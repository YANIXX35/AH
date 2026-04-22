#!/usr/bin/env python3
import argparse
import contextlib
import json
import os
import sys
import tempfile
from typing import Any


if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")
if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding="utf-8")
os.environ.setdefault("PADDLE_PDX_DISABLE_MODEL_SOURCE_CHECK", "True")


def make_error(message: str, error_code: str, error_location: str, raw_response: Any | None = None) -> dict[str, Any]:
    return {
        "success": False,
        "message": message,
        "text": "",
        "confidence": 0.0,
        "mode": None,
        "error_code": error_code,
        "error_location": error_location,
        "raw_response": raw_response or {},
    }


def parse_arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Runner PaddleOCR local pour Laravel.")
    parser.add_argument("--input", required=True, help="Chemin absolu vers le fichier à analyser.")
    parser.add_argument("--preferred-device", default="gpu", choices=["gpu", "cpu"], help="Mode préféré pour l'inférence.")
    parser.add_argument("--fallback-to-cpu", default="true", choices=["true", "false"], help="Activer le fallback GPU vers CPU.")
    parser.add_argument("--language", default="fr", help="Langue PaddleOCR.")
    parser.add_argument("--page-num", type=int, default=0, help="Nombre de pages PDF à traiter, 0 = toutes les pages.")
    return parser.parse_args()


def normalize_page(page_result: Any) -> tuple[list[str], list[float], list[dict[str, Any]]]:
    texts: list[str] = []
    scores: list[float] = []
    lines: list[dict[str, Any]] = []

    if isinstance(page_result, dict):
        raw_texts = page_result.get("rec_texts")
        if raw_texts is None:
            raw_texts = []

        raw_scores = page_result.get("rec_scores")
        if raw_scores is None:
            raw_scores = []

        raw_polys = page_result.get("dt_polys")
        if raw_polys is None or (hasattr(raw_polys, "__len__") and len(raw_polys) == 0):
            raw_polys = page_result.get("rec_boxes")
        if raw_polys is None:
            raw_polys = []

        for index, raw_text in enumerate(raw_texts):
            text = str(raw_text).strip()
            if text == "":
                continue

            try:
                score = float(raw_scores[index]) if index < len(raw_scores) else 0.0
            except (TypeError, ValueError):
                score = 0.0

            coords = raw_polys[index].tolist() if index < len(raw_polys) and hasattr(raw_polys[index], "tolist") else (raw_polys[index] if index < len(raw_polys) else [])

            texts.append(text)
            scores.append(score)
            lines.append(
                {
                    "text": text,
                    "score": score,
                    "coords": coords,
                }
            )

        return texts, scores, lines

    if not isinstance(page_result, list):
        return texts, scores, lines

    for line in page_result:
        if not isinstance(line, list) or len(line) < 2:
            continue

        coords = line[0]
        payload = line[1]
        if not isinstance(payload, (list, tuple)) or len(payload) < 2:
            continue

        text = str(payload[0]).strip()
        if text == "":
            continue

        try:
            score = float(payload[1])
        except (TypeError, ValueError):
            score = 0.0

        texts.append(text)
        scores.append(score)
        lines.append(
            {
                "text": text,
                "score": score,
                "coords": coords,
            }
        )

    return texts, scores, lines


def extract_text_from_result(raw_result: Any) -> tuple[str, float, list[dict[str, Any]]]:
    all_texts: list[str] = []
    all_scores: list[float] = []
    pages: list[dict[str, Any]] = []

    if not isinstance(raw_result, list):
        return "", 0.0, pages

    for page_index, page_result in enumerate(raw_result):
        texts, scores, lines = normalize_page(page_result)
        if not texts:
            continue

        pages.append(
            {
                "page_index": page_index,
                "text": "\n".join(texts),
                "avg_score": round(sum(scores) / len(scores), 4) if scores else 0.0,
                "lines": lines,
            }
        )
        all_texts.extend(texts)
        all_scores.extend(scores)

    full_text = "\n".join(all_texts).strip()
    avg_score = round(sum(all_scores) / len(all_scores), 4) if all_scores else 0.0

    return full_text, avg_score, pages


def preprocess_image_file(source_path: str) -> str:
    """
    Pré-traite une image scannée pour améliorer la lisibilité OCR.
    """
    try:
        from PIL import Image, ImageEnhance, ImageFilter, ImageOps  # type: ignore
    except Exception as exc:  # pragma: no cover - dépend de l'environnement local
        raise RuntimeError(f"Pillow indisponible pour le pré-traitement: {exc}") from exc

    image = Image.open(source_path)
    image = ImageOps.exif_transpose(image).convert("RGB")

    # Pipeline léger: contraste, niveaux de gris, débruitage et netteté.
    image = ImageOps.autocontrast(image, cutoff=1)
    image = image.convert("L")
    image = image.filter(ImageFilter.MedianFilter(size=3))
    image = ImageEnhance.Contrast(image).enhance(1.35)
    image = ImageEnhance.Sharpness(image).enhance(1.7)

    # Binarisation douce (préserve les caractères fins).
    image = image.point(lambda p: 255 if p > 165 else 0)

    temp_file = tempfile.NamedTemporaryFile(prefix="paddle_pre_", suffix=".png", delete=False)
    temp_path = temp_file.name
    temp_file.close()
    image.save(temp_path, format="PNG")
    return temp_path


