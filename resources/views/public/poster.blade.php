<!DOCTYPE html>
<html lang="en" class="{{ $size === 'letter' ? 'letter' : 'tabloid' }}{{ $mono ? ' mono' : '' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMC {{ $monthLabel }} Events</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <style>
        html {
            font-size: 100px;
        }

        html.letter {
            font-size: 77px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            size: {{ $size === 'letter' ? '8.5in 11in' : '11in 17in' }};
            margin: 0;
        }

        body {
            font-family: 'Lexend', sans-serif;
            background: #888;
            color: #5C3D2E;
            display: flex;
            justify-content: center;
            padding: 40px 0;
        }

        .poster {
            width: 3300px;
            height: 5100px;
            background: #fff;
            position: relative;
            padding: 1rem 1rem 1.4rem 1rem;
            display: flex;
            flex-direction: column;
            transform-origin: top left;
            transform: scale(0.2);
            margin-bottom: -4080px;
            margin-right: -2640px;
        }

        .letter .poster {
            width: 2550px;
            height: 3300px;
            margin-bottom: -2640px;
            margin-right: -2040px;
        }

        @media print {
            html {
                font-size: 0.3333in;
                margin: 0 !important;
                padding: 0 !important;
            }

            html.letter {
                font-size: 0.2576in;
            }

            body {
                background: none;
                display: block;
                margin: 0 !important;
                padding: 0 !important;
            }

            .poster {
                width: 11in;
                height: 17in;
                transform: none;
                margin: 0;
                padding: 1rem 1rem 1.4rem 1rem;
                position: static;
            }

            .letter .poster {
                width: 8.5in;
                height: 11in;
            }

            .preview-controls {
                display: none;
            }
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 0.2rem;
        }

        .logo {
            width: auto;
            height: 2.8rem;
            flex-shrink: 0;
            margin-right: -0.4rem;
            --speaker-case: #E5771E;
            --speaker-front: #B8DDE1;
            --speaker-fill: #FFE28A;
            /* --sound-lines: #3A8C96; */
        }

        .logo svg {
            width: 100%;
            height: 100%;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .org-name {
            font-size: 1.08rem;
            font-weight: 700;
            color: #3A8C96;
            line-height: 1.2;
        }

        .month-label {
            font-size: 1.2rem;
            font-weight: 500;
            color: #5C3D2E;
            text-align: right;
            padding-top: 0.4rem;
            text-transform: lowercase;
        }

        .header-rule {
            width: 100%;
            height: 0.06rem;
            background: #5C3D2E;
            border: none;
            margin-bottom: 0.6rem;
        }

        /* Main Content */
        .content {
            position: relative;
            display: flex;
            gap: 0.6rem;
            margin: 0 0 auto 0;
        }

        .vertical-title {
            writing-mode: vertical-lr;
            transform: rotate(180deg);
            font-size: 1.48rem;
            font-weight: 900;
            color: #5C3D2E;
            letter-spacing: 0.09rem;
            text-transform: uppercase;
            align-self: flex-start;
            line-height: 1;
            flex-shrink: 0;
            white-space: nowrap;
            margin-left: 0.2rem;
        }

        .events {
            flex: 1;
            display: grid;
            grid-template-columns: 3.2rem 2.2rem 1fr;
            gap: 0.3rem 0.4rem;
            align-content: start;
        }

        /* Date square */
        .date-square {
            border-radius: 0.4rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            padding: 0.2rem 0;
        }

        .date-square .day-abbrev {
            font-size: 0.72rem;
            font-weight: 800;
            line-height: 1.1;
            text-transform: uppercase;
        }

        .date-square .date-num {
            font-size: 0.88rem;
            font-weight: 900;
            line-height: 1;
        }

        .date-square:nth-of-type(4n+1) {
            background: #D97A3E;
        }

        .date-square:nth-of-type(4n+2) {
            background: #3A8C96;
        }

        .date-square:nth-of-type(4n+3) {
            background: #E8B830;
        }

        .date-square:nth-of-type(4n+4) {
            background: #CF5C42;
        }

        /* Time pill */
        .time-pill {
            border: 0.04rem solid #5C3D2E;
            border-radius: 0.4rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .time-pill span {
            font-size: 0.48rem;
            font-weight: 600;
            color: #5C3D2E;
        }

        /* Band box */
        .band-box {
            border: 0.04rem solid #5C3D2E;
            border-radius: 0.4rem;
            padding: 0.4rem 0.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .event-title {
            font-size: 0.64rem;
            font-weight: 700;
            line-height: 1.3;
            color: #5C3D2E;
        }

        .event-performers {
            font-size: 0.42rem;
            font-weight: 400;
            line-height: 1.4;
            color: #5C3D2E;
            margin-top: 0.08rem;
        }

        .event-title-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.2rem;
        }

        .free-badge {
            font-size: 0.34rem;
            font-weight: 700;
            color: #3A8C96;
            border: 0.03rem solid #3A8C96;
            border-radius: 0.2rem;
            padding: 0.04rem 0.16rem;
            white-space: nowrap;
            flex-shrink: 0;
        }

        /* Footer note */
        .footer-note {
            align-self: flex-end;
            text-align: left;
            font-size: 0.4rem;
            font-weight: 600;
            color: #3A8C96;
            letter-spacing: 0.02rem;
        }

        /* Footer */
        .footer-info {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: flex-end;
            text-align: right;
            gap: 0.5em;
        }

        .footer-info-left,
        .footer-info-right {
            font-size: 0.42rem;
            font-weight: 500;
            color: #5C3D2E;
            line-height: 1.5;
        }

        .footer-info-right {
            text-align: right;
        }

        .footer-bottom {
            display: flex;
            flex: auto;
            padding-top: .2rem;
            margin-top: .2rem;
            justify-content: space-between;
            align-items: flex-start;
            border-top: 0.06rem solid #5C3D2E;
        }

        .sponsor {
            flex: 1;
            display: flex;
            align-items: flex-start;
            flex-direction: column;
            gap: 0.2rem;
        }

        .sponsor-label {
            font-size: 0.28rem;
            font-weight: 400;
            color: #9A8577;
            text-transform: uppercase;
            letter-spacing: 0.04rem;
        }

        .sponsor-logo {
            max-height: 0.8rem;
            max-width: 4rem;
            object-fit: contain;
        }

        .sponsor-logo-placeholder {
            font-size: 0.48rem;
            font-weight: 700;
            color: #5C3D2E;
            letter-spacing: 0.02rem;
        }

        .footer-qr {
            grid-column: 2;
            grid-row: 1 / -1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.2rem;
        }

        .footer-qr svg {
            width: 4rem;
            height: 4rem;
        }

        .footer-qr .qr-label {
            font-size: .4rem;
            font-weight: 500;
            color: #9A8577;
        }

        .no-events {
            grid-column: 1 / -1;
            font-size: 0.64rem;
            font-weight: 500;
            color: #9A8577;
            padding-top: 1rem;
            text-align: center;
        }

        .footer {
            display: grid;
            grid-template-columns: 1fr auto;
            grid-template-rows: auto auto;
            font-size: .5rem;
            gap: 0 1rem;
        }

        .contact {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
        }

        /* Monochrome variant */
        .mono body {
            color: #222;
        }

        .mono .org-name {
            color: #222;
        }

        .mono .month-label {
            color: #222;
        }

        .mono .header-rule {
            background: #222;
        }

        .mono .vertical-title {
            color: #222;
        }

        .mono .date-square:nth-of-type(4n+1),
        .mono .date-square:nth-of-type(4n+2),
        .mono .date-square:nth-of-type(4n+3),
        .mono .date-square:nth-of-type(4n+4) {
            background: #222;
        }

        .mono .time-pill {
            border-color: #222;
        }

        .mono .time-pill span {
            color: #222;
        }

        .mono .band-box {
            border-color: #222;
        }

        .mono .event-title {
            color: #222;
        }

        .mono .event-performers {
            color: #444;
        }

        .mono .free-badge {
            color: #222;
            border-color: #222;
        }

        .mono .footer-note {
            color: #444;
        }

        .mono .footer-info-left,
        .mono .footer-info-right {
            color: #222;
        }

        .mono .footer-bottom {
            border-top-color: #222;
        }

        .mono .sponsor-label {
            color: #666;
        }

        .mono .sponsor-logo-placeholder {
            color: #222;
        }

        .mono .footer-qr .qr-label {
            color: #666;
        }

        .mono .footer-qr svg path {
            fill: #222;
        }

        .mono .no-events {
            color: #666;
        }

        .mono .logo {
            --speaker-case: #222;
            --speaker-front: #fff;
            --speaker-fill: #fff;
            --sound-lines: #222;
        }

        .mono .sponsor-logo {
            filter: grayscale(1);
        }

        /* Preview controls */
        .preview-controls {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #fff;
            border-radius: 8px;
            padding: 12px 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            font-family: 'Lexend', sans-serif;
            font-size: 14px;
            color: #333;
            z-index: 100;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .preview-controls label {
            cursor: pointer;
            user-select: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .preview-controls input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        @media print {
            .preview-controls {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="preview-controls">
        <label>
            <input type="checkbox" id="size-toggle" {{ $size === 'letter' ? 'checked' : '' }}>
            8.5 × 11
        </label>
        <label>
            <input type="checkbox" id="mono-toggle" {{ $mono ? 'checked' : '' }}>
            Mono
        </label>
    </div>

    <div class="poster">

        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <div class="logo">
                    <x-logo :soundLines="false" />
                </div>
                <div class="org-name">Corvallis Music<br>Collective</div>
            </div>
            <div class="month-label">{{ $monthLabel }}</div>
        </div>
        <hr class="header-rule">

        <!-- Main -->
        <div class="content">
            <div class="vertical-title">Upcoming Events</div>

            <div class="events">
                @forelse ($events as $event)
                    <div class="date-square" data-event-row="{{ $loop->index }}"
                        data-event-month="{{ $event->start_datetime->format('F Y') }}">
                        <span class="day-abbrev">{{ $event->start_datetime->format('D') }}</span>
                        <span class="date-num">{{ $event->start_datetime->format('n/d') }}</span>
                    </div>
                    <div class="time-pill" data-event-row="{{ $loop->index }}">
                        <span>{{ $event->start_datetime->format('g A') }}</span>
                    </div>
                    <div class="band-box" data-event-row="{{ $loop->index }}">
                        <div class="event-title-row">
                            <span class="event-title">{{ $event->title }}</span>
                            @if ($event->isFree())
                                <span class="free-badge">Free</span>
                            @endif
                        </div>
                        <span class="event-performers">
                            @switch($event->event_type)
                                @case('performance')
                                    {{ $event->performers->pluck('name')->join(' · ') ?: 'Live Music' }}
                                @break

                                @case('open_mic')
                                    Open stage for all musicians
                                @break

                                @case('workshop')
                                    Skill-building session
                                @break

                                @case('volunteer')
                                    Community volunteer event
                                @break

                                @case('meetup')
                                    Member hangout
                                @break

                                @default
                                    Live at CMC
                            @endswitch
                        </span>
                    </div>
                    @empty
                        <div class="no-events">No events scheduled yet.</div>
                    @endforelse
                </div>
            </div>

            <!-- Footer -->
            <div class='footer'>
                <div class="footer-note">Every Show • All Ages • No One Turned Away For Lack of Funds</div>

                <div class='footer-bottom'>
                    <div class="sponsor">
                        @if ($sponsor)
                            <span class="sponsor-label">Sponsored by</span>
                            @if ($sponsor->getFirstMediaUrl('logo'))
                                <img class="sponsor-logo" src="{{ $sponsor->getFirstMediaUrl('logo') }}"
                                    alt="{{ $sponsor->name }}">
                            @else
                                <span class="sponsor-logo-placeholder">{{ strtoupper($sponsor->name) }}</span>
                            @endif
                        @endif
                    </div>
                    <div class='contact'>
                        <div class="footer-info">
                            <div class='instagram'>@@corvmc <x-tabler-brand-instagram
                                    style="width: 1.4em; height: 1.4em; vertical-align: middle;" /></div>
                            <div class='website'>corvallismusic.org</div>
                            <div class="address">
                                6775 SW Philomath Blvd.<br>
                                Corvallis, OR 97330
                            </div>
                        </div>
                    </div>
                </div>

                <div class="footer-qr">
                    <span class="qr-label">corvmc.org/events</span>
                    <svg viewBox="0 0 25 25" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M0,0H1V1H0zM1,0H2V1H1zM2,0H3V1H2zM3,0H4V1H3zM4,0H5V1H4zM5,0H6V1H5zM6,0H7V1H6zM10,0H11V1H10zM12,0H13V1H12zM16,0H17V1H16zM18,0H19V1H18zM19,0H20V1H19zM20,0H21V1H20zM21,0H22V1H21zM22,0H23V1H22zM23,0H24V1H23zM24,0H25V1H24zM0,1H1V2H0zM6,1H7V2H6zM8,1H9V2H8zM10,1H11V2H10zM13,1H14V2H13zM14,1H15V2H14zM15,1H16V2H15zM18,1H19V2H18zM24,1H25V2H24zM0,2H1V3H0zM2,2H3V3H2zM3,2H4V3H3zM4,2H5V3H4zM6,2H7V3H6zM12,2H13V3H12zM14,2H15V3H14zM18,2H19V3H18zM20,2H21V3H20zM21,2H22V3H21zM22,2H23V3H22zM24,2H25V3H24zM0,3H1V4H0zM2,3H3V4H2zM3,3H4V4H3zM4,3H5V4H4zM6,3H7V4H6zM8,3H9V4H8zM9,3H10V4H9zM10,3H11V4H10zM12,3H13V4H12zM13,3H14V4H13zM15,3H16V4H15zM16,3H17V4H16zM18,3H19V4H18zM20,3H21V4H20zM21,3H22V4H21zM22,3H23V4H22zM24,3H25V4H24zM0,4H1V5H0zM2,4H3V5H2zM3,4H4V5H3zM4,4H5V5H4zM6,4H7V5H6zM9,4H10V5H9zM13,4H14V5H13zM16,4H17V5H16zM18,4H19V5H18zM20,4H21V5H20zM21,4H22V5H21zM22,4H23V5H22zM24,4H25V5H24zM0,5H1V6H0zM6,5H7V6H6zM8,5H9V6H8zM9,5H10V6H9zM11,5H12V6H11zM12,5H13V6H12zM13,5H14V6H13zM14,5H15V6H14zM15,5H16V6H15zM16,5H17V6H16zM18,5H19V6H18zM24,5H25V6H24zM0,6H1V7H0zM1,6H2V7H1zM2,6H3V7H2zM3,6H4V7H3zM4,6H5V7H4zM5,6H6V7H5zM6,6H7V7H6zM8,6H9V7H8zM10,6H11V7H10zM12,6H13V7H12zM14,6H15V7H14zM16,6H17V7H16zM18,6H19V7H18zM19,6H20V7H19zM20,6H21V7H20zM21,6H22V7H21zM22,6H23V7H22zM23,6H24V7H23zM24,6H25V7H24zM9,7H10V8H9zM10,7H11V8H10zM11,7H12V8H11zM14,7H15V8H14zM16,7H17V8H16zM0,8H1V9H0zM1,8H2V9H1zM2,8H3V9H2zM3,8H4V9H3zM4,8H5V9H4zM6,8H7V9H6zM7,8H8V9H7zM8,8H9V9H8zM9,8H10V9H9zM11,8H12V9H11zM13,8H14V9H13zM14,8H15V9H14zM17,8H18V9H17zM19,8H20V9H19zM21,8H22V9H21zM23,8H24V9H23zM0,9H1V10H0zM1,9H2V10H1zM3,9H4V10H3zM4,9H5V10H4zM5,9H6V10H5zM7,9H8V10H7zM8,9H9V10H8zM10,9H11V10H10zM12,9H13V10H12zM16,9H17V10H16zM19,9H20V10H19zM23,9H24V10H23zM0,10H1V11H0zM1,10H2V11H1zM2,10H3V11H2zM3,10H4V11H3zM6,10H7V11H6zM8,10H9V11H8zM10,10H11V11H10zM14,10H15V11H14zM15,10H16V11H15zM17,10H18V11H17zM19,10H20V11H19zM20,10H21V11H20zM21,10H22V11H21zM23,10H24V11H23zM24,10H25V11H24zM1,11H2V12H1zM3,11H4V12H3zM5,11H6V12H5zM12,11H13V12H12zM14,11H15V12H14zM16,11H17V12H16zM17,11H18V12H17zM24,11H25V12H24zM2,12H3V13H2zM3,12H4V13H3zM6,12H7V13H6zM9,12H10V13H9zM10,12H11V13H10zM12,12H13V13H12zM13,12H14V13H13zM14,12H15V13H14zM17,12H18V13H17zM18,12H19V13H18zM20,12H21V13H20zM22,12H23V13H22zM23,12H24V13H23zM24,12H25V13H24zM0,13H1V14H0zM8,13H9V14H8zM14,13H15V14H14zM17,13H18V14H17zM19,13H20V14H19zM21,13H22V14H21zM23,13H24V14H23zM0,14H1V15H0zM2,14H3V15H2zM3,14H4V15H3zM6,14H7V15H6zM7,14H8V15H7zM8,14H9V15H8zM9,14H10V15H9zM11,14H12V15H11zM12,14H13V15H12zM13,14H14V15H13zM14,14H15V15H14zM15,14H16V15H15zM16,14H17V15H16zM18,14H19V15H18zM19,14H20V15H19zM20,14H21V15H20zM21,14H22V15H21zM23,14H24V15H23zM24,14H25V15H24zM0,15H1V16H0zM3,15H4V16H3zM5,15H6V16H5zM8,15H9V16H8zM11,15H12V16H11zM19,15H20V16H19zM20,15H21V16H20zM24,15H25V16H24zM0,16H1V17H0zM2,16H3V17H2zM3,16H4V17H3zM5,16H6V17H5zM6,16H7V17H6zM7,16H8V17H7zM8,16H9V17H8zM11,16H12V17H11zM12,16H13V17H12zM13,16H14V17H13zM14,16H15V17H14zM15,16H16V17H15zM16,16H17V17H16zM17,16H18V17H17zM18,16H19V17H18zM19,16H20V17H19zM20,16H21V17H20zM22,16H23V17H22zM8,17H9V18H8zM9,17H10V18H9zM12,17H13V18H12zM15,17H16V18H15zM16,17H17V18H16zM20,17H21V18H20zM21,17H22V18H21zM0,18H1V19H0zM1,18H2V19H1zM2,18H3V19H2zM3,18H4V19H3zM4,18H5V19H4zM5,18H6V19H5zM6,18H7V19H6zM8,18H9V19H8zM10,18H11V19H10zM14,18H15V19H14zM16,18H17V19H16zM18,18H19V19H18zM20,18H21V19H20zM22,18H23V19H22zM23,18H24V19H23zM24,18H25V19H24zM0,19H1V20H0zM6,19H7V20H6zM9,19H10V20H9zM11,19H12V20H11zM12,19H13V20H12zM14,19H15V20H14zM16,19H17V20H16zM20,19H21V20H20zM21,19H22V20H21zM23,19H24V20H23zM24,19H25V20H24zM0,20H1V21H0zM2,20H3V21H2zM3,20H4V21H3zM4,20H5V21H4zM6,20H7V21H6zM8,20H9V21H8zM10,20H11V21H10zM13,20H14V21H13zM14,20H15V21H14zM15,20H16V21H15zM16,20H17V21H16zM17,20H18V21H17zM18,20H19V21H18zM19,20H20V21H19zM20,20H21V21H20zM22,20H23V21H22zM0,21H1V22H0zM2,21H3V22H2zM3,21H4V22H3zM4,21H5V22H4zM6,21H7V22H6zM8,21H9V22H8zM9,21H10V22H9zM11,21H12V22H11zM13,21H14V22H13zM17,21H18V22H17zM18,21H19V22H18zM20,21H22V22H20zM22,21H23V22H22zM23,21H24V22H23zM24,21H25V22H24zM0,22H1V23H0zM2,22H3V23H2zM3,22H4V23H3zM4,22H5V23H4zM6,22H7V23H6zM8,22H9V23H8zM9,22H10V23H9zM12,22H13V23H12zM13,22H14V23H13zM14,22H15V23H14zM16,22H17V23H16zM17,22H18V23H17zM21,22H22V23H21zM22,22H23V23H22zM24,22H25V23H24zM0,23H1V24H0zM6,23H7V24H6zM8,23H9V24H8zM15,23H16V24H15zM16,23H17V24H16zM17,23H18V24H17zM18,23H19V24H18zM19,23H20V24H19zM20,23H21V24H20zM21,23H22V24H21zM24,23H25V24H24zM0,24H1V25H0zM1,24H2V25H1zM2,24H3V25H2zM3,24H4V25H3zM4,24H5V25H4zM5,24H6V25H5zM6,24H7V25H6zM8,24H9V25H8zM10,24H11V25H10zM13,24H14V25H13zM14,24H15V25H14zM19,24H20V25H19zM20,24H21V25H20zM21,24H22V25H21zM22,24H23V25H22zM23,24H24V25H23zM24,24H25V25H24z"
                            fill="#5C3D2E" fill-rule="nonzero" stroke="none" />
                    </svg>
                    <span>For tickets and<br>up-to-date info</span>

                </div>

            </div>
        </div>

        <script>
            const pageStyle = document.createElement('style');
            document.head.appendChild(pageStyle);

            function syncUrl() {
                const params = new URLSearchParams(window.location.search);
                params.set('size', document.getElementById('size-toggle').checked ? 'letter' : 'tabloid');
                if (document.getElementById('mono-toggle').checked) {
                    params.set('mono', '1');
                } else {
                    params.delete('mono');
                }
                history.replaceState(null, '', window.location.pathname + '?' + params.toString());
            }

            document.getElementById('size-toggle').addEventListener('change', function() {
                const html = document.documentElement;
                if (this.checked) {
                    html.classList.remove('tabloid');
                    html.classList.add('letter');
                    pageStyle.textContent = '@page { size: 8.5in 11in; margin: 0; }';
                } else {
                    html.classList.remove('letter');
                    html.classList.add('tabloid');
                    pageStyle.textContent = '@page { size: 11in 17in; margin: 0; }';
                }
                autoFit();
                syncUrl();
            });

            document.getElementById('mono-toggle').addEventListener('change', function() {
                const html = document.documentElement;
                if (this.checked) {
                    html.classList.add('mono');
                } else {
                    html.classList.remove('mono');
                }
                syncUrl();
            });

            function autoFit() {
                const poster = document.querySelector('.poster');

                // Read the target height from CSS before we override it
                const fixedHeight = parseInt(getComputedStyle(poster).height, 10);

                const rows = [...new Set(
                    [...document.querySelectorAll('[data-event-row]')]
                    .map(el => el.dataset.eventRow)
                )];

                // Show all rows
                document.querySelectorAll('[data-event-row]').forEach(el => {
                    el.style.display = '';
                });

                // Measure without height constraint
                poster.style.height = 'auto';

                for (let i = rows.length - 1; i >= 0; i--) {
                    if (poster.scrollHeight <= fixedHeight) break;

                    document.querySelectorAll(`[data-event-row="${rows[i]}"]`).forEach(el => {
                        el.style.display = 'none';
                    });
                }

                poster.style.height = '';

                // Update month label to reflect only visible events
                const visibleMonths = [...new Set(
                    [...document.querySelectorAll('.date-square[data-event-month]')]
                    .filter(el => el.style.display !== 'none')
                    .map(el => el.dataset.eventMonth)
                )];

                const monthLabel = document.querySelector('.month-label');
                if (visibleMonths.length === 0) {
                    monthLabel.textContent = @json(strtolower($monthLabel));
                } else if (visibleMonths.length === 1) {
                    monthLabel.textContent = visibleMonths[0].toLowerCase();
                } else {
                    const first = visibleMonths[0].split(' ');
                    const last = visibleMonths[visibleMonths.length - 1].split(' ');
                    if (first[1] === last[1]) {
                        monthLabel.textContent = first[0].toLowerCase() + ' – ' + last[0].toLowerCase() + ' ' + last[1];
                    } else {
                        monthLabel.textContent = visibleMonths[0].toLowerCase() + ' – ' + visibleMonths[visibleMonths.length -
                            1].toLowerCase();
                    }
                }
            }

            autoFit();
            syncUrl();
        </script>
    </body>

    </html>
