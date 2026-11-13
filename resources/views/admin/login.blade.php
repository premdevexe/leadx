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
        <input id="password" type="password" name="password"
               class="mb-4 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10"
               required autocomplete="current-password">

        <label class="mb-5 flex items-center gap-2 text-sm text-neutral-600">
            <input type="checkbox" name="remember" class="h-4 w-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900/20">
            Remember me
        </label>

        <button type="submit" class="w-full rounded-lg bg-neutral-900 py-2.5 text-sm font-medium text-white hover:bg-neutral-800">
            Sign in
        </button>
    </form>
@endsection
