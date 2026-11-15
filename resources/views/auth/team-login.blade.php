<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Login — {{ \App\Models\Setting::siteName() }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900 antialiased">
    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-sm">
            <div class="mb-8 text-center">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-neutral-900 text-sm font-semibold text-white mx-auto">L</span>
                <h1 class="mt-4 text-lg font-semibold">Team Login</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ \App\Models\Setting::siteName() }}</p>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                @if ($errors->any())
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('team.login.attempt') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="login" class="mb-1.5 block text-sm font-medium text-neutral-700">Username</label>
                        <input id="login" type="text" name="login" value="{{ old('login') }}"
                               class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10"
                               required autofocus autocomplete="username">
                    </div>

                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-medium text-neutral-700">Password</label>
                        <input id="password" type="password" name="password"
                               class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10"
                               required autocomplete="current-password">
                    </div>

                    <label class="flex items-center gap-2 text-sm text-neutral-600">
                        <input type="checkbox" name="remember" class="h-4 w-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900/20">
                        Remember me
                    </label>

                    <button type="submit" class="w-full rounded-lg bg-neutral-900 py-2.5 text-sm font-medium text-white transition-colors hover:bg-neutral-800">
                        Sign in
                    </button>
                </form>
            </div>

            <p class="mt-4 text-center text-xs text-neutral-500">
                <a href="{{ route('login') }}" class="underline hover:text-neutral-900">Employee login</a>
                &nbsp;·&nbsp;
                <a href="{{ route('admin.login') }}" class="underline hover:text-neutral-900">Admin login</a>
            </p>
        </div>
    </div>
</body>
</html>
