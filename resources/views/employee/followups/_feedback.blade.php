@if (session('status'))
    <div class="alert alert-success mb-5" role="status">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger mb-5" role="alert" tabindex="-1" id="followup-errors" aria-labelledby="followup-errors-title">
        <h2 id="followup-errors-title" class="font-semibold">Please check the following details</h2>
        <ul class="mt-2 list-disc list-inside">
            @foreach ($errors->messages() as $field => $messages)
                @foreach ($messages as $message)
                    <li>@if ($field !== 'follow_up')<a class="underline" href="#{{ $field }}">{{ $message }}</a>@else{{ $message }}@endif</li>
                @endforeach
            @endforeach
        </ul>
    </div>
    @push('scripts')
        <script>document.getElementById('followup-errors')?.focus();</script>
    @endpush
@endif
