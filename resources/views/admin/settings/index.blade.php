@extends('layouts.admin-dashboard')

@section('title', 'Settings')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Settings</h1>
            <p class="mt-1 text-sm text-neutral-500">Manage site identity, branding logos, favicon, currency, and payment options.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.api-settings.index') }}" class="btn btn-secondary px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-xs hover:bg-neutral-50">
                <svg class="h-4 w-4 text-neutral-500 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                </svg>
                API Integrations
            </a>
            <a href="{{ route('admin.settings.refund-statuses') }}" class="btn btn-secondary px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-xs hover:bg-neutral-50">
                Refund Statuses
            </a>
            <a href="{{ route('admin.email.index') }}" class="btn btn-secondary px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-xs hover:bg-neutral-50">
                Email / SMTP
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="mt-4 alert alert-success text-sm">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="mt-5 space-y-6">
        @csrf
        @method('PUT')

        {{-- Site identity --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
            <h2 class="text-sm font-semibold text-neutral-900">Site Identity</h2>
            <p class="mt-1 text-xs text-neutral-500">
                The site name and currency shown across the administration panel and reports.
            </p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="site_name" class="form-label">Site Name</label>
                    <input id="site_name" name="site_name" type="text" value="{{ $siteName }}"
                           maxlength="100"
                           placeholder="LeadX"
                           class="form-input mt-1 w-full">
                    @error('site_name') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="currency" class="form-label">Currency</label>
                    <select id="currency" name="currency"
                            class="form-select mt-1 w-full">
                        @foreach ($currencies as $code => $symbol)
                            <option value="{{ $code }}" @selected($code === $currency)>{{ $symbol }} — {{ $code }}</option>
                        @endforeach
                    </select>
                    @error('currency') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Logo & Favicon Upload Center --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-xs">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-neutral-100 pb-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-neutral-900 text-white">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6 6v2m0-14v2"/>
                            </svg>
                        </span>
                        <h2 class="text-base font-semibold text-neutral-900">Logo & Favicon Upload Center</h2>
                    </div>
                    <p class="mt-1 text-xs text-neutral-500">
                        Upload custom logos and browser favicon for site branding. Changes apply immediately across all portal navigation bars, login screens, and browser tabs.
                    </p>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                {{-- 1. Primary Site Logo Box --}}
                <div class="flex flex-col rounded-xl border border-neutral-200 bg-neutral-50 p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-neutral-900">Primary Site Logo</span>
                        @if ($logoUrl)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-neutral">Default Badge</span>
                        @endif
                    </div>
                    <p class="mt-1 text-xs text-neutral-500">Standard brand logo displayed on light backgrounds and portals.</p>

                    <div class="mt-3 flex h-32 w-full items-center justify-center rounded-lg border border-neutral-200 bg-white p-3">
                        <div id="logo-preview-wrapper" class="flex max-h-full max-w-full items-center justify-center">
                            @if ($logoUrl)
                                <img id="logo-preview" src="{{ $logoUrl }}" alt="Primary Logo" class="max-h-24 max-w-full object-contain">
                            @else
                                <div id="logo-placeholder" class="flex flex-col items-center justify-center text-neutral-400">
                                    <svg class="h-8 w-8 text-neutral-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6 6v2m0-14v2"/>
                                    </svg>
                                    <span class="mt-1 text-[11px]">No logo uploaded</span>
                                </div>
                                <img id="logo-preview" src="" alt="Primary Logo Preview" class="hidden max-h-24 max-w-full object-contain">
                            @endif
                        </div>
                    </div>

                    <div class="mt-3 flex-1 flex flex-col justify-end space-y-2">
                        <label class="block">
                            <span class="sr-only">Choose Primary Logo</span>
                            <input type="file" name="logo" id="logo-input" accept="image/png,image/jpeg,image/svg+xml,image/webp"
                                   class="block w-full text-xs text-neutral-500 file:mr-2.5 file:rounded-md file:border-0 file:bg-neutral-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-neutral-800 file:cursor-pointer">
                        </label>
                        <p class="text-[11px] text-neutral-400">PNG, JPG, SVG, WebP (Max 2MB). Rec: 250x60px.</p>
                        @error('logo') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                        @if ($logoUrl)
                            <div class="pt-2 border-t border-neutral-200">
                                <label class="flex items-center gap-1.5 text-xs text-red-600 cursor-pointer hover:text-red-700">
                                    <input type="checkbox" name="remove_logo" value="1" class="rounded border-neutral-300 text-red-600">
                                    <span>Remove current primary logo</span>
                                </label>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- 2. Transparent Logo Box --}}
                <div class="flex flex-col rounded-xl border-2 border-indigo-200 bg-indigo-50 p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="text-sm font-semibold text-neutral-900">Transparent Logo</span>
                            <span class="badge" style="background:var(--c-indigo-100);color:var(--c-indigo-700)">Transparent Box</span>
                        </div>
                        @if ($logoTransparentUrl)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-neutral">Unset</span>
                        @endif
                    </div>
                    <p class="mt-1 text-xs text-neutral-500">Special transparent background logo for dark headers, invoices, and overlays.</p>

                    <div class="mt-2 flex items-center justify-between text-[11px] text-neutral-500">
                        <span>Preview background:</span>
                        <div class="inline-flex rounded-md border border-neutral-200 bg-white p-0.5">
                            <button type="button" onclick="setTransparentBoxBg('checkerboard')" class="rounded px-2 py-0.5 text-[10px] font-medium transition-colors btn btn-primary" id="bg-btn-checkerboard" style="padding:2px 8px">Grid</button>
                            <button type="button" onclick="setTransparentBoxBg('dark')" class="rounded px-2 py-0.5 text-[10px] font-medium transition-colors btn btn-secondary" id="bg-btn-dark" style="padding:2px 8px">Dark</button>
                            <button type="button" onclick="setTransparentBoxBg('light')" class="rounded px-2 py-0.5 text-[10px] font-medium transition-colors btn btn-secondary" id="bg-btn-light" style="padding:2px 8px">Light</button>
                        </div>
                    </div>

                    <div id="transparent-preview-box" class="mt-2 flex h-32 w-full items-center justify-center rounded-lg border border-neutral-200 p-3 checkerboard-bg transition-colors duration-150">
                        <div id="logo-transparent-preview-wrapper" class="flex max-h-full max-w-full items-center justify-center">
                            @if ($logoTransparentUrl)
                                <img id="logo-transparent-preview" src="{{ $logoTransparentUrl }}" alt="Transparent Logo" class="max-h-24 max-w-full object-contain">
                            @else
                                <div id="logo-transparent-placeholder" class="flex flex-col items-center justify-center text-neutral-400">
                                    <svg class="h-8 w-8 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                                    </svg>
                                    <span class="mt-1 text-[11px] font-medium">No transparent logo uploaded</span>
                                </div>
                                <img id="logo-transparent-preview" src="" alt="Transparent Logo Preview" class="hidden max-h-24 max-w-full object-contain">
                            @endif
                        </div>
                    </div>

                    <div class="mt-3 flex-1 flex flex-col justify-end space-y-2">
                        <label class="block">
                            <span class="sr-only">Choose Transparent Logo</span>
                            <input type="file" name="logo_transparent" id="logo-transparent-input" accept="image/png,image/svg+xml,image/webp"
                                   class="block w-full text-xs text-neutral-500 file:mr-2.5 file:rounded-md file:border-0 file:bg-indigo-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-indigo-700 file:cursor-pointer">
                        </label>
                        <p class="text-[11px] text-neutral-500 font-medium">PNG, SVG, WebP with transparency (Max 2MB).</p>
                        @error('logo_transparent') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                        @if ($logoTransparentUrl)
                            <div class="pt-2 border-t border-indigo-100">
                                <label class="flex items-center gap-1.5 text-xs text-red-600 cursor-pointer hover:text-red-700">
                                    <input type="checkbox" name="remove_logo_transparent" value="1" class="rounded border-neutral-300 text-red-600">
                                    <span>Remove transparent logo</span>
                                </label>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- 3. Favicon Upload Box --}}
                <div class="flex flex-col rounded-xl border border-neutral-200 bg-neutral-50 p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-neutral-900">Browser Favicon</span>
                        @if ($faviconUrl)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-neutral">Default</span>
                        @endif
                    </div>
                    <p class="mt-1 text-xs text-neutral-500">Browser tab icon and bookmark shortcut graphic.</p>

                    <div class="mt-3 flex h-32 w-full flex-col justify-center rounded-lg border border-neutral-200 bg-white p-3">
                        <div class="w-full rounded-md border border-neutral-200 bg-neutral-100 p-1.5 text-xs">
                            <div class="flex items-center gap-1.5 rounded bg-white px-2.5 py-1">
                                <div id="favicon-tab-icon-wrapper" class="flex h-4 w-4 shrink-0 items-center justify-center">
                                    @if ($faviconUrl)
                                        <img id="favicon-tab-icon" src="{{ $faviconUrl }}" alt="Favicon" class="h-3.5 w-3.5 object-contain">
                                    @else
                                        <span id="favicon-tab-placeholder" class="text-neutral-400">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-3.5 w-3.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 100-18 9 9 0 000 18zm0 0a8.949 8.949 0 004.95-1.484M12 21a8.949 8.949 0 01-4.95-1.484m9.9 0A8.962 8.962 0 0021 12h-2m-7 9V3m0 0a8.949 8.949 0 014.95 1.484M12 3a8.949 8.949 0 00-4.95 1.484m9.9 0A8.969 8.969 0 0012 3m-7 9H3m14 0a7 7 0 11-14 0 7 7 0 0114 0z" />
                                            </svg>
                                        </span>
                                        <img id="favicon-tab-icon" src="" alt="Favicon Preview" class="hidden h-3.5 w-3.5 object-contain">
                                    @endif
                                </div>
                                <span class="truncate font-medium text-neutral-700 text-[11px]">{{ $siteName }} &mdash; Dashboard</span>
                                <span class="ml-auto text-neutral-400 text-[10px]">&times;</span>
                            </div>
                        </div>
                        <div class="mt-2 flex items-center justify-center">
                            @if ($faviconUrl)
                                <img id="favicon-large-preview" src="{{ $faviconUrl }}" alt="Favicon Large" class="h-10 w-10 rounded border border-neutral-200 bg-neutral-50 p-1 object-contain">
                            @else
                                <div id="favicon-large-placeholder" class="flex h-10 w-10 items-center justify-center rounded border border-neutral-200 bg-neutral-50 text-neutral-400">
                                    <span class="text-xs font-semibold">ICO</span>
                                </div>
                                <img id="favicon-large-preview" src="" alt="Favicon Large Preview" class="hidden h-10 w-10 rounded border border-neutral-200 bg-neutral-50 p-1 object-contain">
                            @endif
                        </div>
                    </div>

                    <div class="mt-3 flex-1 flex flex-col justify-end space-y-2">
                        <label class="block">
                            <span class="sr-only">Choose Favicon</span>
                            <input type="file" name="favicon" id="favicon-input" accept=".ico,image/png,image/svg+xml,image/webp"
                                   class="block w-full text-xs text-neutral-500 file:mr-2.5 file:rounded-md file:border-0 file:bg-neutral-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-neutral-800 file:cursor-pointer">
                        </label>
                        <p class="text-[11px] text-neutral-400">ICO, PNG, SVG (Max 1MB). Rec: 32x32 or 64x64px.</p>
                        @error('favicon') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                        @if ($faviconUrl)
                            <div class="pt-2 border-t border-neutral-200">
                                <label class="flex items-center gap-1.5 text-xs text-red-600 cursor-pointer hover:text-red-700">
                                    <input type="checkbox" name="remove_favicon" value="1" class="rounded border-neutral-300 text-red-600">
                                    <span>Remove custom favicon</span>
                                </label>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Payment methods --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
            <h2 class="text-sm font-semibold text-neutral-900">Payment Methods</h2>
            <p class="mt-1 text-xs text-neutral-500">
                Enabled methods appear on the order creation form. Existing orders keep the method they were created with.
            </p>

            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                @foreach ($paymentMethods as $code => $label)
                    <label class="form-check">
                        <input type="checkbox" name="payment_methods[]" value="{{ $code }}"
                               @checked(in_array($code, $enabledMethods, true))
                               class="rounded border-neutral-300">
                        <span class="text-sm font-medium text-neutral-800">{{ $label }}</span>
                    </label>
                @endforeach
            </div>

            @error('payment_methods') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="btn btn-primary px-5 py-2.5 text-sm font-semibold text-white">
                Save Settings & Branding
            </button>
        </div>
    </form>

    <style>
        .checkerboard-bg {
            background-color: #ffffff;
            background-image:
                linear-gradient(45deg, #e5e7eb 25%, transparent 25%),
                linear-gradient(-45deg, #e5e7eb 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, #e5e7eb 75%),
                linear-gradient(-45deg, transparent 75%, #e5e7eb 75%);
            background-size: 14px 14px;
            background-position: 0 0, 0 7px, 7px -7px, -7px 0px;
        }
    </style>

    <script>
        function bindImagePreview(inputId, imgId, placeholderId) {
            const input = document.getElementById(inputId);
            const img = document.getElementById(imgId);
            const placeholder = document.getElementById(placeholderId);
            if (!input || !img) return;

            input.addEventListener('change', function (e) {
                const file = e.target.files[0];
                if (!file) return;

                const reader = new FileReader();
                reader.onload = function (evt) {
                    img.src = evt.target.result;
                    img.classList.remove('hidden');
                    if (placeholder) {
                        placeholder.classList.add('hidden');
                    }
                };
                reader.readAsDataURL(file);
            });
        }

        bindImagePreview('logo-input', 'logo-preview', 'logo-placeholder');
        bindImagePreview('logo-transparent-input', 'logo-transparent-preview', 'logo-transparent-placeholder');
        bindImagePreview('favicon-input', 'favicon-large-preview', 'favicon-large-placeholder');

        const favInput = document.getElementById('favicon-input');
        if (favInput) {
            favInput.addEventListener('change', function (e) {
                const file = e.target.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = function (evt) {
                    const tabImg = document.getElementById('favicon-tab-icon');
                    const tabPlaceholder = document.getElementById('favicon-tab-placeholder');
                    if (tabImg) {
                        tabImg.src = evt.target.result;
                        tabImg.classList.remove('hidden');
                    }
                    if (tabPlaceholder) {
                        tabPlaceholder.classList.add('hidden');
                    }
                };
                reader.readAsDataURL(file);
            });
        }

        function setTransparentBoxBg(mode) {
            const box = document.getElementById('transparent-preview-box');
            const btnChecker = document.getElementById('bg-btn-checkerboard');
            const btnDark = document.getElementById('bg-btn-dark');
            const btnLight = document.getElementById('bg-btn-light');
            if (!box) return;

            if (mode === 'dark') {
                box.className = 'mt-2 flex h-32 w-full items-center justify-center rounded-lg border border-neutral-700 bg-neutral-900 p-3 shadow-xs transition-colors duration-150';
            } else if (mode === 'light') {
                box.className = 'mt-2 flex h-32 w-full items-center justify-center rounded-lg border border-neutral-200 bg-white p-3 shadow-xs transition-colors duration-150';
            } else {
                box.className = 'mt-2 flex h-32 w-full items-center justify-center rounded-lg border border-neutral-200 p-3 shadow-xs checkerboard-bg transition-colors duration-150';
            }
        }
    </script>
@endsection
