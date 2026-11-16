<form method="GET" action="{{ route($portal.'.follow-ups') }}" class="flex flex-wrap items-end gap-3 mt-5">
    @if ($portal === 'employee')<input type="hidden" name="view" value="{{ $viewMode }}">@endif
    <div>
        <label for="status" class="form-label">Follow-up status</label>
        <select id="status" name="status" class="form-select">
            <option value="">All statuses</option>
            @foreach (\App\Models\FollowUp::STATUSES as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="btn btn-secondary">Apply Filter</button>
    @if (request('status'))<a href="{{ route($portal.'.follow-ups', $portal === 'employee' ? ['view' => $viewMode] : []) }}" class="btn btn-secondary">Clear Filter</a>@endif
</form>
