@props(['name', 'class' => 'size-5'])

{{-- Лінійні іконки в одному стилі (stroke 1.75, 24×24), без сторонніх бібліотек. Декоративні: підпис дає текст поруч. --}}
<svg {{ $attributes->merge(['class' => $class]) }} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('menu')
            <path d="M4 7h16M4 12h16M4 17h16" />
            @break
        @case('x')
            <path d="M6 6l12 12M18 6L6 18" />
            @break
        @case('phone')
            <path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2" />
            @break
        @case('map-pin')
            <path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z" />
            <circle cx="12" cy="9.5" r="2.5" />
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 2" />
            @break
        @case('mail')
            <rect x="3" y="5" width="18" height="14" rx="2" />
            <path d="M3 7l9 6 9-6" />
            @break
        @case('search')
            <circle cx="11" cy="11" r="7" />
            <path d="M20 20l-3.5-3.5" />
            @break
        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="2" />
            <path d="M16 3v4M8 3v4M3 11h18" />
            @break
        @case('utensils')
            <path d="M7 3v8M4 3v5a3 3 0 0 0 6 0V3M7 11v10M17 21V3c-2 1-3 4-3 7h3" />
            @break
        @case('qr')
            <rect x="4" y="4" width="6" height="6" rx="1" />
            <rect x="14" y="4" width="6" height="6" rx="1" />
            <rect x="4" y="14" width="6" height="6" rx="1" />
            <path d="M14 14h2v2h-2zM18 18h2v2h-2zM14 18h2M18 14h2" />
            @break
        @case('arrow-right')
            <path d="M5 12h14M13 6l6 6-6 6" />
            @break
        @case('instagram')
            <rect x="3" y="3" width="18" height="18" rx="5" />
            <circle cx="12" cy="12" r="4" />
            <path d="M17.5 6.5h.01" />
            @break
        @case('facebook')
            <path d="M15 3h-2a4 4 0 0 0-4 4v3H7v4h2v7h4v-7h3l1-4h-4V7a1 1 0 0 1 1-1h2z" />
            @break
        @case('telegram')
            <path d="M21 4L3 11l6 2 2 6 3-4 5 4 2-15zM9 13l8-6" />
            @break
        @case('plate')
            <circle cx="12" cy="12" r="8" />
            <circle cx="12" cy="12" r="4.5" />
            @break
        @case('check')
            <path d="M5 12.5l4.5 4.5L19 7.5" />
            @break
        @case('chevron-left')
            <path d="M15 5l-7 7 7 7" />
            @break
        @case('chevron-right')
            <path d="M9 5l7 7-7 7" />
            @break
        @case('minus')
            <path d="M5 12h14" />
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14" />
            @break
        @case('users')
            <circle cx="9" cy="8" r="3.5" />
            <path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.2a6.5 6.5 0 0 1 3.5 5.8" />
            @break
        @case('alert')
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7.5v5M12 16h.01" />
            @break
        @case('bell')
            <path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15zM10 20.5a2 2 0 0 0 4 0" />
            @break
        @case('filter')
            <path d="M4 6h16M7 12h10M10 18h4" />
            @break
    @endswitch
</svg>
