<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LeadX — Create Admin</title>
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ file_exists(public_path('assets/css/app.css')) ? filemtime(public_path('assets/css/app.css')) : time() }}">
</head>
<body>
    <main class="auth-page">
        <div style="width: 100%; max-width: 28rem;">
            <div class="auth-card" style="max-width: 100%;">
                <div class="auth-brand">
                    <span class="auth-brand-icon">L</span>
                    <span class="auth-brand-text">LeadX Installation</span>
                </div>
                <div class="auth-box">
                    <div class="auth-title">Create Super Admin</div>
                    <div class="auth-subtitle">Set up your first administrator account.</div>

                    @if (session('admin_created'))
                        <div class="alert alert-success" style="margin-top: 1.25rem;">
                            <span class="alert-icon">&#10003;</span>
                            <div class="alert-content">
                                <div class="alert-title">Admin account created</div>
                                <div class="alert-message">Account {{ session('admin_email') }} is ready.</div>
                            </div>
                        </div>
                    @endif

                    @php
                        $errorMessages = [];
                        if (isset($errors)) {
                            if ($errors instanceof \Illuminate\Support\ViewErrorBag || $errors instanceof \Illuminate\Support\MessageBag) {
                                $errorMessages = $errors->all();
                            } elseif (is_array($errors)) {
                                $errorMessages = \Illuminate\Support\Arr::flatten($errors);
                            }
                        }
                    @endphp

                    @if (!empty($errorMessages))
                        <div class="error-box" style="margin-top: 1.25rem;">
                            <ul style="margin-left: 1.25rem; list-style: disc;">
                                @foreach ($errorMessages as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('install.admin') }}" style="margin-top: 1.5rem;">
                        @csrf

                        <div class="form-group">
                            <label for="name" class="form-label form-label-required">Full Name</label>
                            <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-input" required autofocus>
                        </div>

                        <div class="form-group">
                            <label for="email" class="form-label form-label-required">Email Address</label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-input" required>
                        </div>

                        <div class="form-group">
                            <label for="username" class="form-label form-label-required">Username</label>
                            <input type="text" id="username" name="username" value="{{ old('username') }}" class="form-input" required>
                            <p class="form-hint">Used for admin login</p>
                        </div>

                        <div class="form-group">
                            <label for="password" class="form-label form-label-required">Password</label>
                            <input type="password" id="password" name="password" class="form-input" required>
                            <p class="form-hint">Minimum 8 characters</p>
                        </div>

                        <div class="form-group">
                            <label for="password_confirmation" class="form-label form-label-required">Confirm Password</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" class="form-input" required>
                        </div>

                        <div class="form-actions">
                            <a href="{{ route('install.migrate') }}" class="btn btn-secondary">Back</a>
                            <button type="submit" class="btn btn-primary">Create Admin</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
