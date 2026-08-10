<?php

namespace App\Http\Controllers;

use App\Models\PlanComptableAccount;
use App\Models\PlanComptableDefault;
use App\Services\AdminAuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AdminPlanComptableController extends Controller
{
    public function __construct(
        private readonly AdminAuditTrailService $auditTrail
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $query = PlanComptableDefault::query()->orderBy('sort_order');
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('numero_compte', 'like', '%'.$search.'%')
                    ->orWhere('libelle_compte', 'like', '%'.$search.'%');
            });
        }

        $accounts = $query->paginate(50)->withQueryString();

        $total = PlanComptableDefault::count();
        $byClass = PlanComptableDefault::query()
            ->selectRaw('classe, count(*) as total')
            ->groupBy('classe')
            ->orderBy('classe')
            ->pluck('total', 'classe');

        $lastUpdatedAt = PlanComptableDefault::query()->max('updated_at');
        $companiesUsingDefaults = PlanComptableAccount::query()->distinct('user_id')->count('user_id');

        return view('admin.plan-comptable', [
            'accounts' => $accounts,
            'search' => $search,
            'total' => $total,
            'byClass' => $byClass,
            'lastUpdatedAt' => $lastUpdatedAt,
            'companiesUsingDefaults' => $companiesUsingDefaults,
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'plan_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:51200'],
        ], [
            'plan_file.required' => 'Veuillez choisir un fichier.',
            'plan_file.mimes' => 'Le fichier doit être un Excel (.xlsx, .xls) ou un CSV.',
            'plan_file.max' => 'Fichier trop volumineux (50 Mo maximum).',
        ]);

        $file = $request->file('plan_file');
        $path = $file->getRealPath();

        try {
            $accounts = $this->parseFullPlanFile($path);
        } catch (\Throwable $e) {
            Log::error('admin_plan_comptable_upload_failed', ['message' => $e->getMessage()]);

            return redirect()->route('admin.plan-comptable.index')->withErrors([
                'plan_file' => 'Impossible de lire ce fichier : '.$e->getMessage(),
            ]);
        }

        if (empty($accounts)) {
            return redirect()->route('admin.plan-comptable.index')->withErrors([
                'plan_file' => 'Aucun compte valide trouvé. Le fichier doit contenir au minimum les colonnes Classe, Compte et Intitulé.',
            ]);
        }

        $before = ['total_accounts' => PlanComptableDefault::count()];

        DB::transaction(function () use ($accounts) {
            PlanComptableDefault::query()->delete();

            $now = now();
            $rows = array_map(static fn (array $a) => [...$a, 'created_at' => $now, 'updated_at' => $now], $accounts);

            foreach (array_chunk($rows, 250) as $chunk) {
                PlanComptableDefault::insert($chunk);
            }
        });

        $this->auditTrail->log(
            action: 'plan_comptable_defaults.replaced',
            targetType: 'plan_comptable_defaults',
            targetId: null,
            actorUserId: $request->user()?->id,
            before: $before,
            after: ['total_accounts' => count($accounts)],
            meta: ['original_filename' => $file->getClientOriginalName()],
            request: $request
        );

        return redirect()->route('admin.plan-comptable.index')->with(
            'status',
            'Plan comptable de référence remplacé : '.count($accounts).' comptes chargés. Les nouveaux comptes et le bouton "Réinitialiser" utiliseront désormais ce plan. Cliquez sur "Appliquer aux comptes existants" pour le propager immédiatement.'
        );
    }

    public function applyToExisting(Request $request): RedirectResponse
    {
        $userIds = PlanComptableAccount::query()->distinct()->pluck('user_id')->filter();

        foreach ($userIds as $userId) {
            PlanComptableAccount::seedDefaultsFor((int) $userId);
        }

        $this->auditTrail->log(
            action: 'plan_comptable_defaults.applied_to_existing',
            targetType: 'plan_comptable_accounts',
            targetId: null,
            actorUserId: $request->user()?->id,
            meta: ['affected_companies' => $userIds->count()],
            request: $request
        );

        return redirect()->route('admin.plan-comptable.index')->with(
            'status',
            'Plan de référence appliqué à '.$userIds->count().' compte(s) déjà existant(s).'
        );
    }

    /**
     * Lit un fichier Excel/CSV complet (11 colonnes SYSCOHADA si présentes,
     * uniquement Classe/Compte/Intitulé si c'est un fichier plus simple) et
     * retourne un tableau de comptes prêt à insérer dans plan_comptable_defaults.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseFullPlanFile(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        if (method_exists($reader, 'listWorksheetNames')) {
            $sheetNames = $reader->listWorksheetNames($path);
            foreach (['Plan_Comptable', 'Plan Comptable', 'Plan Comptable SYSCOHADA'] as $candidate) {
                if (in_array($candidate, $sheetNames, true) && method_exists($reader, 'setLoadSheetsOnly')) {
                    $reader->setLoadSheetsOnly([$candidate]);
                    break;
                }
            }
        }

        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $maxRow = $sheet->getHighestRow();
        $maxCol = $sheet->getHighestColumn();

        $headerMap = [
            'classe' => 'classe',
            'compte' => 'compte',
            'intitule' => 'intitule',
            'intitulé' => 'intitule',
            'type' => 'type',
            'observation' => 'observation',
            'nature' => 'nature',
            'categorie bceao' => 'categorie_bceao',
            'catégorie bceao' => 'categorie_bceao',
            'flux tafire' => 'flux_tafire',
            'eligible tva' => 'eligible_tva',
            'éligible tva' => 'eligible_tva',
            'eligible echeancier' => 'eligible_echeancier',
            'éligible échéancier' => 'eligible_echeancier',
            'lie immobilisation' => 'lie_immobilisation',
            'lié immobilisation' => 'lie_immobilisation',
        ];

        $columns = [];
        foreach (range('A', $maxCol) as $col) {
            $header = mb_strtolower(trim((string) $sheet->getCell($col.'1')->getFormattedValue()));
            if (isset($headerMap[$header])) {
                $columns[$headerMap[$header]] = $col;
            }
        }

        if (! isset($columns['compte']) || ! isset($columns['intitule'])) {
            throw new \RuntimeException('Colonnes "Compte" et "Intitulé" introuvables sur la première ligne.');
        }

        $accounts = [];
        $sort = 0;
        for ($r = 2; $r <= $maxRow; $r++) {
            $compte = trim((string) $sheet->getCell($columns['compte'].$r)->getFormattedValue());
            $intitule = trim((string) $sheet->getCell($columns['intitule'].$r)->getFormattedValue());

            if ($compte === '' || $intitule === '') {
                continue;
            }
            if (! preg_match('/^([1-9][0-9]{0,8})$/', $compte)) {
                continue;
            }

            $prefix = $compte[0];
            $get = fn (string $key) => isset($columns[$key])
                ? trim((string) $sheet->getCell($columns[$key].$r)->getFormattedValue())
                : '';

            $accounts[$compte] = [
                'numero_compte' => $compte,
                'libelle_compte' => $intitule,
                'prefix' => $prefix,
                'classe' => $prefix,
                'category' => $this->categoryForPrefix($prefix),
                'subtype' => $this->subtypeForPrefix($prefix),
                'type_compte' => $get('type') !== '' ? $get('type') : null,
                'observation' => $get('observation') !== '' ? $get('observation') : null,
                'nature' => $get('nature') !== '' ? $get('nature') : null,
                'categorie_bceao' => $get('categorie_bceao') !== '' ? $get('categorie_bceao') : null,
                'flux_tafire' => (($v = $get('flux_tafire')) !== '' && $v !== 'Non') ? $v : null,
                'eligible_tva' => (($v = $get('eligible_tva')) !== '' && $v !== 'Non') ? $v : null,
                'eligible_echeancier' => $get('eligible_echeancier') === 'Oui',
                'lie_immobilisation' => $get('lie_immobilisation') === 'Oui',
                'is_actif' => true,
                'sort_order' => $sort++,
            ];
        }

        return array_values($accounts);
    }

    private function categoryForPrefix(string $prefix): string
    {
        return match ($prefix) {
            '1', '2', '3', '4', '5' => 'balance',
            '6', '7' => 'resultat',
            '8' => 'hors_bilan',
            '9' => 'analytique',
            default => 'other',
        };
    }

    private function subtypeForPrefix(string $prefix): ?string
    {
        return match ($prefix) {
            '2' => 'investissement',
            '6' => 'charge',
            '7' => 'produit',
            default => null,
        };
    }
}
