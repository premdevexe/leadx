<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — LeadX</title>
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ file_exists(public_path('assets/css/app.css')) ? filemtime(public_path('assets/css/app.css')) : time() }}">
    @if (auth()->user() && auth()->user()->role === 'employee')
        <meta http-equiv="refresh" content="0; url={{ route('employee.dashboard') }}">
        <script>
            window.location.replace("{{ route('employee.dashboard') }}");
        </script>
    @endif
</head>
<body>
    <header class="page-header" style="justify-content:space-between;">
        <div class="flex items-center gap-2">
            <span style="display:flex;align-items:center;justify-content:center;width:1.5rem;height:1.5rem;border-radius:var(--radius-md);background:var(--c-neutral-900);color:var(--c-white);font-size:var(--text-xs);font-weight:600;">L</span>
            <span class="font-semibold tracking-tight">LeadX</span>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="logout-btn">Log out</button>
        </form>
    </header>

    <main style="max-width:28rem;margin:0 auto;padding:3rem 1.5rem;">
        <h1 class="text-xl font-semibold tracking-tight">Your account</h1>
        <p class="mt-1 text-sm text-neutral-500">You are signed in as {{ auth()->user()->email }}</p>

        <div style="margin-top:var(--sp-6);border:var(--border-width) solid var(--c-neutral-200);border-radius:var(--radius-lg);background:var(--c-white);padding:var(--sp-6);">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:var(--sp-1) 0;">
                <span class="text-sm text-neutral-500">Name</span>
                <span class="text-sm font-medium">{{ auth()->user()->name }}</span>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:var(--sp-1) 0;">
                <span class="text-sm text-neutral-500">Email</span>
                <span class="text-sm font-medium">{{ auth()->user()->email }}</span>
            </div>
            @if (auth()->user() && auth()->user()->role === 'employee')
                <div style="padding-top:var(--sp-4);">
                    <a href="{{ route('employee.dashboard') }}"
                       class="btn btn-primary" style="width:100%;">
                        Go to Employee Portal &rarr;
                    </a>
                </div>
            @endif
        </div>

        <p class="mt-4 text-sm text-neutral-500">Need your team or credentials? Contact your administrator.</p>
    </main>
</body>
</html>
