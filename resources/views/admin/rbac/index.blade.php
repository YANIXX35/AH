@extends('layouts.app')

@section('title', 'RBAC')
@section('page_title', 'Gestion avancée des rôles (RBAC)')

@section('content')
<div class="container-fluid p-0">
    <div class="card">
        <div class="card-header"><h5 class="card-title mb-0">Rôles, permissions modules et traçabilité</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Utilisateur</th><th>Email</th><th>Rôle</th><th>Permissions modules</th><th></th></tr></thead>
                    <tbody>
                    @foreach($users as $u)
                        <tr>
                            <td>{{ $u->name }}</td>
                            <td>{{ $u->email }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.rbac.update', $u) }}" class="d-flex gap-2 align-items-center">
                                    @csrf
                                    <select name="role_key" class="form-select form-select-sm">
                                        @foreach($availableRoles as $key => $label)
                                            <option value="{{ $key }}" {{ (string) $u->role_key === $key ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                            </td>
                            <td>
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach($modules as $module)
                                            @php($checked = (bool) (($u->module_permissions[$module] ?? false)))
                                            <label class="form-check form-check-inline m-0">
                                                <input type="checkbox" name="modules[]" value="{{ $module }}" class="form-check-input" {{ $checked ? 'checked' : '' }}>
                                                <span class="form-check-label small">{{ $module }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                            </td>
                            <td class="text-end">
                                    <button class="btn btn-sm btn-primary">Enregistrer</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-2">{{ $users->links() }}</div>
        </div>
    </div>
</div>
@endsection
