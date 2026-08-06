<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    /**
     * Halaman ganti password.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Auth/ChangePassword', [
            'mustChange' => (bool) $request->user()->must_change_password,
        ]);
    }

    /**
     * Proses ganti password.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $rules = [
            'new_password' => ['required', 'string', 'confirmed', Password::min(8)],
        ];

        // User yang di-force ganti password tidak perlu memasukkan password lama
        // karena password lama-nya mungkin diberikan HR secara verbal.
        if (! $user->must_change_password) {
            $rules['current_password'] = ['required', 'string', 'current_password'];
        }

        $request->validate($rules);

        $user->update([
            'password' => Hash::make($request->new_password),
            'must_change_password' => false,
        ]);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Password berhasil diperbarui.');
    }
}
