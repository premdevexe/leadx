@extends('layouts.employee')

@section('title', 'My Customers')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight">My Customers</h1>
    <p class="mt-1 text-sm text-neutral-500">Customers associated with your orders.</p>

    <div class="mt-5 overflow-x-auto rounded-xl border border-neutral-200 bg-white">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-neutral-200 bg-neutral-50">
                <tr>
                    <th class="px-4 py-2.5 font-medium text-neutral-500">Name</th>
                    <th class="px-4 py-2.5 font-medium text-neutral-500">Phone</th>
                    <th class="px-4 py-2.5 font-medium text-neutral-500">Email</th>
                    <th class="px-4 py-2.5 font-medium text-neutral-500">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse ($customers as $customer)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-3">{{ $customer->name }}</td>
                        <td class="px-4 py-3">{{ $customer->phone }}</td>
                        <td class="px-4 py-3">{{ $customer->email }}</td>
                        <td class="px-4 py-3">{{ ucfirst($customer->status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-sm text-neutral-500">No customers found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $customers->links() }}</div>
@endsection
