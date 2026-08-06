<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Provisioning akun login untuk karyawan/mitra.
 *
 * Password awal di-set ke nilai yang diberikan atau default "password".
 * HR dapat me-reset password melalui metode resetPassword().
 */
class AccountProvisioningService
{
    /**
     * Buat atau perbarui akun login untuk seorang karyawan.
     *
     * Jika karyawan sudah punya user_id, akun yang ada diperbarui.
     * Jika belum, akun baru dibuat dan user_id di-set.
     *
     * @return array{user: User, generated_password: string}
     */
    public function provision(Employee $employee, ?string $password = null): array
    {
        $generatedPassword = $password ?? 'password';

        $role = $this->resolveRole($employee);

        if ($employee->user_id && $user = User::find($employee->user_id)) {
            // Perbarui data yang mungkin berubah.
            $user->update([
                'name' => $employee->full_name,
                'email' => $employee->email,
                'role' => $role,
                'password' => Hash::make($generatedPassword),
                'must_change_password' => true,
            ]);
        } else {
            $user = User::create([
                'name' => $employee->full_name,
                'email' => $employee->email,
                'role' => $role,
                'password' => Hash::make($generatedPassword),
                'must_change_password' => true,
            ]);

            $employee->update(['user_id' => $user->id]);
        }

        return [
            'user' => $user,
            'generated_password' => $generatedPassword,
        ];
    }

    /**
     * Reset password akun karyawan. Menandai must_change_password = true.
     *
     * @return string Password baru yang di-generate.
     */
    public function resetPassword(Employee $employee): string
    {
        if (! $employee->user_id || ! $user = $employee->user?->fresh()) {
            throw new \RuntimeException('Karyawan ini belum memiliki akun login.');
        }

        $newPassword = Str::random(10);

        $user->update([
            'password' => Hash::make($newPassword),
            'must_change_password' => true,
        ]);

        return $newPassword;
    }

    /**
     * Hapus akun login seorang karyawan.
     */
    public function revoke(Employee $employee): void
    {
        if ($employee->user_id && $user = User::find($employee->user_id)) {
            $employee->update(['user_id' => null]);
            $user->delete();
        }
    }

    /**
     * Tentukan role berdasarkan kategori entitas kerja.
     * Mitra mendapat role "employee" (akses portal mandiri saja).
     */
    private function resolveRole(Employee $employee): string
    {
        return 'employee';
    }
}
