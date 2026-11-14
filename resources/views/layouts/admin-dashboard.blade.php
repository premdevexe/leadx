<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin Dashboard') — {{ \App\Models\Setting::siteName() }}</title>
    <link rel="manifest" href="{{ url('/manifest.json') }}">
    <meta name="theme-color" content="#171717">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.push-beams')
</head>
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900 antialiased">
    <div class="relative flex min-h-screen">
        <div id="sidebar-overlay" class="fixed inset-0 z-30 hidden bg-neutral-900/40 md:hidden"></div>

        <aside id="sidebar"
               class="fixed inset-y-0 left-0 z-40 w-60 shrink-0 -translate-x-full overflow-hidden border-r border-neutral-200 bg-white transition-[width,transform] duration-200 ease-in-out md:static md:translate-x-0">
            <div class="flex h-14 items-center gap-2 px-4">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-neutral-900 text-sm font-semibold text-white">L</span>
                <span class="font-semibold tracking-tight">{{ \App\Models\Setting::siteName() }}</span>
            </div>
            <nav class="flex flex-col gap-1 px-2 pb-6 pt-4">
                <p class="px-3 pb-1 text-xs text-neutral-400">Management</p>
                @php
                    $menuItems = [
                        ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'match' => 'admin.dashboard'],
                        ['route' => 'admin.reports.index', 'label' => 'Reports & Analytics', 'match' => 'admin.reports*'],
                        ['route' => 'admin.teams', 'label' => 'Teams', 'match' => 'admin.teams*'],
                        ['route' => 'admin.orders', 'label' => 'Orders', 'match' => 'admin.orders*'],
                        ['route' => 'admin.products', 'label' => 'Products', 'match' => 'admin.products*'],
                        ['route' => 'admin.customers', 'label' => 'Customers (CRM)', 'match' => 'admin.customers*'],
                        ['route' => 'admin.employees', 'label' => 'Employees', 'match' => 'admin.employees*'],
                        ['route' => 'admin.admins', 'label' => 'Admins', 'match' => 'admin.admins*'],
                        ['route' => 'admin.notifications', 'label' => 'Send Notification', 'match' => 'admin.notifications*'],
                        ['route' => 'admin.settings', 'label' => 'Settings', 'match' => 'admin.settings*'],
                    ];
                @endphp

                @foreach ($menuItems as $item)
                    <a href="{{ route($item['route']) }}"
                       class="rounded-lg px-3 py-2 text-sm transition-colors {{ request()->routeIs($item['match']) ? 'bg-neutral-900 text-white' : 'text-neutral-600 hover:bg-neutral-100' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex h-14 items-center justify-between border-b border-neutral-200 bg-white px-6">
                <div class="flex items-center gap-3">
                    <button type="button" id="sidebar-toggle" aria-label="Toggle menu" class="relative h-9 w-9 rounded-lg border border-neutral-200 text-neutral-700 transition-colors hover:bg-neutral-50">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon-menu absolute inset-0 m-auto h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon-close absolute inset-0 m-auto h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                    <span class="text-sm text-neutral-500">Admin</span>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-neutral-700">{{ auth('admin')->user()->username }}</span>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="rounded-lg border border-neutral-200 px-3 py-1.5 text-sm text-neutral-700 transition-colors hover:bg-neutral-50">
                            Log out
                        </button>
                    </form>
                </div>
            </header>

            <main class="flex-1 p-6 lg:p-8">
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
        #sidebar-toggle .icon-menu {
            opacity: 1;
            transform: scale(1);
        }
        #sidebar-toggle .icon-close {
            opacity: 0;
            transform: scale(0.5);
        }
        #sidebar-toggle.menu-open .icon-menu {
            opacity: 0;
            transform: scale(0.5);
        }
        #sidebar-toggle.menu-open .icon-close {
            opacity: 1;
            transform: scale(1);
        }
        #sidebar.sidebar-collapsed {
            width: 0 !important;
        }
    </style>

    <script>
        (function () {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            const toggle = document.getElementById('sidebar-toggle');
            const isMobile = () => window.matchMedia('(max-width: 767px)').matches;

            let open = !isMobile(); // mobile starts closed, desktop starts open
            try {
                const saved = localStorage.getItem('leadx-sidebar');
                if (saved !== null) open = saved === 'open';
            } catch (e) {}

            function apply(openState) {
                if (isMobile()) {
                    sidebar.classList.remove('sidebar-collapsed');
                    sidebar.classList.add('border-r');
                    sidebar.classList.toggle('-translate-x-full', !openState);
                    overlay.classList.toggle('hidden', !openState);
                } else {
                    sidebar.classList.remove('-translate-x-full');
                    overlay.classList.add('hidden');
                    sidebar.classList.toggle('sidebar-collapsed', !openState);
                    sidebar.classList.toggle('border-r', openState);
                }
                toggle.classList.toggle('menu-open', openState);
            }

            function setSidebar(openState) {
                open = openState;
                apply(open);
                try {
                    localStorage.setItem('leadx-sidebar', open ? 'open' : 'closed');
                } catch (e) {}
            }

            toggle.addEventListener('click', () => setSidebar(!open));
            overlay.addEventListener('click', () => setSidebar(false));
            sidebar.addEventListener('click', (e) => {
                if (isMobile() && e.target.closest('a')) setSidebar(false);
            });
            window.addEventListener('resize', () => apply(open));

            apply(open);
        })();
    </script>
</body>
</html>
