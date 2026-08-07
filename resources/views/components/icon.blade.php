@props(['name'])

<svg {{ $attributes->merge(['class' => 'h-5 w-5']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    @switch($name)
        @case('home')
            <path d="M4 11.5 12 4l8 7.5" />
            <path d="M6 10v9a1 1 0 0 0 1 1h4v-6h2v6h4a1 1 0 0 0 1-1v-9" />
            @break

        @case('inbox')
            <path d="M4 12h4l2 3h4l2-3h4" />
            <path d="M5.5 5h13l2 7v7a1 1 0 0 1-1 1H4.5a1 1 0 0 1-1-1v-7l2-7Z" />
            @break

        @case('map-pin')
            <path d="M12 21s7-6.1 7-11.5A7 7 0 0 0 5 9.5C5 14.9 12 21 12 21Z" />
            <circle cx="12" cy="9.5" r="2.3" />
            @break

        @case('file-text')
            <path d="M7 3h7l4 4v14a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" />
            <path d="M14 3v4h4" />
            <path d="M9 13h6M9 17h6M9 9h2" />
            @break

        @case('clipboard')
            <path d="M9 4h6a1 1 0 0 1 1 1v1H8V5a1 1 0 0 1 1-1Z" />
            <path d="M6 6.5h12a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7.5a1 1 0 0 1 1-1Z" />
            <path d="M9 12h6M9 16h6" />
            @break

        @case('check-circle')
            <circle cx="12" cy="12" r="8.5" />
            <path d="m8.5 12.2 2.4 2.4 4.8-5" />
            @break

        @case('hard-hat')
            <path d="M4 16h16" />
            <path d="M6 16a6 6 0 0 1 12 0" />
            <path d="M11 7V5h2v2" />
            <path d="M3.5 16h1.7M18.8 16h1.7" />
            @break

        @case('users')
            <circle cx="9" cy="8" r="3" />
            <path d="M3.5 19a5.5 5.5 0 0 1 11 0" />
            <path d="M16 8.2a3 3 0 1 1 0 5.9" />
            <path d="M15.5 13.5c2.3.3 4 1.8 4.5 4.6" />
            @break

        @case('users-round')
            <circle cx="8.5" cy="8" r="3" />
            <circle cx="16" cy="9" r="2.3" />
            <path d="M3 19a5.5 5.5 0 0 1 11 0" />
            <path d="M14.5 15.5c2.2.4 3.6 1.8 4 3.5" />
            @break

        @case('calendar-check')
            <rect x="4" y="5.5" width="16" height="15" rx="1.5" />
            <path d="M4 9.5h16M8 3.5v4M16 3.5v4" />
            <path d="m9 15 2 2 4-4" />
            @break

        @case('wallet')
            <path d="M4 7.5A1.5 1.5 0 0 1 5.5 6h12A1.5 1.5 0 0 1 19 7.5V9H5.5A1.5 1.5 0 0 1 4 7.5Z" />
            <path d="M4 9h15a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V9Z" />
            <circle cx="16" cy="14" r="1.2" />
            @break

        @case('shield-check')
            <path d="M12 3.5 5 6v6c0 4.5 3 7.5 7 8.5 4-1 7-4 7-8.5V6l-7-2.5Z" />
            <path d="m9 12 2 2 4-4.5" />
            @break

        @case('ticket')
            <path d="M4 9a2 2 0 0 0 0-4h16v4a2 2 0 0 0 0 4v4H4v-4a2 2 0 0 0 0-4Z" transform="translate(0 1)" />
            <path d="M9 6v12" stroke-dasharray="2.5 2.5" />
            @break

        @case('banknote')
            <rect x="3" y="6.5" width="18" height="11" rx="1.5" />
            <circle cx="12" cy="12" r="2.5" />
            <path d="M6.5 9v0M17.5 15v0" />
            @break

        @case('scale')
            <path d="M12 3v18M8 21h8" />
            <path d="M12 5 5 8l3.5 7.5a4 4 0 0 0 7 0L19 8Z" />
            @break

        @case('search-check')
            <circle cx="10.5" cy="10.5" r="6.5" />
            <path d="m20 20-3.8-3.8" />
            <path d="m8 10.5 1.7 1.7L13.5 8" />
            @break

        @case('building')
            <rect x="5" y="3.5" width="10" height="17" rx="1" />
            <path d="M15 9.5h4v11H8.5" />
            <path d="M8 7h2M8 10.5h2M8 14h2M8 17.5h2" />
            @break

        @case('bar-chart')
            <path d="M4 20V10M10 20V4M16 20v-7M4 20h16" />
            @break

        @case('user-cog')
            <circle cx="9" cy="8" r="3" />
            <path d="M3.5 19a5.5 5.5 0 0 1 11 0" />
            <circle cx="18" cy="8" r="2.3" />
            <path d="M18 5.3v.9M18 9.8v.9M15.9 6.6l.8.5M19.3 8.9l.8.5M15.9 9.4l.8-.5M19.3 7.1l.8-.5" />
            @break

        @case('key')
            <circle cx="7.5" cy="14.5" r="3.5" />
            <path d="m10 12 8-8M15.5 6.5l2 2M12.5 9.5l2 2" />
            @break

        @case('history')
            <path d="M4 4v5h5" />
            <path d="M4.6 13a8 8 0 1 0 2-8.4L4 9" />
            <path d="M12 8v4.5l3 2" />
            @break

        @case('briefcase')
            <rect x="3" y="7.5" width="18" height="12" rx="1.5" />
            <path d="M8.5 7.5V6a1.5 1.5 0 0 1 1.5-1.5h4A1.5 1.5 0 0 1 15.5 6v1.5" />
            <path d="M3 12.5h18" />
            @break

        @case('receipt')
            <path d="M6 3h12v18l-2.5-1.5L13 21l-2.5-1.5L8 21l-2-1.5V3Z" />
            <path d="M9 8h6M9 12h6M9 16h4" />
            @break

        @case('search')
            <circle cx="10.5" cy="10.5" r="6.5" />
            <path d="m20 20-3.8-3.8" />
            @break

        @case('bell')
            <path d="M6 10a6 6 0 0 1 12 0c0 4 1.5 5.5 1.5 5.5h-15S6 14 6 10Z" />
            <path d="M10 18.5a2 2 0 0 0 4 0" />
            @break

        @case('sun')
            <circle cx="12" cy="12" r="4" />
            <path d="M12 3v1.5M12 19.5V21M4.9 4.9l1 1M18.1 18.1l1 1M3 12h1.5M19.5 12H21M4.9 19.1l1-1M18.1 5.9l1-1" />
            @break

        @case('moon')
            <path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5Z" />
            @break

        @case('chevron-down')
            <path d="m6 9 6 6 6-6" />
            @break

        @case('menu')
            <path d="M4 6.5h16M4 12h16M4 17.5h16" />
            @break

        @case('plus')
            <path d="M12 5v14M5 12h14" />
            @break

        @case('x')
            <path d="M6 6l12 12M18 6 6 18" />
            @break

        @case('trash')
            <path d="M5 7h14M9.5 7V5a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v2M7 7l1 13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1l1-13" />
            @break

        @case('pencil')
            <path d="M4 20l1-4L16 5l3 3L8 19l-4 1Z" />
            @break

        @case('download')
            <path d="M12 4v11M8 11.5l4 4 4-4" />
            <path d="M5 19.5h14" />
            @break

        @case('upload')
            <path d="M12 20V9M8 12.5l4-4 4 4" />
            <path d="M5 19.5h14" />
            @break

        @case('arrow-left')
            <path d="M19 12H6M11 6l-6 6 6 6" />
            @break

        @case('dots')
            <circle cx="5" cy="12" r="1.4" />
            <circle cx="12" cy="12" r="1.4" />
            <circle cx="19" cy="12" r="1.4" />
            @break

        @case('logout')
            <path d="M9 19H6a1.5 1.5 0 0 1-1.5-1.5v-11A1.5 1.5 0 0 1 6 5h3" />
            <path d="M15 16l4-4-4-4M19 12H9" />
            @break

        @case('clock')
            <circle cx="12" cy="12" r="8.5" />
            <path d="M12 7.5V12l3 2" />
            @break

        @case('alert-triangle')
            <path d="M12 4 2.5 20h19L12 4Z" />
            <path d="M12 10v4.5M12 17.5v.1" />
            @break

        @case('paperclip')
            <path d="M8 12.5 15 5.5a3.2 3.2 0 0 1 4.5 4.5L11 18.5a5 5 0 0 1-7-7l8-8" />
            @break

        @case('image')
            <rect x="3.5" y="4.5" width="17" height="15" rx="1.5" />
            <circle cx="9" cy="10" r="1.6" />
            <path d="m5 17.5 5-5 3.5 3.5L18 11l2.5 2.5" />
            @break

        @case('video')
            <rect x="3.5" y="6" width="12" height="12" rx="1.5" />
            <path d="M15.5 10.5 20.5 7v10l-5-3.5Z" />
            @break

        @case('star')
            <path d="m12 3.5 2.6 5.4 5.9.8-4.3 4.2 1 5.9-5.2-2.8-5.2 2.8 1-5.9-4.3-4.2 5.9-.8Z" />
            @break

        @case('rotate-ccw')
            <path d="M4 4v5h5" />
            <path d="M4.6 13a8 8 0 1 0 2.1-8.4L4 9" />
            @break

        @case('layers')
            <path d="m12 3 8.5 4.5L12 12 3.5 7.5 12 3Z" />
            <path d="m3.5 12 8.5 4.5 8.5-4.5" />
            <path d="m3.5 16.5 8.5 4.5 8.5-4.5" />
            @break

        @default
            <circle cx="12" cy="12" r="8.5" />
    @endswitch
</svg>
