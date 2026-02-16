<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <script>
        // Initialize theme from localStorage with auto mode support
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'auto';
            let isDark;

            if (savedTheme === 'auto') {
                isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            } else {
                isDark = savedTheme === 'dark';
            }

            // Force theme setting to override DaisyUI auto-detection
            const theme = isDark ? 'corvmc-dark' : 'corvmc';
            document.documentElement.setAttribute('data-theme', theme);
            document.documentElement.style.setProperty('color-scheme', isDark ? 'dark' : 'light');
            if (isDark) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }

            // Listen for system theme changes when in auto mode
            if (savedTheme === 'auto') {
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
                    const theme = e.matches ? 'corvmc-dark' : 'corvmc';
                    document.documentElement.setAttribute('data-theme', theme);
                    document.documentElement.style.setProperty('color-scheme', e.matches ? 'dark' : 'light');
                    document.documentElement.classList.toggle('dark', e.matches);
                });
            }
        })();
    </script>

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $organizationSettings = app(\App\Settings\OrganizationSettings::class);
            $metaDescription =
                $organizationSettings->description ?:
                'Corvallis Music Collective (CMC) supports local musicians with affordable practice space, events, and community connections. Join Oregon\'s premier music collective.';
        @endphp

        <title>{{ $title ?? $organizationSettings->name }}</title>

        <!-- Meta Tags -->
        <meta name="description" content="{{ $metaDescription }}">
        <meta name="keywords"
            content="Corvallis music, Oregon musicians, practice space, live music, music community, band rehearsal, music events">
        <meta name="author" content="{{ $organizationSettings->name }}">

        <!-- Favicon -->
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="icon" type="image/x-unicon" href="/favicon.ico">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700&display=swap"
            rel="stylesheet">

        <script src="https://zeffy-scripts.s3.ca-central-1.amazonaws.com/embed-form-script.min.js"></script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
        @filamentStyles
    </head>

    @php
        $links = [
            ['label' => 'Events', 'route' => 'events.index', 'pattern' => 'events.*', 'icon' => 'tabler-calendar'],
            ['label' => 'Directory', 'route' => 'directory', 'pattern' => 'directory', 'icon' => 'tabler-users'],
            ['label' => 'Resources', 'route' => 'local-resources', 'pattern' => 'local-resources', 'icon' => 'tabler-list'],
            ['label' => 'Programs', 'route' => 'programs', 'pattern' => 'programs', 'icon' => 'tabler-apps'],
        ];
    @endphp

    <body class="min-h-screen bg-base-100">
        <!-- Development Banner -->
        @livewire('development-banner')

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
                                <x-icon name="tabler-menu-2" class="size-6" />
                            </label>

                            <!-- Center: Small Logo + Title -->
                            <a href="{{ route('home') }}" class="flex items-center hover:opacity-80 transition-opacity">
                                <x-logo class="h-8" :soundLines="false" />
                                <div class="text-left leading-tight ml-2">
                                    <div class="text-primary font-bold text-sm">Corvallis</div>
                                    <div class="text-secondary font-bold text-xs">Music Collective</div>
                                </div>
                            </a>

                            <!-- Right: Dashboard only on mobile (theme selector in sidebar) -->
                            @auth
                                <a href="/member" class="btn btn-ghost btn-square" title="Dashboard">
                                    <x-tabler-layout-dashboard-filled class='size-6' />
                                </a>
                            @else
                                <a href="/member/login" class="btn btn-ghost btn-square text-xs">
                                    <x-tabler-login class='size-6' />
                                </a>
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
                    <div class="flex items-center justify-between mb-8 px-2">
                        <div class="flex items-center gap-3">
                            <x-logo class="h-10" :soundLines="false" />
                            <div class="text-left leading-tight">
                                <div class="text-primary font-bold text-lg">Corvallis</div>
                                <div class="text-secondary font-bold text-sm">Music Collective</div>
                            </div>
                        </div>
                        <x-theme-selector />
                    </div>

                    <!-- Navigation Menu -->
                    <ul class="menu menu-lg w-full">
                        @foreach ($links as $link)
                            <li>
                                <a href="{{ route($link['route']) }}"
                                    class="{{ request()->routeIs($link['pattern']) ? 'menu-active' : '' }}">
                                    <x-filament::icon icon="{{ $link['icon'] }}" class='size-5' />
                                    {{ $link['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('contribute') }}" class="btn btn-primary btn-outline mt-4">
                        <x-tabler-heart-handshake class='size-5' />
                        Contribute
                    </a>
                    <!-- Sidebar Footer -->
                    <div class="mt-auto pt-8">
                        <div class="divider"></div>
                        @guest
                            <a href='/member/login' class='btn btn-outline btn-block'>
                                <x-tabler-login class='size-6' />
                                Login
                            </a>
                        @endguest
                        @auth
                            <div class='flex gap-2 items-center justify-center'>
                                <img src={{ Auth::user()->getFilamentAvatarUrl() }} alt="User Avatar"
                                    class="size-10 rounded-full" />
                                <div class='font-medium text-sm grow'>
                                    <p>{{ Auth::user()->name }}</p>
                                    <p>{{ Auth::user()->email }}</p>
                                </div>
                                <a class='btn btn-ghost btn-square mt-2 row-span-2' href='/member'>
                                    <x-tabler-layout-dashboard-filled class='size-5' />
                                </a>
                            </div>
                        @endauth
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
                    <a href="{{ route('home') }}"
                        class="flex flex-col items-start hover:opacity-80 transition-opacity">
                        <div class="text-primary font-bold text-3xl">Corvallis Music Collective</div>
                    </a>
                </div>

                <!-- Action Buttons - first row, third column -->
                <div class="flex items-center gap-2" style="grid-row: 2; grid-column: 3;">
                    @auth
                        <a href="/member" class="btn btn-ghost" title="Dashboard">
                            <x-tabler-layout-dashboard-filled class='size-5' />
                            <span class="ml-1">Dashboard</span>
                        </a>
                    @else
                        <a href="/member/login" class="btn btn-ghost">
                            <x-tabler-login class='size-5' />
                            <span class="ml-1">Login</span>
                        </a>
                    @endauth
                    <x-theme-selector />
                </div>

                <!-- Navigation - second row, spans columns 2-3 -->
                <div class="flex items-center" style="grid-row: 2; grid-column: 2;">

                    <ul class="menu menu-horizontal px-1 w-full -ml-4 items-center">
                        @foreach ($links as $link)
                            <li>
                                <a href="{{ route($link['route']) }}"
                                    class="{{ request()->routeIs($link['pattern']) ? 'active' : '' }}">
                                    {{ $link['label'] }}
                                </a>
                            </li>
                        @endforeach
                        <li><a href="{{ route('contribute') }}" class="btn btn-primary btn-outline ml-2">
                                <x-tabler-heart-handshake class='size-5' />
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
            class="footer footer-center bg-base-200 text-base-content p-10 flex justify-between flex-wrap items-center">
            <div class="grid grid-flow-col gap-4 mx-auto">
                @foreach ($footerLinks as $link)
                    <a href="{{ $link['url'] }}" class="link link-hover">{{ $link['label'] }}</a>
                @endforeach
            </div>
            <div class='mx-auto'>
                <div class="grid grid-flow-col gap-4">
                    @foreach ($socialLinks as $social)
                        <a href="{{ $social['url'] }}" class="text-2xl hover:text-primary">
                            <x-icon name="{{ $social['icon']  }}" class="w-6 h-6" />
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

        @livewireScripts
        @filamentScripts
    </body>

</html>
