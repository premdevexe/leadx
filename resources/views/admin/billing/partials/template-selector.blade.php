@php
    $inputName = $inputName ?? 'template';
    $selected = $selected ?? 'classic_navy';
@endphp

<div class="grid gap-3 sm:grid-cols-3" data-template-selector="{{ $inputName }}">
    {{-- 1. Classic Navy & Green --}}
    <label class="tpl-card relative cursor-pointer rounded-xl border-2 p-3 transition-all {{ $selected === 'classic_navy' ? 'border-emerald-600 bg-emerald-50/40 shadow-sm' : 'border-neutral-200 bg-white hover:border-neutral-300' }}">
        <input type="radio" name="{{ $inputName }}" value="classic_navy" class="sr-only tpl-radio" @checked($selected === 'classic_navy')>
        {{-- Mini Invoice Thumbnail --}}
        <div class="rounded-lg border border-neutral-200 bg-white p-2.5 shadow-2xs">
            <div style="border-top: 4px solid #0f294a; padding-top: 6px; display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div style="width: 36px; height: 6px; background: #0f294a; border-radius: 2px;"></div>
                    <div style="width: 24px; height: 3px; background: #cbd5e1; border-radius: 2px; margin-top: 3px;"></div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 7px; font-weight: 700; color: #0f294a;">INVOICE</div>
                    <div style="display: inline-block; padding: 1px 4px; background: #dcfce7; color: #15803d; font-size: 5px; font-weight: 700; border-radius: 99px; margin-top: 2px;">PAID</div>
                </div>
            </div>
            <div style="margin-top: 6px; padding: 4px 5px; background: #f8fafc; border-left: 2px solid #15803d; display: flex; justify-content: space-between;">
                <div style="width: 28px; height: 4px; background: #64748b; border-radius: 1px;"></div>
                <div style="width: 20px; height: 4px; background: #94a3b8; border-radius: 1px;"></div>
            </div>
            <div style="margin-top: 6px; height: 7px; background: #0f294a; border-radius: 1px;"></div>
            <div style="margin-top: 3px; height: 4px; background: #f1f5f9;"></div>
            <div style="margin-top: 2px; height: 4px; background: #e2e8f0;"></div>
            <div style="margin-top: 6px; display: flex; justify-content: space-between; align-items: center;">
                <div style="width: 34px; height: 10px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 2px;"></div>
                <div style="width: 26px; height: 6px; background: #0f294a; border-radius: 1px;"></div>
            </div>
        </div>
        <div class="mt-2.5 flex items-center justify-between">
            <div>
                <div class="text-xs font-semibold text-neutral-900">Classic Navy &amp; Green</div>
                <div class="text-[11px] text-neutral-500">Top navy bar + green accents</div>
            </div>
            <span class="tpl-check inline-flex h-5 w-5 items-center justify-center rounded-full {{ $selected === 'classic_navy' ? 'bg-emerald-600 text-white' : 'bg-neutral-100 text-transparent' }}">
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            </span>
        </div>
    </label>

    {{-- 2. Modern Executive Banner --}}
    <label class="tpl-card relative cursor-pointer rounded-xl border-2 p-3 transition-all {{ $selected === 'modern_emerald' ? 'border-emerald-600 bg-emerald-50/40 shadow-sm' : 'border-neutral-200 bg-white hover:border-neutral-300' }}">
        <input type="radio" name="{{ $inputName }}" value="modern_emerald" class="sr-only tpl-radio" @checked($selected === 'modern_emerald')>
        <div class="rounded-lg border border-neutral-200 bg-white p-2.5 shadow-2xs">
            <div style="background: #0f294a; border-bottom: 3px solid #15803d; border-radius: 3px; padding: 6px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="width: 34px; height: 5px; background: #ffffff; border-radius: 2px;"></div>
                    <div style="width: 20px; height: 3px; background: #94a3b8; border-radius: 2px; margin-top: 2px;"></div>
                </div>
                <div style="font-size: 7px; font-weight: 700; color: #ffffff;">INVOICE</div>
            </div>
            <div style="margin-top: 6px; padding: 4px 5px; background: #f0fdf4; border-left: 3px solid #0f294a; display: flex; justify-content: space-between;">
                <div style="width: 28px; height: 4px; background: #15803d; border-radius: 1px;"></div>
                <div style="width: 20px; height: 4px; background: #64748b; border-radius: 1px;"></div>
            </div>
            <div style="margin-top: 6px; height: 7px; background: #0f294a; border-radius: 1px;"></div>
            <div style="margin-top: 3px; height: 4px; background: #f1f5f9;"></div>
            <div style="margin-top: 2px; height: 4px; background: #e2e8f0;"></div>
            <div style="margin-top: 6px; display: flex; justify-content: space-between; align-items: center;">
                <div style="width: 34px; height: 10px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 2px;"></div>
                <div style="width: 26px; height: 6px; background: #15803d; border-radius: 1px;"></div>
            </div>
        </div>
        <div class="mt-2.5 flex items-center justify-between">
            <div>
                <div class="text-xs font-semibold text-neutral-900">Modern Executive</div>
                <div class="text-[11px] text-neutral-500">Full navy banner + emerald</div>
            </div>
            <span class="tpl-check inline-flex h-5 w-5 items-center justify-center rounded-full {{ $selected === 'modern_emerald' ? 'bg-emerald-600 text-white' : 'bg-neutral-100 text-transparent' }}">
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            </span>
        </div>
    </label>

    {{-- 3. Minimal Clean Print --}}
    <label class="tpl-card relative cursor-pointer rounded-xl border-2 p-3 transition-all {{ $selected === 'minimal_slate' ? 'border-emerald-600 bg-emerald-50/40 shadow-sm' : 'border-neutral-200 bg-white hover:border-neutral-300' }}">
        <input type="radio" name="{{ $inputName }}" value="minimal_slate" class="sr-only tpl-radio" @checked($selected === 'minimal_slate')>
        <div class="rounded-lg border border-neutral-200 bg-white p-2.5 shadow-2xs">
            <div style="border-top: 2px solid #0f294a; border-bottom: 1px solid #cbd5e1; padding: 5px 0; display: flex; justify-content: space-between; align-items: center;">
                <div style="width: 34px; height: 5px; background: #0f294a; border-radius: 2px;"></div>
                <div style="font-size: 7px; font-weight: 700; color: #0f294a;">INVOICE</div>
            </div>
            <div style="margin-top: 6px; padding: 4px 5px; background: #ffffff; border: 1px solid #e2e8f0; border-left: 2px solid #0f294a; display: flex; justify-content: space-between;">
                <div style="width: 28px; height: 4px; background: #475569; border-radius: 1px;"></div>
                <div style="width: 20px; height: 4px; background: #94a3b8; border-radius: 1px;"></div>
            </div>
            <div style="margin-top: 6px; height: 7px; background: #f8fafc; border-top: 1px solid #0f294a; border-bottom: 1.5px solid #0f294a;"></div>
            <div style="margin-top: 3px; height: 4px; background: #f1f5f9;"></div>
            <div style="margin-top: 2px; height: 4px; background: #e2e8f0;"></div>
            <div style="margin-top: 6px; display: flex; justify-content: space-between; align-items: center;">
                <div style="width: 34px; height: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 2px;"></div>
                <div style="width: 26px; height: 6px; background: #0f294a; border-radius: 1px;"></div>
            </div>
        </div>
        <div class="mt-2.5 flex items-center justify-between">
            <div>
                <div class="text-xs font-semibold text-neutral-900">Minimal Clean Print</div>
                <div class="text-[11px] text-neutral-500">Light header, crisp hairlines</div>
            </div>
            <span class="tpl-check inline-flex h-5 w-5 items-center justify-center rounded-full {{ $selected === 'minimal_slate' ? 'bg-emerald-600 text-white' : 'bg-neutral-100 text-transparent' }}">
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            </span>
        </div>
    </label>
</div>

<script>
    (function () {
        document.querySelectorAll('[data-template-selector]').forEach(container => {
            const cards = container.querySelectorAll('.tpl-card');
            cards.forEach(card => {
                const radio = card.querySelector('.tpl-radio');
                radio?.addEventListener('change', () => {
                    cards.forEach(c => {
                        c.classList.remove('border-emerald-600', 'bg-emerald-50/40', 'shadow-sm');
                        c.classList.add('border-neutral-200', 'bg-white');
                        const chk = c.querySelector('.tpl-check');
                        chk?.classList.remove('bg-emerald-600', 'text-white');
                        chk?.classList.add('bg-neutral-100', 'text-transparent');
                    });
                    if (radio.checked) {
                        card.classList.remove('border-neutral-200', 'bg-white');
                        card.classList.add('border-emerald-600', 'bg-emerald-50/40', 'shadow-sm');
                        const chk = card.querySelector('.tpl-check');
                        chk?.classList.remove('bg-neutral-100', 'text-transparent');
                        chk?.classList.add('bg-emerald-600', 'text-white');
                    }
                });
            });
        });
    })();
</script>
