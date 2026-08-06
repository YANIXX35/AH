@php
    $hasFile = !empty($path);
    $inputId = 'doc_' . $type . '_' . $client->id;
@endphp
<div class="d-flex flex-column gap-2">
    <div class="d-flex align-items-center gap-2">
        @if($hasFile)
            <span class="badge bg-light-success text-success py-1 px-2">
                <i data-feather="check" style="width:12px;height:12px;"></i> Fourni
            </span>
            <a href="{{ route('accountant.documents.view', ['user' => $client, 'type' => $type]) }}" target="_blank" rel="noopener" class="small text-decoration-none d-inline-flex align-items-center gap-1">
                <i data-feather="eye" style="width:14px;height:14px;"></i> Aperçu
            </a>
        @else
            <span class="badge bg-light-secondary text-secondary py-1 px-2">Non fourni</span>
        @endif
    </div>
    <form action="{{ route('accountant.documents.update', ['user' => $client, 'type' => $type]) }}" method="POST" enctype="multipart/form-data" class="d-flex align-items-center gap-1">
        @csrf
        <input type="file" name="file" id="{{ $inputId }}" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,image/*" style="max-width: 210px;" required>
        <button type="submit" class="btn btn-sm btn-outline-primary flex-shrink-0" title="{{ $hasFile ? 'Remplacer le fichier' : 'Ajouter le fichier' }}">
            <i data-feather="upload" style="width:14px;height:14px;"></i>
        </button>
    </form>
</div>
