<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Login — {{ \App\Models\Setting::siteName() }}</title>
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
                <span class="auth-brand-icon">{{ strtoupper(substr(\App\Models\Setting::siteName(), 0, 1)) }}</span>
                <h1 class="auth-title">Team Login</h1>
                <p class="auth-subtitle">{{ \App\Models\Setting::siteName() }}</p>
            </div>

            <div class="auth-box">
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <div class="alert-message">{{ $errors->first() }}</div>
                    </div>
                @endif

                <form method="POST" action="{{ route('team.login.attempt') }}">
                    @csrf

                    <div class="form-group">
                        <label for="login" class="form-label">Username</label>
                        <input id="login" type="text" name="login" value="{{ old('login') }}" class="form-input" required autofocus autocomplete="username">
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="password-field">
                            <input id="password" type="password" name="password" class="form-input" required autocomplete="current-password">
                            <button type="button" id="password-toggle" class="password-toggle" aria-label="Show password" aria-controls="password" aria-pressed="false">
                                <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                </svg>
                                <svg class="icon-eye-off" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 0 1-4.293 5.774M6.375 6.375a11.05 11.05 0 0 0-4.417 7.253.82.82 0 0 1-.371-.653c.005-.383.062-.76.167-1.123a9.626 9.626 0 0 1 5.016-5.968ZM18.375 18.375a11.05 11.05 0 0 0 4.417-7.253.82.82 0 0 1-.371-.653c-.005-.383.062-.76.167-1.123a9.626 9.626 0 0 1-5.016 5.968Z"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <label class="form-check">
                        <input type="checkbox" name="remember" value="1">
                        <span>Remember me</span>
                    </label>

                    <button type="submit" id="login-submit" class="btn btn-primary btn-block btn-submit">
                        <span class="btn-label">Sign in</span>
                    </button>
                </form>
            </div>
        </div>
    </main>

    <script>
        (function () {
            // Show / hide password (same pattern as the admin login page).
            var toggle = document.getElementById('password-toggle');
            var input = document.getElementById('password');
            toggle.addEventListener('click', function () {
                var showing = input.type === 'text';
                input.type = showing ? 'password' : 'text';
                toggle.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
                toggle.setAttribute('aria-pressed', String(!showing));
                input.focus();
            });

            // Prevent double submission: lock the button on submit.
            var form = document.querySelector('form');
            var button = document.getElementById('login-submit');
            form.addEventListener('submit', function () {
                if (button.classList.contains('is-loading')) { return; }
                button.classList.add('is-loading');
                button.setAttribute('aria-busy', 'true');
            });
        })();
    </script>
</body>
</html>
