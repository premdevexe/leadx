<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Employee Dashboard') — {{ \App\Models\Setting::siteName() }}</title>
    @if (\App\Models\Setting::faviconUrl())
        <link rel="icon" href="{{ \App\Models\Setting::faviconUrl() }}">
    @else
        <link rel="icon" href="{{ asset('favicon.ico') }}">
    @endif
    <link rel="manifest" href="{{ url('/manifest.json') }}">
    <meta name="theme-color" content="#171717">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ file_exists(public_path('assets/css/app.css')) ? filemtime(public_path('assets/css/app.css')) : time() }}">
    @include('partials.pusher-channels')
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <div class="app-shell">
        <div id="sidebar-overlay" class="sidebar-overlay" aria-hidden="true"></div>

        <aside id="sidebar" class="sidebar" aria-label="Employee Navigation">
            <div class="flex flex-col h-full w-full">
                <div class="sidebar-header">
                    <div class="sidebar-brand">
                        @php $brandLogo = \App\Models\Setting::logoTransparentUrl() ?? \App\Models\Setting::logoUrl(); @endphp
                        @if ($brandLogo)
                            <img src="{{ $brandLogo }}" alt="{{ \App\Models\Setting::siteName() }}" class="sidebar-brand-img">
                        @else
                            <span class="sidebar-brand-icon">
                                {{ strtoupper(substr(\App\Models\Setting::siteName(), 0, 1)) }}
                            </span>
                            <span class="sidebar-brand-text">{{ \App\Models\Setting::siteName() }}</span>
                        @endif
                    </div>
                    <button type="button" id="sidebar-close-btn" class="sidebar-close-btn" aria-label="Close menu">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <nav class="sidebar-nav">
                    <p class="sidebar-section-title">Employee Portal</p>
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

                    <div class="sidebar-menu mt-1">
                        @php $currentView = request()->query('view', 'my_data'); @endphp
                        @foreach ($menuItems as $item)
                            @if ($item['visible'] ?? true)
                                @canMenu($item['key'])
                                    @php
                                        $params = $item['params'] ?? [];
                                        if ($currentView === 'team_data' && in_array($item['key'], ['dashboard', 'orders', 'subscriptions', 'follow-ups', 'payments', 'customers', 'reports'])) {
                                            $params['view'] = 'team_data';
                                        }
                                        $isActive = request()->routeIs($item['match']) && (empty($item['params']) || request()->query('view') === ($params['view'] ?? null));
                                    @endphp
                                    <a href="{{ route($item['route'], $params) }}"
                                       @if($isActive) aria-current="page" @endif
                                       class="sidebar-link {{ $isActive ? 'active' : '' }}">
                                        {{ $item['label'] }}
                                    </a>
                                @endcanMenu
                            @endif
                        @endforeach
                    </div>
                </nav>
            </div>
        </aside>

        <div class="main-column">
            <header class="page-header">
                <div class="header-left">
                    <button type="button" id="sidebar-toggle" aria-label="Toggle menu" aria-controls="sidebar" class="sidebar-toggle-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon-menu h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon-close h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <a href="{{ route('employee.dashboard') }}" class="header-logo">
                        @php $headerLogo = \App\Models\Setting::logoTransparentUrl() ?? \App\Models\Setting::logoUrl(); @endphp
                        @if ($headerLogo)
                            <img src="{{ $headerLogo }}" alt="{{ \App\Models\Setting::siteName() }}" class="header-logo-img">
                        @else
                            <span class="header-logo-icon">
                                {{ strtoupper(substr(\App\Models\Setting::siteName(), 0, 1)) }}
                            </span>
                            <span class="header-logo-text hidden sm:inline">{{ \App\Models\Setting::siteName() }}</span>
                        @endif
                    </a>

                    <span class="header-divider hidden sm:inline-block"></span>
                    <span class="header-role-badge">Employee</span>
                    @if(request()->user()?->team)
                        <span class="badge badge-neutral text-2xs ml-1 hidden md:inline-flex">Team: {{ request()->user()->team->team_code }}</span>
                    @endif
                </div>
                <div class="header-right flex items-center gap-3">
                    <button type="button" id="notification-bell" data-notifications-url="{{ route('employee.notifications') }}" aria-label="Notifications" class="sidebar-toggle-btn p-2 rounded-lg text-neutral-600 hover:bg-neutral-100 relative">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                        </svg>
                        <span id="unread-badge" class="notification-badge" style="font-size:10px; min-width:18px; height:18px; padding: 0 4px;">0</span>
                    </button>
                    <a href="{{ route('employee.profile') }}" class="flex items-center gap-2 pl-2 border-l border-neutral-200 text-xs font-medium text-neutral-800 hover:text-neutral-900" title="My Profile">
                        <div class="w-7 h-7 rounded-full bg-neutral-800 text-white flex items-center justify-center font-bold text-xs select-none">
                            {{ strtoupper(substr(request()->user()?->name ?? 'E', 0, 1)) }}
                        </div>
                        <span class="header-username hidden sm:inline">{{ request()->user()?->name }}</span>
                    </a>
                    <form method="POST" action="{{ route('employee.logout') }}" class="shrink-0">
                        @csrf
                        <button type="submit" class="logout-btn btn btn-secondary text-xs px-2.5 py-1">Log out</button>
                    </form>
                </div>
            </header>

            <main id="main-content" class="page-content flex-1 p-4 sm:p-6 lg:p-8" tabindex="-1">
                <div class="page-content-inner max-w-7xl mx-auto">
                    @yield('content')
                </div>
            </main>

            <footer class="employee-footer border-t border-neutral-200 bg-white py-4 px-6 text-xs text-neutral-500 flex flex-col sm:flex-row items-center justify-between gap-2">
                <div>
                    &copy; {{ date('Y') }} <strong>{{ \App\Models\Setting::siteName() }}</strong>. Employee Portal.
                </div>
                <div class="flex items-center gap-4">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-green-500"></span>
                        <span>Employee Session Active</span>
                    </span>
                </div>
            </footer>
        </div>
    </div>

    <script type="module" src="{{ asset('assets/js/pages/employee-dashboard.js') }}"></script>
    @auth
        @if (isset($unreadCount) && $unreadCount > 0)
            <script>
                (function () {
                    var badge = document.getElementById('unread-badge');
                    if (badge) {
                        badge.textContent = @json($unreadCount) > 99 ? '99+' : @json($unreadCount);
                        badge.classList.add('visible');
                    }
                    window.leadxUnreadCount = @json($unreadCount);
                })();
            </script>
        @endif
    @endauth
    @yield('scripts')
    @stack('scripts')
</body>
</html>
