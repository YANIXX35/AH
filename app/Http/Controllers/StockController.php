<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\StockService;
use App\Http\Controllers\Concerns\UsesClientWorkspace;
use App\Http\Requests\StockProductRequest;
use App\Models\StockProduct;
use App\Support\CompanyLogo;
use App\Support\Export\TabularDocumentExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class StockController extends Controller
{
    use UsesClientWorkspace;

    public function __construct(private readonly StockService $stockService) {}

    public function index(): View
    {
        $products = StockProduct::where('user_id', $this->workspaceUserId())
            ->where('is_active', true)
            ->orderBy('name')
            ->paginate(20);

        $totalValue = StockProduct::where('user_id', $this->workspaceUserId())
            ->where('is_active', true)
            ->selectRaw('SUM(quantity_on_hand * average_cost) as total')
            ->value('total');

        $lowStockCount = StockProduct::where('user_id', $this->workspaceUserId())
            ->where('is_active', true)
            ->whereNotNull('reorder_threshold')
            ->whereColumn('quantity_on_hand', '<', 'reorder_threshold')
            ->count();

        $archivedCount = StockProduct::where('user_id', $this->workspaceUserId())
            ->where('is_active', false)
            ->count();

        return view('stock.index', [
            'products' => $products,
            'totalValue' => (float) ($totalValue ?? 0),
            'lowStockCount' => $lowStockCount,
            'archivedCount' => $archivedCount,
        ]);
    }

    public function create(): View
    {
        return view('stock.create');
    }

    public function store(StockProductRequest $request): RedirectResponse
    {
        $product = $this->stockService->createProduct($this->workspaceUserId(), auth()->id(), $request->validated());

        return redirect()->route('stock.show', $product)->with('success', 'Produit créé.');
    }

    public function show(StockProduct $product): View
    {
        $this->authorizeProduct($product);
        $product->load('movements');

        return view('stock.show', ['product' => $product]);
    }

    public function downloadPdf(StockProduct $product)
    {
        $this->authorizeProduct($product);
        $product->load(['movements', 'user']);

        $pdf = Pdf::loadView('stock.pdf', [
            'product' => $product,
            'companyLogo' => CompanyLogo::toDataUri($product->user->company_logo),
        ]);

        return $pdf->download('fiche-stock-'.($product->sku ?: $product->id).'.pdf');
    }

    public function export(StockProduct $product, string $format, TabularDocumentExporter $exporter)
    {
        $this->authorizeProduct($product);

        if ($format === 'pdf') {
            return $this->downloadPdf($product);
        }

        $product->load(['movements', 'user']);
        $filename = 'fiche-stock-'.($product->sku ?: $product->id);
        $title = 'Fiche stock — '.$product->name;
        $logoPath = CompanyLogo::absolutePath($product->user->company_logo);

        $summary = [
            ['Référence', $product->sku ?: '-'],
            ['Quantité en stock', number_format((float) $product->quantity_on_hand, 2, ',', ' ').' '.$product->unit],
            ['CUMP', number_format((float) $product->average_cost, 0, ',', ' ').' FCFA'],
            ['Valeur du stock', number_format($product->stockValue(), 0, ',', ' ').' FCFA'],
            ['Prix de vente', number_format((float) $product->sale_price, 0, ',', ' ').' FCFA'],
        ];
        if ($product->reorder_threshold !== null) {
            $summary[] = ['Seuil de réapprovisionnement', number_format((float) $product->reorder_threshold, 2, ',', ' ').' '.$product->unit];
        }

        $headers = ['Date', 'Type', 'Quantité', 'Coût unitaire', 'Solde après', 'Motif'];
        $typeLabels = ['entree' => 'Entrée', 'sortie' => 'Sortie', 'ajustement' => 'Ajustement'];
        $rows = $product->movements->map(fn ($m) => [
            optional($m->movement_date)->format('d/m/Y'),
            $typeLabels[$m->type] ?? $m->type,
            number_format((float) $m->quantity, 2, ',', ' '),
            $m->unit_cost !== null ? number_format((float) $m->unit_cost, 0, ',', ' ').' FCFA' : '-',
            number_format((float) $m->quantity_after, 2, ',', ' '),
            $m->reason ?: '-',
        ])->all();

        return match ($format) {
            'csv' => $exporter->csv($filename, $title, $summary, $headers, $rows),
            'xlsx' => $exporter->excel($filename, $title, $summary, $headers, $rows, $logoPath),
            'docx' => $exporter->word($filename, $title, $summary, $headers, $rows, $logoPath),
            default => abort(404),
        };
    }

    public function edit(StockProduct $product): View
    {
        $this->authorizeProduct($product);

        return view('stock.edit', [
            'product' => $product,
            'hasMovements' => $product->movements()->exists(),
        ]);
    }

    public function update(StockProductRequest $request, StockProduct $product): RedirectResponse
    {
        $this->authorizeProduct($product);

        $this->stockService->updateProduct($product, $request->validated(), auth()->id());

        return redirect()->route('stock.show', $product)->with('success', 'Produit mis à jour.');
    }

    public function destroy(StockProduct $product): RedirectResponse
    {
        $this->authorizeProduct($product);

        $result = $this->stockService->deleteProduct($product, auth()->id());

        $message = $result === 'deleted'
            ? 'Produit supprimé.'
            : "Produit archivé (des mouvements existent, l'historique est conservé) — retirable de la liste mais toujours consultable.";

        return redirect()->route('stock.index')->with('success', $message);
    }

    public function storeMovement(Request $request, StockProduct $product): RedirectResponse
    {
        $this->authorizeProduct($product);

        $validated = $request->validate([
            'type' => ['required', 'in:entree,sortie,ajustement'],
            'quantity' => ['required', 'numeric'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'movement_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (in_array($validated['type'], ['entree', 'sortie'], true) && (float) $validated['quantity'] <= 0) {
            return back()->withErrors(['quantity' => 'La quantité doit être positive pour une entrée ou une sortie.']);
        }

        try {
            $this->stockService->recordMovement(
                $product,
                $validated['type'],
                (float) $validated['quantity'],
                isset($validated['unit_cost']) ? (float) $validated['unit_cost'] : null,
                Carbon::parse($validated['movement_date']),
                $validated['reason'] ?? null,
                $validated['notes'] ?? null,
                auth()->id()
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()]);
        }

        return back()->with('success', 'Mouvement enregistré.');
    }

    private function authorizeProduct(StockProduct $product): void
    {
        abort_unless($product->user_id === $this->workspaceUserId(), 403);
    }
}
