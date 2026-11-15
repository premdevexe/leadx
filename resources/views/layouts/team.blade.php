<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Team Dashboard') — {{ \App\Models\Setting::siteName() }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900 antialiased overflow-x-clip">
    <div class="relative flex min-h-screen w-full max-w-full overflow-x-clip">
        {{-- Mobile Overlay --}}
        <div id="sidebar-overlay"
             class="fixed inset-0 z-40 bg-neutral-900/50 backdrop-blur-xs transition-opacity duration-200 hidden md:hidden"
             aria-hidden="true"></div>

        {{-- Team Sidebar --}}
        <aside id="sidebar"
               class="fixed inset-y-0 left-0 z-50 flex h-full w-64 max-w-[85vw] flex-col border-r border-neutral-200 bg-white shadow-2xl transition-transform duration-200 ease-in-out -translate-x-full overflow-x-hidden overflow-y-auto md:sticky md:top-0 md:z-20 md:h-screen md:w-60 md:max-w-none md:shadow-none md:translate-x-0 md:transition-[width] md:duration-200"
               aria-label="Team Navigation">
            <div class="flex h-full w-full flex-col">
                <div class="flex h-14 shrink-0 items-center justify-between border-b border-neutral-100 px-4 md:border-b-0">
                    <div class="flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-neutral-900 text-sm font-semibold text-white">L</span>
                        <span class="font-semibold tracking-tight">{{ \App\Models\Setting::siteName() }}</span>
                    </div>
                    <button type="button" id="sidebar-close-btn" class="flex h-8 w-8 items-center justify-center rounded-lg text-neutral-500 hover:bg-neutral-100 hover:text-neutral-900 md:hidden" aria-label="Close menu">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <nav class="flex-1 overflow-y-auto px-2 pb-6 pt-3">
                    <p class="px-3 pb-1 text-xs font-medium uppercase tracking-wider text-neutral-400">Team Portal</p>
                    @php
                        $menuItems = [
                            ['route' => 'team.dashboard', 'label' => 'Dashboard', 'match' => 'team.dashboard'],
                            ['route' => 'team.orders.create', 'label' => 'Create Order', 'match' => 'team.orders.create'],
                            ['route' => 'team.orders', 'label' => 'Orders', 'match' => 'team.orders*'],
                            ['route' => 'team.customers', 'label' => 'Customers', 'match' => 'team.customers'],
                            ['route' => 'team.payments', 'label' => 'Payments', 'match' => 'team.payments'],
                            ['route' => 'team.subscriptions', 'label' => 'Subscriptions', 'match' => 'team.subscriptions'],
                            ['route' => 'team.members', 'label' => 'Team Members', 'match' => 'team.members'],
                            ['route' => 'team.notifications', 'label' => 'Notifications', 'match' => 'team.notifications'],
                        ];
                    @endphp

                    <div class="mt-1 flex flex-col gap-1">
                        @foreach ($menuItems as $item)
                            <a href="{{ route($item['route']) }}"
                               class="rounded-lg px-3 py-2 text-sm font-medium transition-colors {{ request()->routeIs($item['match']) ? 'bg-neutral-900 text-white' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900' }}">
                                {{ $item['label'] }}
                            </a>
                        @endforeach

                        <div class="mt-4 border-t border-neutral-100 pt-3">
                            <p class="px-3 pb-1 text-xs font-medium uppercase tracking-wider text-neutral-400">Account</p>
                            <a href="#" class="rounded-lg px-3 py-2 text-sm font-medium text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900">Profile & Settings</a>
                        </div>
                    </div>
                </nav>
            </div>
        </aside>

        {{-- Main Column --}}
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 flex h-14 shrink-0 w-full items-center justify-between border-b border-neutral-200 bg-white/95 backdrop-blur-sm px-4 sm:px-6">
                <div class="flex items-center gap-2 sm:gap-3">
                    <button type="button" id="sidebar-toggle" aria-label="Toggle menu" class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-neutral-200 text-neutral-700 transition-colors hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-neutral-200">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon-menu absolute inset-0 m-auto h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon-close absolute inset-0 m-auto h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                    <span class="text-xs sm:text-sm font-medium text-neutral-500">Team Portal</span>
                </div>
                <div class="flex items-center gap-2 sm:gap-4">
                    <span class="max-w-[110px] truncate text-xs sm:max-w-xs sm:text-sm text-neutral-700 font-medium">{{ request()->user()?->team_name ?? request()->user()?->team_code }}</span>
                    <form method="POST" action="{{ route('team.logout') }}" class="shrink-0">
                        @csrf
                        <button type="submit" class="rounded-lg border border-neutral-200 px-2.5 py-1 text-xs sm:px-3 sm:py-1.5 sm:text-sm text-neutral-700 transition-colors hover:bg-neutral-50">
                            Log out
                        </button>
                    </form>
                </div>
            </header>

            <main class="flex-1 w-full max-w-full p-4 sm:p-6 lg:p-8">
                <div class="mx-auto w-full max-w-5xl">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    <style>
        #sidebar-toggle .icon-menu,
        #sidebar-toggle .icon-close {
            transition: opacity 0.2s ease, transform 0.2s ease;
        }
        #sidebar-toggle .icon-menu { opacity: 1; transform: scale(1); }
        #sidebar-toggle .icon-close { opacity: 0; transform: scale(0.5); }
        #sidebar-toggle.menu-open .icon-menu { opacity: 0; transform: scale(0.5); }
        #sidebar-toggle.menu-open .icon-close { opacity: 1; transform: scale(1); }
        @media (min-width: 768px) {
            #sidebar.sidebar-collapsed { width: 0 !important; border-right-width: 0 !important; overflow: hidden !important; }
        }
    </style>

    <script>
        (function () {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            const toggle = document.getElementById('sidebar-toggle');
            const closeBtn = document.getElementById('sidebar-close-btn');
            if (!sidebar || !toggle) return;
            const isMobile = () => window.matchMedia('(max-width: 767px)').matches;
            let mobileOpen = false;
            let desktopOpen = true;
            try { const saved = localStorage.getItem('leadx-team-sidebar-desktop'); if (saved !== null) desktopOpen = saved === 'open'; } catch (e) {}
            function render() {
                if (isMobile()) {
                    sidebar.classList.remove('sidebar-collapsed');
                    if (mobileOpen) {
                        sidebar.classList.remove('-translate-x-full'); sidebar.classList.add('translate-x-0');
                        if (overlay) overlay.classList.remove('hidden');
                        document.body.classList.add('overflow-hidden'); toggle.classList.add('menu-open');
                    } else {
                        sidebar.classList.add('-translate-x-full'); sidebar.classList.remove('translate-x-0');
                        if (overlay) overlay.classList.add('hidden');
                        document.body.classList.remove('overflow-hidden'); toggle.classList.remove('menu-open');
                    }
                } else {
                    document.body.classList.remove('overflow-hidden');
                    if (overlay) overlay.classList.add('hidden');
                    sidebar.classList.remove('-translate-x-full', 'translate-x-0');
                    if (desktopOpen) { sidebar.classList.remove('sidebar-collapsed'); toggle.classList.add('menu-open'); }
                    else { sidebar.classList.add('sidebar-collapsed'); toggle.classList.remove('menu-open'); }
                }
            }
            function toggleSidebar() {
                if (isMobile()) { mobileOpen = !mobileOpen; render(); }
                else { desktopOpen = !desktopOpen; try { localStorage.setItem('leadx-team-sidebar-desktop', desktopOpen ? 'open' : 'closed'); } catch (e) {} render(); }
            }
            function closeMobileSidebar() { if (isMobile() && mobileOpen) { mobileOpen = false; render(); } }
            toggle.addEventListener('click', toggleSidebar);
            if (overlay) overlay.addEventListener('click', closeMobileSidebar);
            if (closeBtn) closeBtn.addEventListener('click', closeMobileSidebar);
            sidebar.addEventListener('click', (e) => { if (isMobile() && e.target.closest('a')) closeMobileSidebar(); });
            window.addEventListener('keydown', (e) => { if (e.key === 'Escape' && isMobile() && mobileOpen) closeMobileSidebar(); });
            let prevIsMobile = isMobile();
            window.addEventListener('resize', () => { const c = isMobile(); if (c !== prevIsMobile) { prevIsMobile = c; if (!c) mobileOpen = false; render(); } });
            render();
        })();
    </script>
</body>
</html>
