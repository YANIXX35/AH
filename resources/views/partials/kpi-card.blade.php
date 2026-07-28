@props([
    'title' => '',
    'value' => '0',
    'change' => null,
    'trend' => 'up',
    'icon' => 'activity',
    'badgeColor' => 'primary',
    'subtitle' => null
])

<div class="card p-3 shadow-sm border-0 glass-card" style="background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(12px); border-radius: 14px; border: 1px solid rgba(255, 255, 255, 0.08); transition: transform 0.2s ease, box-shadow 0.2s ease;">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="text-muted small fw-semibold text-uppercase tracking-wider fs-7" style="color: #94a3b8 !important;">{{ $title }}</span>
        <div class="badge-icon p-2 rounded-3 d-flex align-items-center justify-content-center" style="background: rgba(255, 255, 255, 0.05); width: 36px; height: 36px;">
            <i class="feather-{{ $icon }} text-{{ $badgeColor }}" style="font-size: 1.1rem;"></i>
        </div>
    </div>
    <div class="d-flex align-items-baseline justify-content-between">
        <h3 class="mb-0 fw-bold text-white fs-3 font-outfit" style="letter-spacing: -0.5px;">{{ $value }}</h3>
        @if($change !== null)
            <span class="badge rounded-pill px-2 py-1 fs-7 d-inline-flex align-items-center gap-1 {{ $trend === 'up' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' }}">
                <i class="feather-trending-{{ $trend }}"></i> {{ $change }}
            </span>
        @endif
    </div>
    @if($subtitle)
        <div class="mt-2 text-muted fs-7" style="color: #64748b !important;">
            {{ $subtitle }}
        </div>
    @endif
</div>
