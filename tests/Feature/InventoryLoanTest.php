<?php

use App\Models\Employee;
use App\Models\EmployeeExit;
use App\Models\InventoryItem;
use App\Models\InventoryLoan;
use App\Models\User;
use App\Services\InventoryService;
use Database\Seeders\HrisDemoSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(HrisDemoSeeder::class);
    $this->admin = User::where('role', 'super_admin')->first();
    $this->service = app(InventoryService::class);
});

function asetBaru(int $quantity = 3): InventoryItem
{
    return InventoryItem::create([
        'code' => 'TST-'.fake()->unique()->numerify('####'),
        'name' => 'Aset Uji',
        'category' => 'elektronik',
        'quantity' => $quantity,
        'condition' => 'good',
        'status' => 'active',
    ]);
}

function pinjamanBaru(InventoryItem $item, Employee $employee, string $status = 'requested', int $quantity = 1): InventoryLoan
{
    return InventoryLoan::create([
        'inventory_item_id' => $item->id,
        'employee_id' => $employee->id,
        'quantity' => $quantity,
        'status' => $status,
        'purpose' => 'Keperluan pengujian',
        'due_date' => now()->addDays(7)->toDateString(),
    ]);
}

test('pegawai dapat mengajukan peminjaman untuk dirinya sendiri', function () {
    $employee = Employee::active()->whereNotNull('user_id')->firstOrFail();
    $item = asetBaru();

    $this->actingAs($employee->user)
        ->post('/inventaris-saya', [
            'inventory_item_id' => $item->id,
            'quantity' => 1,
            'purpose' => 'Dinas luar kota',
            'due_date' => now()->addDays(5)->toDateString(),
        ])
        ->assertRedirect();

    $loan = InventoryLoan::where('employee_id', $employee->id)->latest('id')->firstOrFail();

    expect($loan->status)->toBe('requested')
        // Pengajuan belum menahan stok — baru persetujuan yang mengunci unit.
        ->and($this->service->availableQuantity($item))->toBe(3);
});

test('persetujuan mengunci stok dan pengembalian melepasnya kembali', function () {
    $item = asetBaru(2);
    $employee = Employee::active()->firstOrFail();
    $loan = pinjamanBaru($item, $employee, quantity: 2);

    $this->service->approve($loan, $this->admin->id);
    expect($this->service->availableQuantity($item->fresh()))->toBe(0);

    $this->service->handOver($loan->fresh(), 'good');
    expect($this->service->availableQuantity($item->fresh()))->toBe(0);

    $this->service->returnItem($loan->fresh(), 'good');
    expect($this->service->availableQuantity($item->fresh()))->toBe(2);
});

test('persetujuan ditolak bila stok tidak mencukupi', function () {
    $item = asetBaru(1);
    $employees = Employee::active()->limit(2)->get();

    $pertama = pinjamanBaru($item, $employees[0]);
    $kedua = pinjamanBaru($item, $employees[1]);

    $this->service->approve($pertama, $this->admin->id);

    expect(fn () => $this->service->approve($kedua, $this->admin->id))
        ->toThrow(ValidationException::class);

    expect($kedua->fresh()->status)->toBe('requested');
});

test('transisi status yang melompati alur ditolak', function () {
    $item = asetBaru();
    $loan = pinjamanBaru($item, Employee::active()->firstOrFail());

    // requested tidak boleh langsung menjadi returned.
    expect(fn () => $this->service->returnItem($loan, 'good'))
        ->toThrow(ValidationException::class);

    // Pinjaman yang sudah ditolak tidak dapat dihidupkan lagi.
    $this->service->reject($loan, $this->admin->id, 'Stok dialokasikan tim lain');

    expect(fn () => $this->service->approve($loan->fresh(), $this->admin->id))
        ->toThrow(ValidationException::class);
});

test('pengembalian rusak berat menurunkan kondisi aset dan menariknya dari peredaran', function () {
    $item = asetBaru(1);
    $loan = pinjamanBaru($item, Employee::active()->firstOrFail());

    $this->service->approve($loan, $this->admin->id);
    $this->service->handOver($loan->fresh(), 'good');
    $this->service->returnItem($loan->fresh(), 'damaged', 'Layar pecah saat perjalanan');

    $item->refresh();

    expect($item->condition)->toBe('damaged')
        ->and($item->status)->toBe('maintenance');
});

