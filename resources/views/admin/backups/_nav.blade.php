<nav class="flex flex-wrap gap-2 mb-5" aria-label="Backup management">
    @foreach (['admin.backups.index' => 'Backup history', 'admin.backups.restore' => 'Restore', 'admin.backups.settings' => 'Schedule & retention'] as $route => $label)
        <a href="{{ route($route) }}" class="btn {{ request()->routeIs($route) ? 'btn-primary' : 'btn-secondary' }}" @if(request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
@if (session('status'))
    <div class="alert alert-success mb-4" role="status">{{ session('status') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger mb-4" role="alert">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger mb-4" role="alert" tabindex="-1" id="backup-errors">
        <div>
            <p class="font-semibold">Please check the following fields.</p>
            <ul class="mt-2">
                @foreach ($errors->messages() as $field => $messages)
                    <li><a href="#{{ str_replace('_', '-', $field) }}" class="underline">{{ $messages[0] }}</a></li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
@push('scripts')
<script>
document.getElementById('backup-errors')?.focus();
document.querySelectorAll('form[data-backup-action]').forEach(form => {
    form.addEventListener('submit', event => {
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
            event.preventDefault();
            return;
        }
        const button = form.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = true;
            button.textContent = form.dataset.backupAction;
            form.setAttribute('aria-busy', 'true');
        }
    });
});
</script>
@endpush
