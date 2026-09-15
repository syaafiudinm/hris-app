<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Validation\Rule;

/**
 * Aturan validasi & bentuk tampilan data diri karyawan — dipakai bersama oleh
 * halaman Data Diri milik karyawan dan form data induk milik HR.
 */
class EmployeeProfile
{
    /**
     * Isian data diri. Karyawan wajib mengisi semuanya; HR boleh
     * mengosongkan karena datanya memang dilengkapi karyawan belakangan.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(?Employee $employee, bool $required): array
    {
        $presence = $required ? 'required' : 'nullable';

        return [
            'ktp_number' => [$presence, 'digits:16', Rule::unique('employees', 'ktp_number')->ignore($employee)],
            'birth_place' => [$presence, 'string', 'max:100'],
            'birth_date' => [$presence, 'date', 'before:today'],
            'gender' => [$presence, Rule::in(array_keys(Employee::GENDERS))],
            'religion' => [$presence, Rule::in(array_keys(Employee::RELIGIONS))],
            'marital_status' => [$presence, Rule::in(array_keys(Employee::MARITAL_STATUSES))],
            'dependents_count' => [$presence, 'integer', 'min:0', 'max:20'],
            'ktp_address' => [$presence, 'string', 'max:500'],
            'domicile_address' => [$presence, 'string', 'max:500'],
            'emergency_contact_name' => [$presence, 'string', 'max:150'],
            'emergency_contact_relation' => [$presence, 'string', 'max:50'],
            'emergency_contact_phone' => [$presence, 'string', 'max:30', 'regex:/^[0-9+\-\s]{8,30}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'ktp_number.digits' => 'No KTP harus 16 digit angka.',
            'ktp_number.unique' => 'No KTP ini sudah terdaftar pada karyawan lain.',
            'birth_date.before' => 'Tanggal lahir harus sebelum hari ini.',
            'emergency_contact_phone.regex' => 'Nomor kontak darurat hanya boleh berisi angka.',
        ];
    }

    /**
     * Nilai mentah untuk mengisi form.
     *
     * @return array<string, mixed>
     */
    public static function formValues(Employee $employee): array
    {
        return [
            'ktp_number' => $employee->ktp_number,
            'birth_place' => $employee->birth_place,
            'birth_date' => $employee->birth_date?->toDateString(),
            'gender' => $employee->gender,
            'religion' => $employee->religion,
            'marital_status' => $employee->marital_status,
            'dependents_count' => $employee->dependents_count,
            'ktp_address' => $employee->ktp_address,
            'domicile_address' => $employee->domicile_address,
            'emergency_contact_name' => $employee->emergency_contact_name,
            'emergency_contact_relation' => $employee->emergency_contact_relation,
            'emergency_contact_phone' => $employee->emergency_contact_phone,
        ];
    }

    /**
     * Nilai siap baca untuk halaman detail.
     *
     * @return array<string, mixed>
     */
    public static function present(Employee $employee): array
    {
        return [
            'ktpNumber' => $employee->ktp_number,
            'birth' => $employee->birth_place || $employee->birth_date
                ? trim(($employee->birth_place ?? '-').', '.($employee->birth_date?->translatedFormat('d F Y') ?? '-'))
                : null,
            'gender' => Employee::GENDERS[$employee->gender] ?? null,
            'religion' => Employee::RELIGIONS[$employee->religion] ?? null,
            'maritalStatus' => Employee::MARITAL_STATUSES[$employee->marital_status] ?? null,
            'dependentsCount' => $employee->dependents_count,
            'ktpAddress' => $employee->ktp_address,
            'domicileAddress' => $employee->domicile_address,
            'emergencyContactName' => $employee->emergency_contact_name,
            'emergencyContactRelation' => $employee->emergency_contact_relation,
            'emergencyContactPhone' => $employee->emergency_contact_phone,
            'missingFields' => $employee->missingProfileFields(),
            'missingDocuments' => $employee->missingRequiredDocuments(),
            'completion' => $employee->profileCompletion(),
        ];
    }

    /**
     * @return array<string, list<array{value: string, label: string}>>
     */
    public static function options(): array
    {
        $pairs = fn (array $labels) => array_map(
            fn (string $value, string $label) => ['value' => $value, 'label' => $label],
            array_keys($labels),
            $labels,
        );

        return [
            'genders' => $pairs(Employee::GENDERS),
            'religions' => $pairs(Employee::RELIGIONS),
            'maritalStatuses' => $pairs(Employee::MARITAL_STATUSES),
        ];
    }
}
