<?php

use App\Models\Employee;
use App\Models\InventoryItem;
use App\Models\User;
use Database\Seeders\HrisDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->seed(HrisDemoSeeder::class);
    $this->admin = User::where('role', 'super_admin')->firstOrFail();
});

/**
 * @return array<string, mixed>
 */
function isianAset(array $ubah = []): array
{
    return array_merge([
        'code' => 'AST-FOTO-001',
        'name' => 'Laptop Uji',
        'category' => 'elektronik',
        'quantity' => 2,
        'condition' => 'good',
        'status' => 'active',
    ], $ubah);
}

test('HR menambah aset beserta fotonya dan pegawai dapat melihat foto itu', function () {
    $this->actingAs($this->admin)
        ->post('/inventaris/aset', isianAset(['photo' => UploadedFile::fake()->image('laptop.jpg', 800, 600)]))
        ->assertSessionHasNoErrors();

    $item = InventoryItem::where('code', 'AST-FOTO-001')->firstOrFail();

    expect($item->photo_path)->not->toBeNull();
    Storage::disk('local')->assertExists($item->photo_path);

    $employee = Employee::active()->whereNotNull('user_id')->firstOrFail();

    $this->actingAs($employee->user)
        ->get("/inventaris/aset/{$item->id}/foto")
        ->assertOk();
});

test('foto aset dapat diganti dan dihapus lewat pembaruan aset', function () {
    $this->actingAs($this->admin)
        ->post('/inventaris/aset', isianAset(['photo' => UploadedFile::fake()->image('lama.jpg')]));

    $item = InventoryItem::where('code', 'AST-FOTO-001')->firstOrFail();
    $lama = $item->photo_path;

    // Multipart dikirim lewat POST dengan _method, persis seperti frontend.
    $this->actingAs($this->admin)
        ->post("/inventaris/aset/{$item->id}", isianAset([
            '_method' => 'patch',
            'photo' => UploadedFile::fake()->image('baru.png'),
        ]))
        ->assertSessionHasNoErrors();

    $baru = $item->fresh()->photo_path;

    expect($baru)->not->toBe($lama);
    Storage::disk('local')->assertMissing($lama);
    Storage::disk('local')->assertExists($baru);

    $this->actingAs($this->admin)
        ->patch("/inventaris/aset/{$item->id}", isianAset(['remove_photo' => true]))
        ->assertSessionHasNoErrors();

    expect($item->fresh()->photo_path)->toBeNull();
    Storage::disk('local')->assertMissing($baru);

    $this->actingAs($this->admin)
        ->get("/inventaris/aset/{$item->id}/foto")
        ->assertNotFound();
});

test('berkas selain gambar ditolak sebagai foto aset', function () {
    $this->actingAs($this->admin)
        ->post('/inventaris/aset', isianAset([
            'photo' => UploadedFile::fake()->create('manual.pdf', 50, 'application/pdf'),
        ]))
        ->assertSessionHasErrors('photo');

    expect(InventoryItem::where('code', 'AST-FOTO-001')->exists())->toBeFalse();
});

test('menghapus aset ikut membuang fotonya', function () {
    $this->actingAs($this->admin)
        ->post('/inventaris/aset', isianAset(['photo' => UploadedFile::fake()->image('aset.jpg')]));

    $item = InventoryItem::where('code', 'AST-FOTO-001')->firstOrFail();
    $path = $item->photo_path;

    $this->actingAs($this->admin)->delete("/inventaris/aset/{$item->id}")->assertRedirect();

    Storage::disk('local')->assertMissing($path);
});
