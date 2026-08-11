<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\CommercialProspection;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CommercialProspectionController extends Controller
{
    /**
     * Extensions/MIME explicitement acceptés pour un fichier de prospection.
     * Volontairement restrictif : jamais de script exécutable.
     */
    private const ALLOWED_EXTENSIONS = ['xlsx', 'xls', 'csv', 'pdf', 'doc', 'docx'];

    public function index(Request $request): View
    {
        $prospections = CommercialProspection::where('commercial_id', $request->user()->id)
            ->latest('updated_at')
            ->paginate(15);

        return view('commercial.prospections.index', compact('prospections'));
    }

    public function create(): View
    {
        return view('commercial.prospections.form', ['prospection' => new CommercialProspection]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProspection($request);

        $prospection = new CommercialProspection([
            'commercial_id' => $request->user()->id,
            'title' => $validated['title'] ?? null,
            'content' => $validated['content'] ?? null,
            'status' => CommercialProspection::STATUS_DRAFT,
        ]);

        $this->attachFileIfPresent($request, $prospection);
        $this->assertNotEmpty($prospection);
        $prospection->save();

        if ($request->boolean('submit_now')) {
            return $this->submit($request, $prospection);
        }

        return redirect()->route('commercial.prospections.index')
            ->with('status', 'Prospection enregistrée en brouillon.');
    }

    public function show(Request $request, CommercialProspection $prospection): View
    {
        $this->authorize('view', $prospection);

        return view('commercial.prospections.show', compact('prospection'));
    }

    public function edit(CommercialProspection $prospection): View
    {
        $this->authorize('update', $prospection);

        return view('commercial.prospections.form', compact('prospection'));
    }

    public function update(Request $request, CommercialProspection $prospection): RedirectResponse
    {
        $this->authorize('update', $prospection);

        $validated = $this->validateProspection($request);

        $prospection->title = $validated['title'] ?? null;
        $prospection->content = $validated['content'] ?? null;

        if ($request->boolean('remove_file') && $prospection->hasFile()) {
            Storage::disk('public')->delete($prospection->file_path);
            $prospection->file_path = null;
            $prospection->file_name = null;
            $prospection->file_type = null;
            $prospection->file_size = null;
        }

        $this->attachFileIfPresent($request, $prospection);
        $this->assertNotEmpty($prospection);

        // Un rapport retourné pour correction redevient un brouillon une fois retouché.
        if ($prospection->status === CommercialProspection::STATUS_NEEDS_REVISION) {
            $prospection->status = CommercialProspection::STATUS_DRAFT;
        }

        $prospection->save();

        if ($request->boolean('submit_now')) {
            return $this->submit($request, $prospection);
        }

        return redirect()->route('commercial.prospections.index')
            ->with('status', 'Prospection mise à jour.');
    }

    public function destroy(CommercialProspection $prospection): RedirectResponse
    {
        $this->authorize('delete', $prospection);

        if ($prospection->hasFile()) {
            Storage::disk('public')->delete($prospection->file_path);
        }
        $prospection->delete();

        return redirect()->route('commercial.prospections.index')
            ->with('status', 'Brouillon supprimé.');
    }

    public function submit(Request $request, CommercialProspection $prospection): RedirectResponse
    {
        $this->authorize('submit', $prospection);

        if ($prospection->isEmpty()) {
            return redirect()->back()->withErrors([
                'content' => 'Impossible d’envoyer une prospection vide : ajoutez un texte ou un fichier.',
            ]);
        }

        $prospection->update([
            'status' => CommercialProspection::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $this->notifyAdmins($prospection);

        return redirect()->route('commercial.prospections.index')
            ->with('status', 'Prospection envoyée à l’administration.');
    }

    /**
     * @return array{title?: ?string, content?: ?string}
     */
    private function validateProspection(Request $request): array
    {
        return $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:20000'],
            'file' => ['nullable', 'file', 'max:20480', 'mimes:'.implode(',', self::ALLOWED_EXTENSIONS)],
        ]);
    }

    private function attachFileIfPresent(Request $request, CommercialProspection $prospection): void
    {
        if (! $request->hasFile('file')) {
            return;
        }

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'file' => 'Type de fichier non autorisé. Formats acceptés : '.implode(', ', self::ALLOWED_EXTENSIONS).'.',
            ]);
        }

        // Remplace un éventuel fichier déjà attaché.
        if ($prospection->hasFile()) {
            Storage::disk('public')->delete($prospection->file_path);
        }

        $prospection->file_path = $file->store('commercial_prospections', 'public');
        $prospection->file_name = $file->getClientOriginalName();
        $prospection->file_type = $extension;
        $prospection->file_size = $file->getSize();
    }

    private function assertNotEmpty(CommercialProspection $prospection): void
    {
        if ($prospection->isEmpty()) {
            throw ValidationException::withMessages([
                'content' => 'Une prospection doit contenir un texte, un fichier, ou les deux.',
            ]);
        }
    }

    private function notifyAdmins(CommercialProspection $prospection): void
    {
        $admins = User::query()->where('is_platform_admin', true)->get(['id']);
        $label = $prospection->title ?: 'Prospection du '.$prospection->submitted_at->format('d/m/Y');

        foreach ($admins as $admin) {
            AppNotification::create([
                'user_id' => $admin->id,
                'title' => 'Nouvelle prospection commerciale',
                'body' => $prospection->commercial->name.' a envoyé « '.$label.' » pour vérification.',
                'type' => 'info',
                'action_url' => route('admin.prospections.show', $prospection),
            ]);
        }
    }
}
