@props(['class' => 'h-9 w-auto'])

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" fill="none" {{ $attributes->merge(['class' => $class]) }}>
    <g stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity="0.55">
        <line x1="24" y1="2" x2="24" y2="6" />
        <line x1="24" y1="42" x2="24" y2="46" />
        <line x1="2" y1="24" x2="6" y2="24" />
        <line x1="42" y1="24" x2="46" y2="24" />
        <line x1="8.2" y1="8.2" x2="10.9" y2="10.9" />
        <line x1="37.1" y1="37.1" x2="39.8" y2="39.8" />
        <line x1="8.2" y1="39.8" x2="10.9" y2="37.1" />
        <line x1="37.1" y1="10.9" x2="39.8" y2="8.2" />
    </g>
    <circle cx="24" cy="24" r="16" stroke="currentColor" stroke-width="2" />
    <circle cx="24" cy="24" r="12.5" stroke="currentColor" stroke-width="1" opacity="0.5" />
    <path d="M17.5 24.5l4.2 4.2 9-9.4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none" />
</svg>