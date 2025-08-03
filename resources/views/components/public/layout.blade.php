<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="corvmc">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Corvallis Music Collective' }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-base-100">
    <!-- Mobile Drawer -->
    <div class="drawer lg:hidden">
        <input id="mobile-drawer" type="checkbox" class="drawer-toggle" />
        <div class="drawer-content flex flex-col">
            <!-- Header Stripe -->
            <div class="corvmc-header-stripes h-2"></div>

            <!-- Mobile Header (single line) -->
            <div class="bg-base-100 border-b border-base-200">
                <div class="container mx-auto px-4 py-3">
                    <div class="flex items-center justify-between">
                        <!-- Mobile menu button -->
                        <label for="mobile-drawer" class="btn btn-ghost btn-sm btn-square drawer-button">
                            <x-icon name="tabler:menu" class="w-5 h-5" />
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
                            <a href="/member" class="btn btn-ghost btn-sm btn-square" title="Dashboard">
                                <x-icon name="tabler:dashboard" class="w-4 h-4" />
                            </a>
                        @else
                            <a href="/member/login" class="btn btn-ghost btn-sm btn-square text-xs">Login</a>
                        @endauth
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <main>
                {{ $slot }}
            </main>

            <!-- Footer -->
            <footer class="footer footer-center bg-base-200 text-base-content p-10 mt-20 block space-y-8 sm:grid">
                <div class="flex grid-flow-col gap-4">
                    <a href="{{ route('about') }}" class="link link-hover whitespace-nowrap">About</a>
                    <a href="{{ route('contact') }}" class="link link-hover whitespace-nowrap">Contact</a>
                    <a href="{{ route('volunteer') }}" class="link link-hover whitespace-nowrap">Volunteer</a>
                    <a href="{{ route('support') }}" class="link link-hover whitespace-nowrap">Support Us</a>
                </div>
                <div>
                    <div class="grid grid-flow-col gap-4">
                        <a href="#" class="text-2xl hover:text-primary">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/>
                            </svg>
                        </a>
                        <a href="#" class="text-2xl hover:text-primary">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M22.46 6c-.77.35-1.6.58-2.46.69.88-.53 1.56-1.37 1.88-2.38-.83.5-1.75.85-2.72 1.05C18.37 4.5 17.26 4 16 4c-2.35 0-4.27 1.92-4.27 4.29 0 .34.04.67.11.98C8.28 9.09 5.11 7.38 3 4.79c-.37.63-.58 1.37-.58 2.15 0 1.49.75 2.81 1.91 3.56-.71 0-1.37-.2-1.95-.5v.03c0 2.08 1.48 3.82 3.44 4.21a4.22 4.22 0 0 1-1.93.07 4.28 4.28 0 0 0 4 2.98 8.521 8.521 0 0 1-5.33 1.84c-.34 0-.68-.02-1.02-.06C3.44 20.29 5.7 21 8.12 21 16 21 20.33 14.46 20.33 8.79c0-.19 0-.37-.01-.56.84-.6 1.56-1.36 2.14-2.23z"/>
                            </svg>
                        </a>
                        <a href="#" class="text-2xl hover:text-primary">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.174-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.165 1.775 2.165 2.128 0 3.768-2.245 3.768-5.487 0-2.861-2.063-4.869-5.008-4.869-3.41 0-5.409 2.562-5.409 5.199 0 1.033.394 2.143.889 2.741.099.12.112.225.085.347-.09.375-.293 1.199-.334 1.363-.053.225-.172.271-.402.165-1.495-.69-2.433-2.878-2.433-4.646 0-3.776 2.748-7.252 7.92-7.252 4.158 0 7.392 2.967 7.392 6.923 0 4.135-2.607 7.462-6.233 7.462-1.214 0-2.357-.629-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24.009 12.017 24.009c6.624 0 11.99-5.367 11.99-11.988C24.007 5.367 18.641.001.012.001z"/>
                            </svg>
                        </a>
                        <a href="#" class="text-2xl hover:text-primary">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                            </svg>
                        </a>
                    </div>
                </div>
                <div>
                    <p>Copyright © {{ date('Y') }} Corvallis Music Collective. All rights reserved.</p>
                    <p class="text-sm opacity-70">501(c)(3) Nonprofit Organization • EIN: XX-XXXXXXX</p>
                </div>
            </footer>
        </div>

        <!-- Sidebar -->
        <div class="drawer-side">
            <label for="mobile-drawer" aria-label="close sidebar" class="drawer-overlay"></label>
            <aside class="bg-base-100 text-base-content min-h-full w-72 p-4">
                <!-- Sidebar Header -->
                <div class="flex items-center gap-3 mb-8 px-2">
                    <x-logo class="h-10" :soundLines="false" />
                    <div class="text-left leading-tight">
                        <div class="text-primary font-bold text-lg">Corvallis</div>
                        <div class="text-secondary font-bold text-sm">Music Collective</div>
                    </div>
                </div>

                <!-- Navigation Menu -->
                <ul class="menu menu-lg w-full">
                    <li><a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active' : '' }}">
                        <x-icon name="tabler:home" class="w-5 h-5" />
                        Home
                    </a></li>
                    <li><a href="{{ route('about') }}" class="{{ request()->routeIs('about') ? 'active' : '' }}">
                        <x-icon name="tabler:info-circle" class="w-5 h-5" />
                        About Us
                    </a></li>
                    <li><a href="{{ route('events.index') }}" class="{{ request()->routeIs('events.*') ? 'active' : '' }}">
                        <x-icon name="tabler:calendar" class="w-5 h-5" />
                        Events
                    </a></li>
                    <li><a href="{{ route('members.index') }}" class="{{ request()->routeIs('members.*') ? 'active' : '' }}">
                        <x-icon name="tabler:users" class="w-5 h-5" />
                        Members
                    </a></li>
                    <li><a href="{{ route('bands.index') }}" class="{{ request()->routeIs('bands.*') ? 'active' : '' }}">
                        <x-icon name="tabler:music" class="w-5 h-5" />
                        Bands
                    </a></li>
                    <li><a href="{{ route('practice-space') }}" class="{{ request()->routeIs('practice-space') ? 'active' : '' }}">
                        <x-icon name="tabler:building" class="w-5 h-5" />
                        Practice Space
                    </a></li>
                    <li><a href="{{ route('volunteer') }}" class="{{ request()->routeIs('volunteer') ? 'active' : '' }}">
                        <x-icon name="tabler:heart" class="w-5 h-5" />
                        Volunteer
                    </a></li>
                    <li><a href="{{ route('contact') }}" class="{{ request()->routeIs('contact') ? 'active' : '' }}">
                        <x-icon name="tabler:mail" class="w-5 h-5" />
                        Contact
                    </a></li>
                </ul>

                <!-- Sidebar Footer -->
                <div class="mt-auto pt-8">
                    <div class="divider"></div>
                    <a href="{{ route('support') }}" class="btn btn-primary w-full">
                        <x-icon name="tabler:heart" class="w-5 h-5" />
                        Support CMC
                    </a>
                </div>
            </aside>
        </div>
    </div>

    <!-- Header Stripe (Desktop only) -->
    <div class="corvmc-header-stripes h-2 hidden lg:block"></div>

    <!-- Desktop Header (two rows) -->
    <div class="bg-base-100 border-b border-base-200 hidden lg:block">
        <div class="container mx-auto p-4 grid gap-x-4" style="grid-template-columns: auto 1fr auto; grid-template-rows: auto auto;">
            <!-- Logo - spans both rows -->
            <a href="{{ route('home') }}" class="flex items-center hover:opacity-80 transition-opacity" style="grid-row: 1 / 3;">
                <x-logo class="h-24" :soundLines="true" />
            </a>
            
            <!-- Title - first row, second column -->
            <div class="text-left flex items-center" style="grid-row: 1; grid-column: 2;">
                <a href="{{ route('home') }}" class="flex flex-col items-start hover:opacity-80 transition-opacity">
                    <div class="text-primary font-bold text-3xl">Corvallis Music Collective</div>
                </a>
            </div>

            <!-- Action Buttons - first row, third column -->
            <div class="flex items-center gap-2" style="grid-row: 1; grid-column: 3;">
                @auth
                    <a href="/member" class="btn btn-ghost btn-sm" title="Dashboard">
                        <x-icon name="tabler:dashboard" class="w-5 h-5" />
                        <span class="ml-1">Dashboard</span>
                    </a>
                @else
                    <a href="/member/login" class="btn btn-ghost btn-sm">Login</a>
                @endauth
            </div>

            <!-- Navigation - second row, spans columns 2-3 -->
            <div class="flex items-center" style="grid-row: 2; grid-column: 2 / 4;">
                <ul class="menu menu-horizontal px-1 w-full">
                    <li><a href="{{ route('about') }}" class="{{ request()->routeIs('about') ? 'active' : '' }}">About Us</a></li>
                    <li><a href="{{ route('events.index') }}" class="{{ request()->routeIs('events.*') ? 'active' : '' }}">Events</a></li>
                    <li><a href="{{ route('members.index') }}" class="{{ request()->routeIs('members.*') ? 'active' : '' }}">Members</a></li>
                    <li><a href="{{ route('bands.index') }}" class="{{ request()->routeIs('bands.*') ? 'active' : '' }}">Bands</a></li>
                    <li><a href="{{ route('practice-space') }}" class="{{ request()->routeIs('practice-space') ? 'active' : '' }}">Practice Space</a></li>
                    <li><a href="{{ route('volunteer') }}" class="{{ request()->routeIs('volunteer') ? 'active' : '' }}">Volunteer</a></li>
                    <li class='ml-auto'>
                        <a href="{{ route('support') }}" class="btn btn-primary btn-sm {{ request()->routeIs('support') ? 'active' : '' }}">Contribute</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Main Content (Desktop only) -->
    <main class="hidden lg:block">
        {{ $slot }}
    </main>

    <!-- Footer (Desktop only) -->
    <footer class="footer footer-center bg-base-200 text-base-content p-10 mt-20 hidden lg:grid">
        <div class="grid grid-flow-col gap-4">
            <a href="{{ route('about') }}" class="link link-hover">About</a>
            <a href="{{ route('contact') }}" class="link link-hover">Contact</a>
            <a href="{{ route('volunteer') }}" class="link link-hover">Volunteer</a>
            <a href="{{ route('support') }}" class="link link-hover">Support Us</a>
        </div>
        <div>
            <div class="grid grid-flow-col gap-4">
                <a href="#" class="text-2xl hover:text-primary">
                    <x-icon name="tabler:brand-x" class="w-6 h-6" />
                </a>
                <a href="#" class="text-2xl hover:text-primary">
                    <x-icon name="tabler:brand-facebook" class="w-6 h-6" />
                </a>
                <a href="#" class="text-2xl hover:text-primary">
                    <x-icon name="tabler:brand-pinterest" class="w-6 h-6" />
                </a>
                <a href="#" class="text-2xl hover:text-primary">
                    <x-icon name="tabler:brand-instagram" class="w-6 h-6" />
                </a>
            </div>
        </div>
        <div>
            <p>Copyright © {{ date('Y') }} Corvallis Music Collective. All rights reserved.</p>
            <p class="text-sm opacity-70">501(c)(3) Nonprofit Organization • EIN: XX-XXXXXXX</p>
        </div>
    </footer>
</body>

</html>
