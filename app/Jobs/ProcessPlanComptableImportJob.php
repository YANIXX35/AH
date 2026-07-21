<?php

namespace App\Jobs;

use App\Models\PlanComptableAccount;
use App\Models\PlanComptableImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessPlanComptableImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(
        public string $storedPath,
        public string $originalFilename,
        public int $userId,
        public array $accountsData
    ) {}

    public function handle(): void
    {
        try {
            if (empty($this->accountsData)) {
                return;
            }

            PlanComptableAccount::where('user_id', $this->userId)->delete();

            $dataToInsert = [];
            foreach ($this->accountsData as $key => $account) {
                $prefix = $account['prefix'] ?? (is_numeric($key) && $key >= 1 && $key <= 7 ? $key : null);
                $label = $account['label'] ?? $account['libelle_compte'] ?? '';

                if (trim($label) === '') {
                    continue;
                }

                $data = [
                    'user_id' => $this->userId,
                    'prefix' => $prefix,
                    'label' => $label,
                    'category' => $account['category'] ?? 'other',
                    'subtype' => $account['subtype'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (isset($account['numero_compte'])) {
                    $data['numero_compte'] = $account['numero_compte'];
                }
                if (isset($account['libelle_compte'])) {
                    $data['libelle_compte'] = $account['libelle_compte'];
                }
                if (isset($account['type_compte'])) {
                    $data['type_compte'] = $account['type_compte'];
                }
                if (isset($account['sous_type'])) {
                    $data['sous_type'] = $account['sous_type'];
                }
                if (isset($account['classe'])) {
                    $data['classe'] = $account['classe'];
                }
                if (isset($account['is_actif'])) {
                    $data['is_actif'] = $account['is_actif'];
                }
                if (isset($account['sort_order'])) {
                    $data['sort_order'] = $account['sort_order'];
                }

                $dataToInsert[] = $data;
            }

            foreach (array_chunk($dataToInsert, 250) as $chunk) {
                try {
                    PlanComptableAccount::insert($chunk);
                } catch (\Throwable $e) {
                    foreach ($chunk as $singleData) {
                        try {
                            PlanComptableAccount::create($singleData);
                        } catch (\Throwable $ex) {
                            // Ignorer les doublons stricts
                        }
                    }
                }
            }

            PlanComptableImport::create([
                'user_id' => $this->userId,
                'original_filename' => $this->originalFilename,
                'status' => 'success',
                'message' => 'Importation en arrière-plan effectuée avec succès ('.count($dataToInsert).' comptes).',
                'valid_rows' => count($dataToInsert),
                'invalid_rows' => 0,
                'invalid_details' => null,
            ]);

            Storage::disk('public')->delete($this->storedPath);

        } catch (\Throwable $e) {
            Log::error('Échec du Job ProcessPlanComptableImportJob: '.$e->getMessage(), ['exception' => $e]);
        }
    }
}