def predict_with_preprocessed_pdf(ocr: Any, file_path: str, page_num: int) -> tuple[list[Any], list[str]]:
    """
    Convertit chaque page PDF en image pré-traitée avant OCR.
    """
    temp_paths: list[str] = []
    raw_pages: list[Any] = []

    try:
        import fitz  # type: ignore
    except Exception as exc:  # pragma: no cover - dépend de l'environnement local
        raise RuntimeError(f"PyMuPDF indisponible pour le pré-traitement PDF: {exc}") from exc

    pdf = fitz.open(file_path)
    try:
        max_pages = pdf.page_count if page_num <= 0 else min(page_num, pdf.page_count)
        for page_index in range(max_pages):
            page = pdf.load_page(page_index)
            # Rend en 2x pour améliorer les scans faibles.
            pix = page.get_pixmap(matrix=fitz.Matrix(2, 2), alpha=False)
            page_tmp = tempfile.NamedTemporaryFile(prefix=f"paddle_pdf_{page_index}_", suffix=".png", delete=False)
            page_path = page_tmp.name
            page_tmp.close()
            pix.save(page_path)
            temp_paths.append(page_path)

            preprocessed_path = preprocess_image_file(page_path)
            temp_paths.append(preprocessed_path)

            page_result = ocr.predict(preprocessed_path, use_textline_orientation=True)
            if isinstance(page_result, list):
                if len(page_result) == 1:
                    raw_pages.append(page_result[0])
                else:
                    raw_pages.extend(page_result)
            else:
                raw_pages.append(page_result)
    finally:
        pdf.close()

    return raw_pages, temp_paths


def guess_is_pdf(path: str) -> bool:
    return path.lower().endswith(".pdf")


def run_ocr(file_path: str, preferred_device: str, fallback_to_cpu: bool, language: str, page_num: int) -> dict[str, Any]:
    if not os.path.exists(file_path):
        return make_error("Fichier introuvable pour PaddleOCR.", "FILE_NOT_FOUND", file_path)

    try:
        from paddleocr import PaddleOCR  # type: ignore
    except Exception as exc:  # pragma: no cover - dépend de l'environnement local
        return make_error(
            "PaddleOCR n'est pas installé dans l'environnement Python configuré.",
            "PADDLE_OCR_IMPORT_ERROR",
            "python import paddleocr",
            {"exception": str(exc)},
        )

    attempted_modes = [preferred_device]
    if preferred_device == "gpu" and fallback_to_cpu:
        attempted_modes.append("cpu")

    last_exception: str | None = None

    is_pdf = guess_is_pdf(file_path)

    for mode in attempted_modes:
        try:
            # On initialise le moteur dans le mode demandé avec l'API PaddleOCR 3.x.
            with contextlib.redirect_stdout(sys.stderr):
                ocr = PaddleOCR(
                    lang=language,
                    device=mode,
                    use_textline_orientation=True,
                )
                raw_result: Any
                temp_paths: list[str] = []

                try:
                    # Pré-traitement prioritaire pour scans (PDF ou image).
                    if is_pdf:
                        raw_result, temp_paths = predict_with_preprocessed_pdf(ocr, file_path, page_num)
                    else:
                        preprocessed_path = preprocess_image_file(file_path)
                        temp_paths = [preprocessed_path]
                        raw_result = ocr.predict(preprocessed_path, use_textline_orientation=True)
                except Exception as preprocess_exc:
                    # Fallback robuste: OCR direct du fichier original.
                    print(
                        f"[WARN] Pré-traitement indisponible ({preprocess_exc}), fallback OCR direct.",
                        file=sys.stderr,
                    )
                    raw_result = ocr.predict(file_path, use_textline_orientation=True)
                    if isinstance(raw_result, list) and page_num > 0:
                        raw_result = raw_result[:page_num]
                finally:
                    for temp_path in temp_paths:
                        try:
                            if os.path.exists(temp_path):
                                os.unlink(temp_path)
                        except OSError:
                            pass

            text, avg_score, pages = extract_text_from_result(raw_result)

            if text == "":
                return make_error(
                    "Aucun texte détecté par PaddleOCR.",
                    "PADDLE_OCR_EMPTY_TEXT",
                    file_path,
                    {"mode": mode, "pages": pages},
                )

            return {
                "success": True,
                "message": f"OCR local réalisé avec succès via PaddleOCR ({mode}).",
                "text": text,
                "confidence": round(avg_score * 100, 2),
                "mode": mode,
                "raw_response": {
                    "pages": pages,
                    "page_count": len(pages),
                },
            }
        except Exception as exc:  # pragma: no cover - dépend de l'environnement local
            last_exception = str(exc)
            if mode == attempted_modes[-1]:
                break

    return make_error(
        "PaddleOCR a échoué dans tous les modes disponibles.",
        "PADDLE_OCR_RUNTIME_ERROR",
        file_path,
        {
            "attempted_modes": attempted_modes,
            "exception": last_exception,
        },
    )


def main() -> int:
    args = parse_arguments()
    response = run_ocr(
        file_path=os.path.abspath(args.input),
        preferred_device=args.preferred_device,
        fallback_to_cpu=args.fallback_to_cpu.lower() == "true",
        language=args.language,
        page_num=args.page_num,
    )
    sys.stdout.write(json.dumps(response, ensure_ascii=False))
    return 0 if response.get("success") else 1


if __name__ == "__main__":
    raise SystemExit(main())
