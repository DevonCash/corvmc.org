<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="corvmc">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Corvallis Music Collective' }}</title>

    <!-- Meta Tags -->
    <meta name="description"
        content="Corvallis Music Collective (CMC) supports local musicians with affordable practice space, events, and community connections. Join Oregon's premier music collective.">
    <meta name="keywords"
        content="Corvallis music, Oregon musicians, practice space, live music, music community, band rehearsal, music events">
    <meta name="author" content="Corvallis Music Collective">

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/x-unicon" href="/favicon.ico">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://zeffy-scripts.s3.ca-central-1.amazonaws.com/embed-form-script.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-base-100">
    <!-- Mobile Drawer -->
    <div class="drawer lg:hidden">
        <input id="mobile-drawer" type="checkbox" class="drawer-toggle" />
        <div class="drawer-content flex flex-col">

            <!-- Mobile Header (single line) -->
            <div class="bg-base-100 border-b border-base-200">
                <div class=" px-4 py-3">
                    <div class="flex items-center justify-between">
                        <!-- Mobile menu button -->
                        <label for="mobile-drawer" class="btn btn-ghost btn-square drawer-button">
                            <x-unicon name="tabler:menu-2" class="size-6" />
                        </label>

                        <!-- Center: Small Logo + Title -->
                        <a href="{{ route('home') }}" class="flex items-center hover:opacity-80 transition-opacity">
                            <x-logo class="h-8" :soundLines="false" />
                            <div class="text-left leading-tight ml-2">
                                <div class="text-primary font-bold text-sm">Corvallis</div>
                                <div class="text-secondary font-bold text-xs">Music Collective</div>
                            </div>
                        </a>

                        <!-- Right: Dashboard -->
                        @auth
                            <a href="/member" class="btn btn-ghost btn-square" title="Dashboard">
                                <x-unicon name="tabler:layout-dashboard-filled" class="size-6" />
                            </a>
                        @else
                            <a href="/member/login" class="btn btn-ghost btn-square text-xs">Login</a>
                        @endauth
                    </div>
                </div>
                <!-- Header Stripe -->
                <div class="corvmc-header-stripes h-2"></div>

            </div>


        </div>

        <!-- Sidebar -->
        <div class="drawer-side">
            <label for="mobile-drawer" aria-label="close sidebar" class="drawer-overlay"></label>
            <aside class="bg-base-100 text-base-content min-h-full w-72 p-4 flex flex-col">
                <!-- Sidebar Header -->
                <div class="flex items-center gap-3 mb-8 px-2">
                    <x-logo class="h-10" :soundLines="false" />
                    <div class="text-left leading-tight">
                        <div class="text-primary font-bold text-lg">Corvallis</div>
                        <div class="text-secondary font-bold text-sm">Music Collective</div>
                    </div>
                </div>

                <!-- Navigation Menu -->
                <ul class="menu menu-lg w-full grow">
                    <li><a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active' : '' }}">
                            <x-unicon name="tabler:home" class="w-5 h-5" />
                            Home
                        </a></li>
                    <li><a href="{{ route('about') }}" class="{{ request()->routeIs('about') ? 'active' : '' }}">
                            <x-unicon name="tabler:info-circle" class="w-5 h-5" />
                            About Us
                        </a></li>
                    <li><a href="{{ route('events.index') }}"
                            class="{{ request()->routeIs('events.*') ? 'active' : '' }}">
                            <x-unicon name="tabler:calendar" class="w-5 h-5" />
                            Events
                        </a></li>
                    <li><a href="{{ route('members.index') }}"
                            class="{{ request()->routeIs('members.*') ? 'active' : '' }}">
                            <x-unicon name="tabler:users" class="w-5 h-5" />
                            Members
                        </a></li>
                    <li><a href="{{ route('bands.index') }}"
                            class="{{ request()->routeIs('bands.*') ? 'active' : '' }}">
                            <x-unicon name="tabler:music" class="w-5 h-5" />
                            Bands
                        </a></li>
                    <li><a href="{{ route('programs') }}"
                            class="{{ request()->routeIs('programs') ? 'active' : '' }}">
                            <x-unicon name="tabler:apps" class="w-5 h-5" />
                            Programs
                        </a></li>
                    <li><a href="{{ route('contact') }}" class="{{ request()->routeIs('contact') ? 'active' : '' }}">
                            <x-unicon name="tabler:mail" class="w-5 h-5" />
                            Contact
                        </a></li>
                    <li><a href="{{ route('contribute') }}"
                            class="{{ request()->routeIs('contribute') ? 'active text-primary' : 'text-primary outline outline-1 outline-primary ' }} mt-4">
                            <x-unicon name="tabler:heart-handshake" class="w-5 h-5" />
                            Contribute
                        </a></li>
                </ul>

                <!-- Sidebar Footer -->
                <div class="mt-auto pt-8">
                    <div class="divider"></div>
                </div>
            </aside>
        </div>
    </div>

    <!-- Desktop Header (two rows) -->
    <div class="bg-base-100 border-b border-base-200 hidden lg:block">
        <div class="container mx-auto p-4 grid gap-x-4"
            style="grid-template-columns: auto 1fr auto; grid-template-rows: auto auto;">
            <!-- Logo - spans both rows -->
            <a href="{{ route('home') }}" class="flex items-center hover:opacity-80 transition-opacity"
                style="grid-row: 1 / 3;">
                <x-logo class="h-24" :soundLines="true" />
            </a>

            <!-- Title - first row, second column -->
            <div class="text-left flex items-center mt-1" style="grid-row: 1; grid-column: 2;">
                <a href="{{ route('home') }}" class="flex flex-col items-start hover:opacity-80 transition-opacity">
                    <div class="text-primary font-bold text-3xl">Corvallis Music Collective</div>
                </a>
            </div>

            <!-- Action Buttons - first row, third column -->
            <div class="flex items-center gap-2" style="grid-row: 2; grid-column: 3;">
                @auth
                    <a href="/member" class="btn btn-ghost" title="Dashboard">
                        <x-unicon name="tabler:layout-dashboard-filled" class="w-5 h-5" />
                        <span class="ml-1">Dashboard</span>
                    </a>
                @else
                    <a href="/member/login" class="btn btn-ghost">Login</a>
                @endauth
            </div>

            <!-- Navigation - second row, spans columns 2-3 -->
            <div class="flex items-center" style="grid-row: 2; grid-column: 2;">
                <ul class="menu menu-horizontal px-1 w-full -ml-4">
                    <li><a href="{{ route('about') }}"
                            class="{{ request()->routeIs('about') ? 'active' : '' }}">About Us</a></li>
                    <li><a href="{{ route('events.index') }}"
                            class="{{ request()->routeIs('events.*') ? 'active' : '' }}">Events</a></li>
                    <li><a href="{{ route('members.index') }}"
                            class="{{ request()->routeIs('members.*') ? 'active' : '' }}">Members</a></li>
                    <li><a href="{{ route('bands.index') }}"
                            class="{{ request()->routeIs('bands.*') ? 'active' : '' }}">Bands</a></li>
                    <li><a href="{{ route('programs') }}"
                            class="{{ request()->routeIs('programs') ? 'active' : '' }}">Programs</a></li>
                    <li><a href="{{ route('contribute') }}"
                            class="{{ request()->routeIs('contribute') ? 'active text-primary ml-2' : 'text-primary outline outline-1 outline-primary ml-2' }}">
                            <x-unicon name="tabler:heart-handshake" class="w-5 h-5" />
                            Contribute</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Header Stripe (Desktop only) -->
    <div class="corvmc-header-stripes h-3 hidden lg:block"></div>


    <!-- Main Content -->
    <main>
        {{ $slot }}
    </main>

    <!-- Footer -->
    @php
        $footerSettings = app(\App\Settings\FooterSettings::class);
        $footerLinks = $footerSettings->getLinks();
        $socialLinks = $footerSettings->getSocialLinks();
    @endphp
    <footer
        class="footer footer-center bg-base-200 text-base-content p-10 mt-20 flex justify-between flex-wrap items-center">
        <div class="grid grid-flow-col gap-4 mx-auto">
            @foreach ($footerLinks as $link)
                <a href="{{ $link['url'] }}" class="link link-hover">{{ $link['label'] }}</a>
            @endforeach
        </div>
        <div class='mx-auto'>
            <div class="grid grid-flow-col gap-4">
                @foreach ($socialLinks as $social)
                    <a href="{{ $social['url'] }}" class="text-2xl hover:text-primary">
                        <x-unicon name="{{ $social['icon'] }}" class="w-6 h-6" />
                    </a>
                @endforeach
            </div>
        </div>
        <div class='mx-auto'>
            <p>Copyright © {{ date('Y') }} Corvallis Music Collective. All rights reserved.</p>
            <p class="text-sm opacity-70">
                {{ app(\App\Settings\OrganizationSettings::class)->getFullNonprofitDescription() }}</p>
        </div>
    </footer>
</body>

</html>
