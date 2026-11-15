<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RefundStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RefundStatusesController extends Controller
{
    public function index(): View
    {
        $statuses = RefundStatus::orderBy('id')->get();

        return view('admin.settings.refund_statuses', [
            'statuses' => $statuses,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:refund_statuses,slug'],
            'label' => ['required', 'string', 'max:100'],
            'color' => ['required', 'string', 'in:neutral,amber,blue,emerald,red,purple'],
            'is_final' => ['nullable', 'boolean'],
        ]);

        RefundStatus::create([
            'slug' => strtolower($data['slug']),
            'label' => $data['label'],
            'color' => $data['color'],
            'is_final' => (bool) ($data['is_final'] ?? false),
            'is_active' => true,
        ]);

        return back()->with('status', 'Refund status created successfully.');
    }

    public function update(Request $request, RefundStatus $status): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'color' => ['required', 'string', 'in:neutral,amber,blue,emerald,red,purple'],
            'is_final' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $status->update([
            'label' => $data['label'],
            'color' => $data['color'],
            'is_final' => (bool) ($data['is_final'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return back()->with('status', "Refund status '{$status->label}' updated successfully.");
    }
}
