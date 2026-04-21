{{--
  Champ fichier : capture mobile (attribut HTML5) + bouton webcam navigateur (script webcam-capture.js).
  Optionnel : webam => false pour désactiver le bouton Web.
--}}
@php
    $inputId = $id ?? $name;
    $accept = $accept ?? 'image/*';
    $showWebcam = !isset($webcam) || $webcam;
    $facingWeb = $capture ?? 'environment';
@endphp
<div class="{{ $wrapperClass ?? 'mb-3' }}">
    <label class="form-label" for="{{ $inputId }}">{{ $label }}</label>
    <div class="d-flex gap-2 flex-wrap align-items-stretch">
        <input
            type="file"
            name="{{ $name }}"
            id="{{ $inputId }}"
            class="form-control {{ $class ?? '' }} @error($name) is-invalid @enderror flex-grow-1"
            style="min-width: 200px;"
            accept="{{ $accept }}"
            @if(!empty($capture)) capture="{{ $capture }}" @endif
            @if(!empty($required) && $required) required @endif
        >
        @if($showWebcam)
            <button type="button" class="btn btn-outline-secondary btn-sm js-webcam-open align-self-center text-nowrap px-3" data-webcam-for="{{ $inputId }}" data-webcam-facing="{{ $facingWeb }}" title="Prendre une photo avec la webcam (ordinateur ou navigateur)">
                <span aria-hidden="true">📷</span> Web
            </button>
        @endif
    </div>
    @error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @if(!empty($help))
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
