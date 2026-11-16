<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Sign in') — {{ \App\Models\Setting::siteName() }}</title>
    @if (\App\Models\Setting::faviconUrl())
        <link rel="icon" href="{{ \App\Models\Setting::faviconUrl() }}">
    @else
        <link rel="icon" href="{{ asset('favicon.ico') }}">
    @endif
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ file_exists(public_path('assets/css/app.css')) ? filemtime(public_path('assets/css/app.css')) : time() }}">
</head>
<body>
    <main class="auth-page">
        <div class="auth-card">
            <div class="auth-brand">
                @if (\App\Models\Setting::logoUrl())
                    <img src="{{ \App\Models\Setting::logoUrl() }}" alt="{{ \App\Models\Setting::siteName() }}" class="auth-brand-img">
                @else
                    <span class="auth-brand-icon">
                        {{ strtoupper(substr(\App\Models\Setting::siteName(), 0, 1)) }}
                    </span>
                    <span class="auth-brand-text">{{ \App\Models\Setting::siteName() }}</span>
                @endif
            </div>
            <div class="auth-box">
                @yield('subtitle')
                @yield('content')
            </div>
        </div>
    </main>
</body>
</html>
