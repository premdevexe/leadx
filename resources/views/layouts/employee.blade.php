<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Employee Dashboard') — {{ \App\Models\Setting::siteName() }}</title>
    @if (\App\Models\Setting::faviconUrl())
        <link rel="icon" href="{{ \App\Models\Setting::faviconUrl() }}">
    @else
        <link rel="icon" href="{{ asset('favicon.ico') }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.pusher-channels')
</head>
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900 antialiased overflow-x-clip">
    <div class="relative flex min-h-screen w-full max-w-full overflow-x-clip">
        <div id="sidebar-overlay"
             class="fixed inset-0 z-40 bg-neutral-900/50 backdrop-blur-xs transition-opacity duration-200 hidden md:hidden"
             aria-hidden="true"></div>

        <aside id="sidebar"
               class="fixed inset-y-0 left-0 z-50 flex h-full w-64 max-w-[85vw] flex-col border-r border-neutral-200 bg-white shadow-2xl transition-transform duration-200 ease-in-out -translate-x-full overflow-x-hidden overflow-y-auto md:sticky md:top-0 md:z-20 md:h-screen md:w-60 md:max-w-none md:shadow-none md:translate-x-0 md:transition-[width] md:duration-200"
               aria-label="Employee Navigation">
            <div class="flex h-full w-full flex-col">
                <div class="flex h-14 shrink-0 items-center justify-between border-b border-neutral-100 px-4 md:border-b-0">
                    <div class="flex items-center gap-2">
                        @php $brandLogo = \App\Models\Setting::logoTransparentUrl() ?? \App\Models\Setting::logoUrl(); @endphp
                        @if ($brandLogo)
                            <img src="{{ $brandLogo }}" alt="{{ \App\Models\Setting::siteName() }}" class="h-8 max-w-[140px] w-auto object-contain">
                        @else
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-neutral-900 text-sm font-semibold text-white">
                                {{ strtoupper(substr(\App\Models\Setting::siteName(), 0, 1)) }}
                            </span>
                            <span class="font-semibold tracking-tight">{{ \App\Models\Setting::siteName() }}</span>
                        @endif
                    </div>
                    <button type="button" id="sidebar-close-btn" class="flex h-8 w-8 items-center justify-center rounded-lg text-neutral-500 hover:bg-neutral-100 hover:text-neutral-900 md:hidden" aria-label="Close menu">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <nav class="flex-1 overflow-y-auto px-2 pb-6 pt-3">
                    <p class="px-3 pb-1 text-xs font-medium uppercase tracking-wider text-neutral-400">Employee Portal</p>
                    @php
                        $employeeId = auth()->id() ?? 0;
                        $defaultModules = \App\Http\Controllers\Employee\DashboardController::DEFAULT_SIDEBAR_MODULES;
                        $savedModules = \App\Models\UserUiPreference::getPreferences('employee', $employeeId, 'sidebar_modules', []);

                        if (!empty($savedModules)) {
                            $savedByKey = collect($savedModules)->keyBy('key');
                            $mergedModules = [];
                            foreach ($defaultModules as $def) {
                                if ($savedByKey->has($def['key'])) {
                                    $saved = $savedByKey->get($def['key']);
                                    $mergedModules[] = array_merge($def, [
                                        'visible' => (bool) ($saved['visible'] ?? true),
                                        'order' => (int) ($saved['order'] ?? $def['order']),
                                    ]);
                                } else {
                                    $mergedModules[] = $def;
                                }
                            }
                            usort($mergedModules, fn($a, $b) => $a['order'] <=> $b['order']);
                            $menuItems = $mergedModules;
                        } else {
                            $menuItems = $defaultModules;
                        }
                    @endphp

                    <div class="mt-1 flex flex-col gap-1">
                        @php
                            $currentView = request()->query('view', 'my_data');
                        @endphp
                        @foreach ($menuItems as $item)
                            @if ($item['visible'] ?? true)
                                @canMenu($item['key'])
                                    @php
                                        $params = $item['params'] ?? [];
                                        if ($currentView === 'team_data' && in_array($item['key'], ['dashboard', 'orders', 'subscriptions', 'follow-ups', 'payments', 'customers', 'reports'])) {
                                            $params['view'] = 'team_data';
                                        }
                                    @endphp
                                    <a href="{{ route($item['route'], $params) }}"
                                       class="rounded-lg px-3 py-2 text-sm font-medium transition-colors {{ request()->routeIs($item['match']) && (empty($item['params']) || request()->query('view') === ($params['view'] ?? null)) ? 'bg-neutral-900 text-white' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900' }}">
                                        {{ $item['label'] }}
                                    </a>
                                @endcanMenu
                            @endif
                        @endforeach
                    </div>
                </nav>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-30 flex h-14 shrink-0 w-full items-center justify-between border-b border-neutral-200 bg-white/95 backdrop-blur-sm px-4 sm:px-6">
                <div class="flex items-center gap-2.5 sm:gap-3.5">
                    <button type="button" id="sidebar-toggle" aria-label="Toggle menu" class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-neutral-200 text-neutral-700 transition-colors hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-neutral-200">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon-menu absolute inset-0 m-auto h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon-close absolute inset-0 m-auto h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <a href="{{ route('employee.dashboard') }}" class="flex items-center gap-2 transition-opacity hover:opacity-85">
                        @php $headerLogo = \App\Models\Setting::logoTransparentUrl() ?? \App\Models\Setting::logoUrl(); @endphp
                        @if ($headerLogo)
                            <img src="{{ $headerLogo }}" alt="{{ \App\Models\Setting::siteName() }}" class="h-8 max-w-[140px] w-auto object-contain">
                        @else
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-neutral-900 text-xs font-semibold text-white">
                                {{ strtoupper(substr(\App\Models\Setting::siteName(), 0, 1)) }}
                            </span>
                            <span class="font-semibold tracking-tight text-neutral-900 text-sm hidden sm:inline">{{ \App\Models\Setting::siteName() }}</span>
                        @endif
                    </a>

                    <span class="hidden sm:inline-block h-4 w-px bg-neutral-200"></span>
                    <span class="text-[11px] font-medium text-neutral-500 rounded bg-neutral-100 px-1.5 py-0.5">Employee</span>
                </div>
                <div class="flex items-center gap-2 sm:gap-4">
                    <button type="button" id="notification-bell" aria-label="Notifications" class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-neutral-200 text-neutral-700 transition-colors hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-neutral-200">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                        </svg>
                        <span id="unread-badge" class="absolute -right-1.5 -top-1.5 hidden h-4.5 w-4.5 items-center justify-center rounded-full bg-red-500 text-white" style="font-size:10px; min-width:18px; height:18px; padding: 0 4px;">0</span>
                    </button>
                    <span class="max-w-[110px] truncate text-xs sm:max-w-xs sm:text-sm text-neutral-700 font-medium">{{ request()->user()?->name }}</span>
                    <form method="POST" action="{{ route('employee.logout') }}" class="shrink-0">
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
            try { const saved = localStorage.getItem('leadx-employee-sidebar-desktop'); if (saved !== null) desktopOpen = saved === 'open'; } catch (e) {}
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
                else { desktopOpen = !desktopOpen; try { localStorage.setItem('leadx-employee-sidebar-desktop', desktopOpen ? 'open' : 'closed'); } catch (e) {} render(); }
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

    <script>
        @auth
        // Unread count comes from the Laravel view variable (already marked-as-read on page load)
        var unreadInitial = @json($unreadCount ?? 0);
        var badge = document.getElementById('unread-badge');
        if (badge && unreadInitial > 0) {
            badge.textContent = unreadInitial > 99 ? '99+' : unreadInitial;
            badge.classList.remove('hidden');
            badge.classList.add('flex');
        }

        document.addEventListener('leadx:notification', function (e) {
            var data = e.detail;
            if (!data || !data.title) return;

            var toast = document.createElement('div');
            toast.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:9999;background:#18181b;color:#fff;padding:12px 16px;border-radius:8px;box-shadow:0 10px 15px -3px rgba(0,0,0,.1);font-size:14px;max-width:320px;transform:translateY(100px);opacity:0;transition:all .3s ease;pointer-events:none;';
            toast.innerHTML = '<div style="font-weight:600;margin-bottom:2px;">' + data.title + '</div>' +
                '<div style="color:#a1a1aa;font-size:13px;">' + data.message + '</div>';
            document.body.appendChild(toast);

            requestAnimationFrame(function () {
                toast.style.transform = 'translateY(0)';
                toast.style.opacity = '1';
            });

            if (badge) {
                var current = parseInt(badge.textContent || '0', 10);
                badge.textContent = current + 1 > 99 ? '99+' : (current + 1);
                badge.classList.remove('hidden');
                badge.classList.add('flex');
            }

            setTimeout(function () {
                toast.style.transform = 'translateY(100px)';
                toast.style.opacity = '0';
                setTimeout(function () { toast.remove(); }, 300);
            }, 4000);
        });

        document.getElementById('notification-bell')?.addEventListener('click', function () {
            window.location.href = '/employee/notifications';
        });
        @endauth
    </script>
</body>
</html>
