<?php

namespace App\Domain\Treasury\MobileMoney;

/**
 * Lit un relevé de transactions Mobile Money exporté en CSV depuis l'application
 * de l'opérateur (Wave / Orange Money / MTN MoMo n'exposent pas d'API "lecture"
 * accessible sans agrément marchand ; l'export manuel par le client est la voie
 * disponible aujourd'hui). Les en-têtes de colonnes varient selon l'opérateur et
 * la langue de l'export : la détection se fait par mots-clés plutôt que par
 * position fixe.
 */
class CsvMobileMoneyConnector implements MobileMoneyConnector
{
    private const DATE_HEADERS = ['date', 'date operation', 'date transaction', 'transaction date', 'created'];

    private const AMOUNT_HEADERS = ['montant', 'amount', 'valeur'];

    private const DIRECTION_HEADERS = ['type', 'sens', 'type operation', 'nature', 'direction'];

    private const REFERENCE_HEADERS = ['reference', 'id transaction', 'transaction id', 'numero transaction', 'ref'];

    private const COUNTERPARTY_NAME_HEADERS = ['nom', 'beneficiaire', 'expediteur', 'correspondant', 'contact'];

    private const COUNTERPARTY_NUMBER_HEADERS = ['telephone', 'msisdn', 'numero correspondant', 'numero destinataire', 'numero expediteur', 'numero'];

    private const IN_KEYWORDS = ['depot', 'reception', 'credit', 'encaissement', 'recu', 'cash in', 'recharge', 'top up', 'entrant'];

    private const OUT_KEYWORDS = ['retrait', 'paiement', 'envoi', 'debit', 'decaissement', 'cash out', 'transfert envoye', 'sortant', 'achat'];

    /**
     * @return list<NormalizedMobileMoneyTransaction>
     */
    public function parse(string $absoluteFilePath, string $operator): array
    {
        $handle = fopen($absoluteFilePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Impossible de lire le fichier : $absoluteFilePath");
        }

        $rows = [];
        $delimiter = $this->detectDelimiter($absoluteFilePath);

        try {
            $header = fgetcsv($handle, 0, $delimiter, '"', '\\');
            if ($header === false) {
                return [];
            }

            $columns = $this->mapColumns($header);

            while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                if (count(array_filter($row, static fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue; // ligne vide
                }

                $normalized = $this->normalizeRow($row, $columns);
                if ($normalized !== null) {
                    $rows[] = $normalized;
                }
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    private function detectDelimiter(string $path): string
    {
        $firstLine = '';
        $handle = fopen($path, 'r');
        if ($handle !== false) {
            $firstLine = (string) fgets($handle);
            fclose($handle);
        }

        return substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    }

    /**
     * @param  list<string>  $header
     * @return array<string, int>
     */
    private function mapColumns(array $header): array
    {
        $normalizedHeader = array_map([$this, 'normalizeLabel'], $header);
        $columns = [];

        $fields = [
            'date' => self::DATE_HEADERS,
            'amount' => self::AMOUNT_HEADERS,
            'direction' => self::DIRECTION_HEADERS,
            'reference' => self::REFERENCE_HEADERS,
            'counterparty_name' => self::COUNTERPARTY_NAME_HEADERS,
            'counterparty_number' => self::COUNTERPARTY_NUMBER_HEADERS,
        ];

        foreach ($fields as $field => $candidates) {
            foreach ($normalizedHeader as $index => $label) {
                foreach ($candidates as $candidate) {
                    if (str_contains($label, $candidate)) {
                        $columns[$field] = $index;
                        continue 3;
                    }
                }
            }
        }

        return $columns;
    }

    private function normalizeLabel(string $label): string
    {
        $label = strtolower(trim($label));
        $label = iconv('UTF-8', 'ASCII//TRANSLIT', $label) ?: $label;

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $label) ?? '');
    }

    /**
     * @param  list<string>  $row
     * @param  array<string, int>  $columns
     */
    private function normalizeRow(array $row, array $columns): ?NormalizedMobileMoneyTransaction
    {
        if (! isset($columns['date'], $columns['amount'])) {
            return null;
        }

        $rawLine = implode(' | ', $row);

        $occurredAt = $this->parseDate((string) ($row[$columns['date']] ?? ''));
        if ($occurredAt === null) {
            return null;
        }

        $rawAmount = (string) ($row[$columns['amount']] ?? '');
        $amount = $this->parseAmount($rawAmount);
        if ($amount === null) {
            return null;
        }

        $direction = $this->resolveDirection($row, $columns, $rawAmount);

        return new NormalizedMobileMoneyTransaction(
            occurredAt: $occurredAt,
            amount: abs($amount),
            direction: $direction,
            externalReference: isset($columns['reference']) ? $this->nullableTrim((string) ($row[$columns['reference']] ?? '')) : null,
            counterpartyName: isset($columns['counterparty_name']) ? $this->nullableTrim((string) ($row[$columns['counterparty_name']] ?? '')) : null,
            counterpartyNumber: isset($columns['counterparty_number']) ? $this->nullableTrim((string) ($row[$columns['counterparty_number']] ?? '')) : null,
            rawLine: $rawLine,
        );
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $formats = ['d/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d', 'd-m-Y'];
        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date !== false) {
                return $date;
            }
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? (new \DateTimeImmutable())->setTimestamp($timestamp) : null;
    }

    private function parseAmount(string $value): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $value = str_replace(["\xC2\xA0", ' ', 'FCFA', 'XOF', 'CFA'], '', $value);

        if (str_contains($value, ',') && ! str_contains($value, '.')) {
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param  list<string>  $row
     * @param  array<string, int>  $columns
     */
    private function resolveDirection(array $row, array $columns, string $rawAmount): string
    {
        if (isset($columns['direction'])) {
            $label = $this->normalizeLabel((string) ($row[$columns['direction']] ?? ''));
            foreach (self::IN_KEYWORDS as $keyword) {
                if (str_contains($label, $keyword)) {
                    return 'in';
                }
            }
            foreach (self::OUT_KEYWORDS as $keyword) {
                if (str_contains($label, $keyword)) {
                    return 'out';
                }
            }
        }

        return str_starts_with(trim($rawAmount), '-') ? 'out' : 'in';
    }

    private function nullableTrim(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
