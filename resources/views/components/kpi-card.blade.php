@props(['label', 'value', 'sub', 'colorClass' => 'text-primary'])
<div class="portfolio-kpi-card {{ $colorClass }}">
    <div class="kpi-label">{{ $label }}</div>
    <div class="kpi-number {{ $colorClass }}">{{ $value }}</div>
    @isset($sub)
        <div class="kpi-sub">{{ $sub }}</div>
    @endisset
</div>
