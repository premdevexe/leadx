@extends('layouts.admin-dashboard')

@section('title', 'Settings')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Settings</h1>
            <p class="mt-1 text-sm text-neutral-500">Manage site identity, branding logos, favicon, currency, and payment options.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.api-settings.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-xs hover:bg-neutral-50">
                <svg class="h-4 w-4 text-neutral-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                </svg>
                API Integrations
            </a>
            <a href="{{ route('admin.settings.refund-statuses') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-xs hover:bg-neutral-50">
                Refund Statuses
            </a>
            <a href="{{ route('admin.email.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-xs hover:bg-neutral-50">
                Email / SMTP
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
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
                    <label for="site_name" class="text-sm font-medium text-neutral-800">Site Name</label>
                    <input id="site_name" name="site_name" type="text" value="{{ $siteName }}"
                           maxlength="100"
                           placeholder="LeadX"
                           class="mt-1 w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                    @error('site_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="currency" class="text-sm font-medium text-neutral-800">Currency</label>
                    <select id="currency" name="currency"
                            class="mt-1 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200">
                        @foreach ($currencies as $code => $symbol)
                            <option value="{{ $code }}" @selected($code === $currency)>{{ $symbol }} — {{ $code }}</option>
                        @endforeach
                    </select>
                    @error('currency') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
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
                <div class="flex flex-col rounded-xl border border-neutral-200 bg-neutral-50/50 p-4 transition-all hover:border-neutral-300">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-neutral-900">Primary Site Logo</span>
                        @if ($logoUrl)
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-800">Active</span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-neutral-200 px-2 py-0.5 text-[10px] font-medium text-neutral-600">Default Badge</span>
                        @endif
                    </div>
                    <p class="mt-1 text-xs text-neutral-500">Standard brand logo displayed on light backgrounds and portals.</p>

                    {{-- Preview Container --}}
                    <div class="mt-3 flex h-32 w-full items-center justify-center rounded-lg border border-neutral-200 bg-white p-3 shadow-2xs">
                        <div id="logo-preview-wrapper" class="flex max-h-full max-w-full items-center justify-center">
                            @if ($logoUrl)
                                <img id="logo-preview" src="{{ $logoUrl }}" alt="Primary Logo" class="max-h-24 max-w-full object-contain">
                            @else
                                <div id="logo-placeholder" class="flex flex-col items-center justify-center text-neutral-400">
                                    <svg class="h-8 w-8 text-neutral-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <span class="mt-1 text-[11px]">No logo uploaded</span>
                                </div>
                                <img id="logo-preview" src="" alt="Primary Logo Preview" class="hidden max-h-24 max-w-full object-contain">
                            @endif
                        </div>
                    </div>

                    {{-- Upload Controls --}}
                    <div class="mt-3 flex-1 flex flex-col justify-end space-y-2">
                        <label class="block">
                            <span class="sr-only">Choose Primary Logo</span>
                            <input type="file" name="logo" id="logo-input" accept="image/png,image/jpeg,image/svg+xml,image/webp"
                                   class="block w-full text-xs text-neutral-500 file:mr-2.5 file:rounded-md file:border-0 file:bg-neutral-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-neutral-800 file:cursor-pointer">
                        </label>
                        <p class="text-[11px] text-neutral-400">PNG, JPG, SVG, WebP (Max 2MB). Rec: 250×60px.</p>
                        @error('logo') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                        @if ($logoUrl)
                            <div class="pt-2 border-t border-neutral-200">
                                <label class="flex items-center gap-1.5 text-xs text-red-600 cursor-pointer hover:text-red-700">
                                    <input type="checkbox" name="remove_logo" value="1" class="rounded border-neutral-300 text-red-600 focus:ring-red-500">
                                    <span>Remove current primary logo</span>
                                </label>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- 2. Transparent Logo Box Section (Specially Highlighted) --}}
                <div class="flex flex-col rounded-xl border-2 border-indigo-200 bg-indigo-50/20 p-4 shadow-xs transition-all hover:border-indigo-300">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="text-sm font-semibold text-neutral-900">Transparent Logo</span>
                            <span class="inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold text-indigo-800">Transparent Box</span>
                        </div>
                        @if ($logoTransparentUrl)
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-800">Active</span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-neutral-200 px-2 py-0.5 text-[10px] font-medium text-neutral-600">Unset</span>
                        @endif
                    </div>
                    <p class="mt-1 text-xs text-neutral-500">Special transparent background logo for dark headers, invoices, and overlays.</p>

                    {{-- Background Switcher Toolbar for Transparent Logo --}}
                    <div class="mt-2 flex items-center justify-between text-[11px] text-neutral-500">
                        <span>Preview background:</span>
                        <div class="inline-flex rounded-md border border-neutral-200 bg-white p-0.5 shadow-2xs">
                            <button type="button" onclick="setTransparentBoxBg('checkerboard')" class="rounded px-2 py-0.5 text-[10px] font-medium transition-colors bg-neutral-900 text-white" id="bg-btn-checkerboard" title="Checkerboard transparency grid">Grid</button>
                            <button type="button" onclick="setTransparentBoxBg('dark')" class="rounded px-2 py-0.5 text-[10px] font-medium text-neutral-600 hover:text-neutral-900 transition-colors" id="bg-btn-dark" title="Dark Header Preview (#171717)">Dark</button>
                            <button type="button" onclick="setTransparentBoxBg('light')" class="rounded px-2 py-0.5 text-[10px] font-medium text-neutral-600 hover:text-neutral-900 transition-colors" id="bg-btn-light" title="White Preview (#FFFFFF)">Light</button>
                        </div>
                    </div>

                    {{-- Transparent Preview Container (with CSS Checkerboard) --}}
                    <div id="transparent-preview-box" class="mt-2 flex h-32 w-full items-center justify-center rounded-lg border border-neutral-200 p-3 shadow-2xs checkerboard-bg transition-colors duration-150">
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

                    {{-- Upload Controls --}}
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
                                    <input type="checkbox" name="remove_logo_transparent" value="1" class="rounded border-neutral-300 text-red-600 focus:ring-red-500">
                                    <span>Remove transparent logo</span>
                                </label>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- 3. Favicon Upload Box --}}
                <div class="flex flex-col rounded-xl border border-neutral-200 bg-neutral-50/50 p-4 transition-all hover:border-neutral-300">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-neutral-900">Browser Favicon</span>
                        @if ($faviconUrl)
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-800">Active</span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-neutral-200 px-2 py-0.5 text-[10px] font-medium text-neutral-600">Default</span>
                        @endif
                    </div>
                    <p class="mt-1 text-xs text-neutral-500">Browser tab icon and bookmark shortcut graphic.</p>

                    {{-- Simulated Browser Tab Preview --}}
                    <div class="mt-3 flex h-32 w-full flex-col justify-center rounded-lg border border-neutral-200 bg-white p-3 shadow-2xs">
                        <div class="w-full rounded-md border border-neutral-200 bg-neutral-100 p-1.5 text-xs">
                            <div class="flex items-center gap-1.5 rounded bg-white px-2.5 py-1 shadow-2xs">
                                <div id="favicon-tab-icon-wrapper" class="flex h-4 w-4 shrink-0 items-center justify-center">
                                    @if ($faviconUrl)
                                        <img id="favicon-tab-icon" src="{{ $faviconUrl }}" alt="Favicon" class="h-3.5 w-3.5 object-contain">
                                    @else
                                        <span id="favicon-tab-placeholder" class="text-[10px]">🌐</span>
                                        <img id="favicon-tab-icon" src="" alt="Favicon Preview" class="hidden h-3.5 w-3.5 object-contain">
                                    @endif
                                </div>
                                <span class="truncate font-medium text-neutral-700 text-[11px]">{{ $siteName }} &mdash; Dashboard</span>
                                <span class="ml-auto text-neutral-400 text-[10px]">&times;</span>
                            </div>
                        </div>
                        <div class="mt-2 flex items-center justify-center">
                            @if ($faviconUrl)
                                <img id="favicon-large-preview" src="{{ $faviconUrl }}" alt="Favicon Large" class="h-10 w-10 rounded border border-neutral-200 bg-neutral-50 p-1 object-contain shadow-2xs">
                            @else
                                <div id="favicon-large-placeholder" class="flex h-10 w-10 items-center justify-center rounded border border-neutral-200 bg-neutral-50 text-neutral-400">
                                    <span class="text-xs font-semibold">ICO</span>
                                </div>
                                <img id="favicon-large-preview" src="" alt="Favicon Large Preview" class="hidden h-10 w-10 rounded border border-neutral-200 bg-neutral-50 p-1 object-contain shadow-2xs">
                            @endif
                        </div>
                    </div>

                    {{-- Upload Controls --}}
                    <div class="mt-3 flex-1 flex flex-col justify-end space-y-2">
                        <label class="block">
                            <span class="sr-only">Choose Favicon</span>
                            <input type="file" name="favicon" id="favicon-input" accept=".ico,image/png,image/svg+xml,image/webp"
                                   class="block w-full text-xs text-neutral-500 file:mr-2.5 file:rounded-md file:border-0 file:bg-neutral-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-neutral-800 file:cursor-pointer">
                        </label>
                        <p class="text-[11px] text-neutral-400">ICO, PNG, SVG (Max 1MB). Rec: 32×32 or 64×64px.</p>
                        @error('favicon') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                        @if ($faviconUrl)
                            <div class="pt-2 border-t border-neutral-200">
                                <label class="flex items-center gap-1.5 text-xs text-red-600 cursor-pointer hover:text-red-700">
                                    <input type="checkbox" name="remove_favicon" value="1" class="rounded border-neutral-300 text-red-600 focus:ring-red-500">
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
                    <label class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2 text-sm {{ in_array($code, $enabledMethods, true) ? 'border-neutral-400 bg-neutral-50' : '' }}">
                        <input type="checkbox" name="payment_methods[]" value="{{ $code }}"
                               @checked(in_array($code, $enabledMethods, true))
                               class="rounded border-neutral-300">
                        <span class="font-medium text-neutral-800">{{ $label }}</span>
                    </label>
                @endforeach
            </div>

            @error('payment_methods') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="w-full sm:w-auto rounded-lg bg-neutral-900 px-5 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-neutral-800 transition-colors">
                Save Settings & Branding
            </button>
        </div>
    </form>

    {{-- Styling for Checkerboard Pattern --}}
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

    {{-- Live Client-side Image Preview Script --}}
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

        // Also update favicon mini tab icon
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

        // Transparent Box Background Toggle
        function setTransparentBoxBg(mode) {
            const box = document.getElementById('transparent-preview-box');
            const btnChecker = document.getElementById('bg-btn-checkerboard');
            const btnDark = document.getElementById('bg-btn-dark');
            const btnLight = document.getElementById('bg-btn-light');
            if (!box) return;

            [btnChecker, btnDark, btnLight].forEach(btn => {
                if (btn) {
                    btn.classList.remove('bg-neutral-900', 'text-white');
                    btn.classList.add('text-neutral-600');
                }
            });

            if (mode === 'dark') {
                box.className = 'mt-2 flex h-32 w-full items-center justify-center rounded-lg border border-neutral-700 bg-neutral-900 p-3 shadow-2xs transition-colors duration-150';
                if (btnDark) {
                    btnDark.classList.add('bg-neutral-900', 'text-white');
                    btnDark.classList.remove('text-neutral-600');
                }
            } else if (mode === 'light') {
                box.className = 'mt-2 flex h-32 w-full items-center justify-center rounded-lg border border-neutral-200 bg-white p-3 shadow-2xs transition-colors duration-150';
                if (btnLight) {
                    btnLight.classList.add('bg-neutral-900', 'text-white');
                    btnLight.classList.remove('text-neutral-600');
                }
            } else {
                box.className = 'mt-2 flex h-32 w-full items-center justify-center rounded-lg border border-neutral-200 p-3 shadow-2xs checkerboard-bg transition-colors duration-150';
                if (btnChecker) {
                    btnChecker.classList.add('bg-neutral-900', 'text-white');
                    btnChecker.classList.remove('text-neutral-600');
                }
            }
        }
    </script>
@endsection
