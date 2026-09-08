<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Provisioning akun login untuk karyawan/mitra.
 *
 * Password awal selalu dibangkitkan acak dan hanya ditampilkan sekali kepada
 * HR — tidak ada nilai default yang sama untuk semua akun, karena password
 * seragam berarti setiap akun baru dapat ditebak siapa pun yang pernah
 * menerima akun dari sistem ini. Akun ditandai must_change_password sehingga
 * password titipan HR wajib diganti pada login pertama.
 */
class AccountProvisioningService
{
    /**
     * Panjang password bangkitan.
     */
    private const PASSWORD_LENGTH = 12;

    /**
     * Alfabet tanpa karakter yang rancu bila password didiktekan lisan
     * (0/O, 1/l/I). HR memang menyampaikannya verbal atau lewat chat.
     */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';

    /**
     * Buat atau perbarui akun login untuk seorang karyawan.
     *
     * Jika karyawan sudah punya user_id, akun yang ada diperbarui.
     * Jika belum, akun baru dibuat dan user_id di-set.
     *
     * @return array{user: User, generated_password: string}
     *
     * @throws ValidationException bila email sudah dipakai akun karyawan lain.
     */
    public function provision(Employee $employee, ?string $password = null): array
    {
        $generatedPassword = $password ?? $this->generatePassword();

        $role = $this->resolveRole($employee);

        $this->assertEmailAvailable($employee);

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
     * Selaraskan nama & email akun login dengan data karyawan.
     *
     * Dipanggil saat HR mengubah data induk: tanpa ini email login tetap
     * memakai alamat lama sehingga karyawan tidak bisa masuk dengan email
     * yang tertera di profilnya sendiri.
     *
     * Email yang dikosongkan tidak menghapus email login — `users.email`
     * bersifat NOT NULL dan menjadi identitas masuk, jadi akunnya dibiarkan
     * apa adanya sampai HR mencabutnya secara eksplisit.
     *
     * @throws ValidationException bila email baru sudah dipakai akun lain.
     */
    public function syncProfile(Employee $employee): void
    {
        if (! $employee->user_id || ! $user = User::find($employee->user_id)) {
            return;
        }

        $attributes = ['name' => $employee->full_name];

        if ($employee->email && $employee->email !== $user->email) {
            $this->assertEmailAvailable($employee);
            $attributes['email'] = $employee->email;
        }

        $user->update($attributes);
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

        $newPassword = $this->generatePassword();

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
     * `users.email` unik di level database, sedangkan satu email bisa saja
     * terlanjur terpasang pada dua baris `employees` hasil impor lama.
     * Diperiksa lebih dulu supaya yang muncul adalah pesan validasi, bukan
     * QueryException 500 di tengah pembuatan karyawan.
     *
     * @throws ValidationException
     */
    private function assertEmailAvailable(Employee $employee): void
    {
        $taken = User::where('email', $employee->email)
            ->when($employee->user_id, fn ($query) => $query->whereKeyNot($employee->user_id))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'email' => "Email {$employee->email} sudah dipakai akun lain. Gunakan email berbeda untuk karyawan ini.",
            ]);
        }
    }

    /**
     * Password acak yang aman secara kriptografis (random_int, bukan rand).
     */
    private function generatePassword(): string
    {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;
        $password = '';

        for ($i = 0; $i < self::PASSWORD_LENGTH; $i++) {
            $password .= $alphabet[random_int(0, $max)];
        }

        return $password;
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
