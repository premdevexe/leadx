<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Login — {{ \App\Models\Setting::siteName() }}</title>
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
            <div class="auth-brand auth-brand--stacked">
                @if (\App\Models\Setting::logoUrl())
                    <img src="{{ \App\Models\Setting::logoUrl() }}" alt="{{ \App\Models\Setting::siteName() }}" class="auth-brand-img">
                @else
                    <span class="auth-brand-icon">{{ strtoupper(substr(\App\Models\Setting::siteName(), 0, 1)) }}</span>
                @endif
                <h1 class="auth-title">Employee Login</h1>
                <p class="auth-subtitle">{{ \App\Models\Setting::siteName() }}</p>
            </div>

            <div class="auth-box" style="border: var(--border-width) solid var(--c-neutral-200); background: var(--c-white); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); padding: var(--sp-5);">
                @if (session('info'))
                    <div class="alert alert-warning" style="margin-bottom: var(--sp-4);">
                        <div class="alert-message">{{ session('info') }}</div>
                    </div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger" style="margin-bottom: var(--sp-4);">
                        <div class="alert-message">{{ $errors->first() }}</div>
                    </div>
                @endif

                <form method="POST" action="{{ route('employee.login.attempt') }}" style="display: flex; flex-direction: column; gap: var(--sp-4);">
                    @csrf

                    <div class="form-group">
                        <label for="login" class="form-label">Username or email</label>
                        <input id="login" type="text" name="login" value="{{ old('login') }}" class="form-input" required autofocus autocomplete="username">
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input id="password" type="password" name="password" class="form-input" required autocomplete="current-password">
                    </div>

                    <label class="form-check">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>

                    <button type="submit" class="btn btn-primary btn-block">Sign in</button>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
