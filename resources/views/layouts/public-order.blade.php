<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- The share page is a customer-facing, unauthenticated surface: no
        session state, no portal chrome, no navigation that would leak
        internal structure. CSP is tight: same-origin only. --}}
    <meta http-equiv="Content-Security-Policy"
          content="default-src 'none'; style-src 'self'; img-src 'self' data:; base-uri 'self'; frame-ancestors 'none'">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Order') — {{ \App\Models\Setting::siteName() }}</title>
    @if (\App\Models\Setting::faviconUrl())
        <link rel="icon" href="{{ \App\Models\Setting::faviconUrl() }}">
    @else
        <link rel="icon" href="{{ asset('favicon.ico') }}">
    @endif
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ file_exists(public_path('assets/css/app.css')) ? filemtime(public_path('assets/css/app.css')) : time() }}">
</head>
<body class="public-order-page">
    <main class="public-order-wrap">
        <div class="public-order-brand">
            @if (\App\Models\Setting::logoUrl())
                <img src="{{ \App\Models\Setting::logoUrl() }}" alt="{{ \App\Models\Setting::siteName() }}">
            @else
                <span class="public-order-brand-icon">
                    {{ strtoupper(substr(\App\Models\Setting::siteName(), 0, 1)) }}
                </span>
            @endif
            <span class="public-order-brand-name">{{ \App\Models\Setting::siteName() }}</span>
        </div>

        <div class="public-order-card">
            @yield('content')
        </div>

        <p class="public-order-foot">
            This is a secure, customer-only view. No account sign-in is required.
        </p>
    </main>
</body>
</html>
