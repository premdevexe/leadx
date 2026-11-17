@extends('layouts.admin-dashboard')

@section('title', 'API Documentation — Swagger UI')

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
            <h1 class="text-lg font-semibold tracking-tight text-neutral-900">LeadX Interactive API Documentation (Swagger)</h1>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.api-management.docs.openapi', ['download' => 1]) }}" class="btn btn-outline btn-sm text-xs">
                Download OpenAPI (YAML)
            </a>
            <a href="{{ route('admin.api-management.docs.postman') }}" class="btn btn-primary btn-sm text-xs">
                Download Postman Collection
            </a>
        </div>
    </div>

    {{-- Swagger Container --}}
    <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-xs overflow-hidden">
        <div id="swagger-ui"></div>
    </div>
</div>

<link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui.css">
<style>
    .swagger-ui .topbar { display: none; }
    .swagger-ui .info { margin: 15px 0; }
    .swagger-ui .scheme-container { background: #fafafa; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
</style>
<script src="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui-bundle.js"></script>
<script>
    window.addEventListener('DOMContentLoaded', function () {
        SwaggerUIBundle({
            url: "{{ route('admin.api-management.docs.openapi') }}",
            dom_id: '#swagger-ui',
            deepLinking: true,
            presets: [
                SwaggerUIBundle.presets.apis,
            ],
            layout: "BaseLayout",
            displayRequestDuration: true,
            docExpansion: 'list',
            defaultModelsExpandDepth: 1,
        });
    });
</script>
@endsection
