@extends('layouts.admin-dashboard')

@section('title', 'Flutter API Integration Guide')

@section('content')
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-neutral-200">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.api-management.index', ['tab' => 'docs']) }}" class="btn btn-secondary btn-sm flex items-center gap-1.5 text-xs">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span>Back to API Management</span>
            </a>
            <h1 class="text-lg font-semibold tracking-tight text-neutral-900">Flutter Mobile API Integration Guide</h1>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.api-management.docs.flutter', ['download' => 1]) }}" class="btn btn-primary btn-sm text-xs">
                Download Guide (.md)
            </a>
        </div>
    </div>

    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-xs prose max-w-none">
        <pre class="bg-neutral-50 p-4 rounded-lg text-xs font-mono text-neutral-800 overflow-x-auto whitespace-pre-wrap leading-relaxed">{{ $content }}</pre>
    </div>
</div>
@endsection