test('barang hilang mengurangi jumlah unit yang dimiliki', function () {
    $item = asetBaru(2);
    $loan = pinjamanBaru($item, Employee::active()->firstOrFail());

    $this->service->approve($loan, $this->admin->id);
    $this->service->handOver($loan->fresh(), 'good');
    $this->service->markLost($loan->fresh(), 'Hilang di lokasi proyek');

    expect($item->fresh()->quantity)->toBe(1);
});

test('aset dengan pinjaman berjalan tidak dapat dihapus', function () {
    $item = asetBaru();
    $loan = pinjamanBaru($item, Employee::active()->firstOrFail());
    $this->service->approve($loan, $this->admin->id);

    $this->actingAs($this->admin)
        ->delete("/inventaris/aset/{$item->id}")
        ->assertRedirect();

    expect(InventoryItem::find($item->id))->not->toBeNull();
});

test('jumlah unit tidak dapat diturunkan di bawah unit yang sedang dipinjam', function () {
    $item = asetBaru(3);
    $loan = pinjamanBaru($item, Employee::active()->firstOrFail(), quantity: 2);
    $this->service->approve($loan, $this->admin->id);

    $this->actingAs($this->admin)
        ->patch("/inventaris/aset/{$item->id}", [
            'code' => $item->code,
            'name' => $item->name,
            'category' => 'elektronik',
            'quantity' => 1,
            'condition' => 'good',
            'status' => 'active',
        ])
        ->assertRedirect();

    expect($item->fresh()->quantity)->toBe(3);
});

test('pegawai tidak dapat membatalkan pengajuan milik orang lain', function () {
    $pemilik = Employee::active()->firstOrFail();
    $penyusup = Employee::active()->whereNotNull('user_id')->where('id', '!=', $pemilik->id)->firstOrFail();

    $loan = pinjamanBaru(asetBaru(), $pemilik);

    $this->actingAs($penyusup->user)
        ->delete("/inventaris-saya/{$loan->id}")
        ->assertForbidden();

    expect(InventoryLoan::find($loan->id))->not->toBeNull();
});

test('konsol inventaris tertutup untuk peran selain super admin', function () {
    $manager = User::where('role', 'manager')->firstOrFail();

    $this->actingAs($manager)->get('/inventaris')->assertForbidden();
});

test('portal peminjaman mandiri terbuka untuk pegawai biasa', function () {
    $employee = Employee::active()->whereNotNull('user_id')->firstOrFail();

    $this->actingAs($employee->user)->get('/inventaris-saya')->assertOk();
});

test('proses keluar tertahan bila masih ada pinjaman yang belum tuntas', function () {
    $employee = Employee::active()->whereNull('user_id')->whereDoesntHave('exit')->firstOrFail();

    // Bersihkan pinjaman bawaan seeder agar hanya pinjaman uji yang menahan.
    $employee->inventoryLoans()->delete();

    $loan = pinjamanBaru(asetBaru(), $employee);
    $this->service->approve($loan, $this->admin->id);

    $exit = EmployeeExit::create([
        'employee_id' => $employee->id,
        'exit_type' => 'resign',
        'last_working_date' => now()->toDateString(),
        'status' => 'draft',
    ]);

    $this->actingAs($this->admin)
        ->patch("/proses-keluar/{$exit->id}/status", ['status' => 'completed'])
        ->assertRedirect();

    expect($exit->fresh()->status)->toBe('draft')
        ->and($exit->fresh()->paklaring_number)->toBeNull();

    // Setelah barang kembali, clearance lolos dan paklaring terbit.
    $this->service->handOver($loan->fresh(), 'good');
    $this->service->returnItem($loan->fresh(), 'good');

    $this->actingAs($this->admin)
        ->patch("/proses-keluar/{$exit->id}/status", ['status' => 'completed'])
        ->assertRedirect();

    expect($exit->fresh()->status)->toBe('completed')
        ->and($exit->fresh()->paklaring_number)->not->toBeNull();
});
