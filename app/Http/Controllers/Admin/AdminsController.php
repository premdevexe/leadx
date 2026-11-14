<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Admin account management (the panel's own sign-in accounts, the
 * `admins` table — distinct from team employees in `users`).
 *
 * Scope kept deliberately small: list, create, edit. Deletion is not
 * offered from the panel so the last admin can never be removed and lock
 * everyone out; that is an explicit database operation.
 */
class AdminsController extends Controller
{
    /**
     * List all admin accounts.
     */
    public function index(): View
    {
        $admins = Admin::orderBy('username')->get();

        return view('admin.admins.index', compact('admins'));
    }

    /**
     * Show the create-admin form.
     */
    public function create(): View
    {
        return view('admin.admins.create', [
            'admin' => new Admin(),
        ]);
    }

    /**
     * Store a new admin account.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Admin::create($data);

        return redirect()->route('admin.admins')->with('status', 'Admin created.');
    }

    /**
     * Show the edit-admin form.
     */
    public function edit(Admin $admin): View
    {
        return view('admin.admins.edit', compact('admin'));
    }

    /**
     * Update an admin account (password optional - blank keeps the current one).
     */
    public function update(Request $request, Admin $admin): RedirectResponse
    {
        $data = $this->validated($request, $admin);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $admin->update($data);

        return redirect()->route('admin.admins')->with('status', 'Admin updated.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Admin $admin = null): array
    {
        $isEdit = $admin !== null;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', $isEdit ? Rule::unique('admins', 'username')->ignore($admin->id) : 'unique:admins,username'],
            'email' => ['required', 'string', 'email', 'max:255', $isEdit ? Rule::unique('admins', 'email')->ignore($admin->id) : 'unique:admins,email'],
            'password' => [$isEdit ? 'sometimes' : 'required', 'required_with:password_confirmation', 'confirmed', Password::min(8)],
        ]);
    }
}
