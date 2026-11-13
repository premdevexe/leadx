<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Sign in') — LeadX</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900 antialiased">
    <main class="flex min-h-screen items-center justify-center p-6">
        <div class="w-full max-w-sm">
            <div class="mb-4 flex items-center gap-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-neutral-900 text-sm font-semibold text-white">L</span>
                <span class="text-lg font-semibold tracking-tight">LeadX</span>
            </div>
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                @yield('subtitle')
                @yield('content')
            </div>
        </div>
    </main>
</body>
</html>
