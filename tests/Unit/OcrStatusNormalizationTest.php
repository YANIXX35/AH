<?php

namespace Tests\Unit;

use App\Models\AccountingEntry;
use App\Support\OcrStatus;
use Tests\TestCase;

class OcrStatusNormalizationTest extends TestCase
{
    public function test_accounting_entry_normalizes_legacy_ocr_status(): void
    {
        $entry = new AccountingEntry;
        $entry->ocr_status = OcrStatus::LEGACY_MISMATCHED;

        $this->assertSame(OcrStatus::MISMATCH, $entry->ocr_status);
    }

    public function test_unknown_status_falls_back_to_pending(): void
    {
        $entry = new AccountingEntry;
        $entry->ocr_status = 'unknown-status';

        $this->assertSame(OcrStatus::PENDING, $entry->ocr_status);
    }
}
