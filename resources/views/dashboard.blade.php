<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — LeadX</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-neutral-50 font-sans text-neutral-900 antialiased">
    <header class="flex h-14 items-center justify-between border-b border-neutral-200 bg-white px-6">
        <div class="flex items-center gap-2">
            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-neutral-900 text-xs font-semibold text-white">L</span>
            <span class="font-semibold tracking-tight">LeadX</span>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="rounded-lg px-3 py-1.5 text-sm text-neutral-600 transition-colors hover:bg-neutral-100">
                Log out
            </button>
        </form>
    </header>

    <main class="mx-auto w-full max-w-md px-6 py-12">
        <h1 class="text-xl font-semibold tracking-tight">Your account</h1>
        <p class="mt-1 text-sm text-neutral-500">You are signed in as {{ auth()->user()->email }}</p>

        <div class="mt-6 divide-y divide-neutral-100 rounded-xl border border-neutral-200 bg-white p-6">
            <dl class="flex items-center justify-between py-1">
                <dt class="text-sm text-neutral-500">Name</dt>
                <dd class="text-sm">{{ auth()->user()->name }}</dd>
            </dl>
            <dl class="flex items-center justify-between py-1">
                <dt class="text-sm text-neutral-500">Email</dt>
                <dd class="text-sm">{{ auth()->user()->email }}</dd>
            </dl>
        </div>

        <p class="mt-4 text-sm text-neutral-500">Need your team or credentials? Contact your administrator.</p>
    </main>
</body>
</html>
