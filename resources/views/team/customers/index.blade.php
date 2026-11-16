@extends('layouts.team')

@section('title', 'Customers')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">Customers</h1>
    <p class="mt-1 text-sm text-neutral-500">Customers associated with your team's orders.</p>

    <div class="mt-5 overflow-x-auto rounded-lg border border-neutral-200 bg-white">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($customers as $customer)
                    <tr>
                        <td>{{ $customer->name }}</td>
                        <td>{{ $customer->phone }}</td>
                        <td>{{ $customer->email }}</td>
                        <td>{{ ucfirst($customer->status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="table-empty">No customers found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $customers->links() }}</div>
@endsection
