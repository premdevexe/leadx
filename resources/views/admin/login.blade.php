@extends('layouts.admin-auth')

@section('title', 'Admin Sign in')

@section('subtitle')
    <p class="mb-6 text-sm text-neutral-500">Admin console</p>
@endsection

@section('content')
    <h1 class="text-lg font-semibold">Sign in</h1>
    <p class="mt-1 text-sm text-neutral-500">Use your username or email with your password</p>

    <form method="POST" action="{{ route('admin.login.attempt') }}" class="mt-6">
        @csrf

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <label for="login" class="mb-1.5 block text-sm font-medium text-neutral-700">Username or email</label>
        <input id="login" type="text" name="login" value="{{ old('login') }}"
               class="mb-4 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10"
               required autofocus autocomplete="username">

        <label for="password" class="mb-1.5 block text-sm font-medium text-neutral-700">Password</label>
        <div class="relative mb-4">
            <input id="password" type="password" name="password"
                   class="w-full rounded-lg border border-neutral-300 px-3 py-2 pr-10 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10"
                   required autocomplete="current-password">
            <button type="button" id="password-toggle"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 rounded p-0.5 text-neutral-400 hover:text-neutral-600 focus:outline-none focus:ring-2 focus:ring-neutral-900/10"
                    aria-label="Show password">
                <svg id="icon-eye" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                </svg>
                <svg id="icon-eye-off" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="hidden h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 0 1-4.293 5.774M6.375 6.375a11.05 11.05 0 0 0-4.417 7.253.82.82 0 0 1-.371-.653c.005-.383.062-.76.167-1.123a9.626 9.626 0 0 1 5.016-5.968ZM18.375 18.375a11.05 11.05 0 0 0 4.417-7.253.82.82 0 0 1 .371.653c-.005.383-.062.76-.167 1.123a9.626 9.626 0 0 1-5.016 5.968Z"/>
                </svg>
            </button>
        </div>
        <script>
            (function () {
                var button = document.getElementById('password-toggle');
                var input = document.getElementById('password');
                var eye = document.getElementById('icon-eye');
                var eyeOff = document.getElementById('icon-eye-off');
                button.addEventListener('click', function () {
                    var showing = input.type === 'text';
                    input.type = showing ? 'password' : 'text';
                    eye.classList.toggle('hidden', showing);
                    eyeOff.classList.toggle('hidden', !showing);
                    button.setAttribute('aria-label', showing ? 'Hide password' : 'Show password');
                });
            })();
        </script>

        <label class="mb-5 flex items-center gap-2 text-sm text-neutral-600">
            <input type="checkbox" name="remember" class="h-4 w-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900/20">
            Remember me
        </label>

        <button type="submit" class="w-full rounded-lg bg-neutral-900 py-2.5 text-sm font-medium text-white hover:bg-neutral-800">
            Sign in
        </button>
    </form>

    @if (app()->environment('local'))
        <div class="mt-6 rounded-lg border border-neutral-200 bg-neutral-50 p-4">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-neutral-700">Local dev credentials</p>
                <button type="button" id="copy-credentials"
                        class="rounded-lg border border-neutral-300 bg-white px-2.5 py-1 text-xs font-medium text-neutral-700 hover:bg-neutral-100">
                    Copy
                </button>
            </div>
            <dl class="mt-2 space-y-1 text-sm text-neutral-600">
                <div class="flex gap-2">
                    <dt class="w-20 shrink-0 text-neutral-400">Username</dt>
                    <dd class="font-mono text-neutral-800">premfx</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="w-20 shrink-0 text-neutral-400">Password</dt>
                    <dd class="font-mono text-neutral-800">admin123</dd>
                </div>
            </dl>
            <script>
                document.getElementById('copy-credentials').addEventListener('click', function () {
                    var button = this;
                    var text = 'premfx\nadmin123';
                    var done = function () {
                        button.textContent = 'Copied!';
                        setTimeout(function () { button.textContent = 'Copy'; }, 1500);
                    };
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(); });
                    } else {
                        fallbackCopy();
                    }
                    function fallbackCopy() {
                        var ta = document.createElement('textarea');
                        ta.value = text;
                        ta.style.position = 'fixed';
                        ta.style.opacity = '0';
                        document.body.appendChild(ta);
                        ta.select();
                        try { document.execCommand('copy'); done(); } catch (e) {}
                        document.body.removeChild(ta);
                    }
                });
            </script>
        </div>
    @endif
@endsection
