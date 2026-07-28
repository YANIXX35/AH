@props([
    'headers' => [],
    'rows' => [],
    'emptyMessage' => 'Aucune donnée disponible',
    'id' => 'data-table-' . uniqid()
])

<div class="table-responsive rounded-3 glass-card" style="background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.08);">
    <table id="{{ $id }}" class="table align-middle mb-0 text-white" style="border-color: rgba(255,255,255,0.06);">
        <thead style="background: rgba(255, 255, 255, 0.03);">
            <tr>
                @foreach($headers as $header)
                    <th scope="col" class="py-3 px-4 text-muted fw-semibold text-uppercase fs-7" style="color: #94a3b8 !important;">{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr style="transition: background 0.15s ease;" onmouseover="this.style.background='rgba(255,255,255,0.02)'" onmouseout="this.style.background='transparent'">
                    @foreach($row as $cell)
                        <td class="py-3 px-4 fs-6 text-white">{!! $cell !!}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headers) ?: 1 }}" class="text-center py-4 text-muted fs-6" style="color: #64748b !important;">
                        <i class="feather-inbox me-2"></i> {{ $emptyMessage }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
