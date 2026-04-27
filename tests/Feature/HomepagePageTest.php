<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Services\HomepageSettings;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    HomepageSettings::clear();
});

afterEach(function () {
    HomepageSettings::clear();
});

test('un admin puede ver la página de homepage', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(route('homepage.index'))
        ->assertOk()
        ->assertSeeText('Imagen de fondo del hero')
        ->assertSeeText('Imágenes de líneas de trabajo');
});

test('un usuario no-admin recibe 403', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(route('homepage.index'))
        ->assertForbidden();
})->with([
    UserRole::Owner,
    UserRole::Seller,
    UserRole::Warehouse,
]);

test('un guest es redirigido a login', function () {
    $this->get(route('homepage.index'))
        ->assertRedirect(route('login'));
});

test('rechaza archivos que no son imagen', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    Livewire::actingAs($admin)
        ->test('pages::homepage.index')
        ->set('hero', UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'))
        ->call('save')
        ->assertHasErrors(['hero']);
});

test('admin puede actualizar info de contacto y persiste en JSON', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    Livewire::actingAs($admin)
        ->test('pages::homepage.index')
        ->set('whatsappMain', '51987654321')
        ->set('whatsappMainDisplay', '+51 987 654 321')
        ->set('emailSales', 'nuevo@morrav.test')
        ->set('emailContracts', '')
        ->call('save')
        ->assertHasNoErrors();

    $stored = HomepageSettings::all();

    expect($stored['whatsapp_main'])->toBe('51987654321');
    expect($stored['whatsapp_main_display'])->toBe('+51 987 654 321');
    expect($stored['email_sales'])->toBe('nuevo@morrav.test');
    expect($stored['email_contracts'])->toBe('');
});

test('admin puede agregar tiendas hasta el máximo de 3', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    // Empezamos con los 3 defaults
    Livewire::actingAs($admin)
        ->test('pages::homepage.index')
        ->assertSet('stores', fn ($s) => count($s) === 3)
        ->call('addStore')
        ->assertSet('stores', fn ($s) => count($s) === 3); // no debe pasar de 3
});

test('admin puede quitar tiendas hasta el mínimo de 1', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    Livewire::actingAs($admin)
        ->test('pages::homepage.index')
        ->call('removeStore', 0)
        ->assertSet('stores', fn ($s) => count($s) === 2)
        ->call('removeStore', 0)
        ->assertSet('stores', fn ($s) => count($s) === 1)
        ->call('removeStore', 0)
        ->assertSet('stores', fn ($s) => count($s) === 1); // no debe quedar en cero
});

test('valida campos obligatorios de tienda al guardar', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    Livewire::actingAs($admin)
        ->test('pages::homepage.index')
        ->set('stores.0.name', '')
        ->set('stores.0.address', '')
        ->call('save')
        ->assertHasErrors(['stores.0.name', 'stores.0.address']);
});

test('admin guarda tiendas y se persisten en JSON', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    Livewire::actingAs($admin)
        ->test('pages::homepage.index')
        ->set('stores.0.name', 'TIENDA NUEVA')
        ->set('stores.0.address', 'Av. Ficticia 123')
        ->call('save')
        ->assertHasNoErrors();

    $stored = HomepageSettings::all();

    expect($stored['stores'][0]['name'])->toBe('TIENDA NUEVA');
    expect($stored['stores'][0]['address'])->toBe('Av. Ficticia 123');
});

test('welcome page renderiza con los datos guardados', function () {
    HomepageSettings::save([
        'whatsapp_main' => '51999111222',
        'whatsapp_main_display' => '+51 999 111 222',
        'email_sales' => 'demo@test.test',
        'email_contracts' => '',
        'stores' => [[
            'name' => 'TIENDA SOLO UNA',
            'badge' => 'Única',
            'address' => 'Av. Demo 1',
            'hours' => 'Lun – Sáb · 10:00 – 19:00',
            'phone' => '',
            'whatsapp' => '',
            'lat' => -15.5,
            'lng' => -70.13,
        ]],
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSeeText('TIENDA SOLO UNA')
        ->assertSeeText('+51 999 111 222')
        ->assertSeeText('demo@test.test')
        ->assertSeeText('1 PUNTO EN LA CIUDAD')
        ->assertDontSeeText('TIENDA CENTRAL'); // no debe mostrar defaults
});

test('store con show_whatsapp=false oculta el botón aunque tenga número', function () {
    HomepageSettings::save([
        'whatsapp_main' => '51111111111',
        'whatsapp_main_display' => '+51 111 111 111',
        'email_sales' => 'a@b.test',
        'email_contracts' => '',
        'stores' => [[
            'name' => 'TIENDA OCULTA',
            'badge' => 'X',
            'address' => 'Av. Demo',
            'hours' => 'L-S',
            'phone' => '',
            'whatsapp' => '51999000111',
            'show_whatsapp' => false,
            'lat' => -15.5,
            'lng' => -70.13,
        ]],
    ]);

    $content = $this->get('/')->assertOk()->getContent();

    // Toggle apagado → no aparece el botón aunque haya número
    expect(substr_count($content, 'wa.me/51999000111'))->toBe(0);
    // Globales (card contacto + footer + flotante) siguen apareciendo
    expect(substr_count($content, 'wa.me/51111111111'))->toBe(3);
});

test('store con show_whatsapp=true muestra el botón con su número', function () {
    HomepageSettings::save([
        'whatsapp_main' => '51111111111',
        'whatsapp_main_display' => '+51 111 111 111',
        'email_sales' => 'a@b.test',
        'email_contracts' => '',
        'stores' => [[
            'name' => 'TIENDA VISIBLE',
            'badge' => 'X',
            'address' => 'Av. Demo',
            'hours' => 'L-S',
            'phone' => '',
            'whatsapp' => '51999000111',
            'show_whatsapp' => true,
            'lat' => -15.5,
            'lng' => -70.13,
        ]],
    ]);

    $content = $this->get('/')->assertOk()->getContent();

    expect(substr_count($content, 'wa.me/51999000111'))->toBe(1); // botón de tienda
    expect(substr_count($content, 'wa.me/51111111111'))->toBe(3); // globales
});

test('toggle show_whatsapp persiste tras guardar', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    Livewire::actingAs($admin)
        ->test('pages::homepage.index')
        ->set('stores.0.show_whatsapp', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(HomepageSettings::all()['stores'][0]['show_whatsapp'])->toBeFalse();
});

test('admin guarda imágenes en public/ y deja los demás slots intactos', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $heroPath = public_path('Hero-parallax.png');
    $hogarPath = public_path('lineas/hogar.jpg');

    // Snapshot del estado actual para restaurarlo después.
    $heroBackup = file_exists($heroPath) ? file_get_contents($heroPath) : null;
    $hogarBackup = file_exists($hogarPath) ? file_get_contents($hogarPath) : null;

    try {
        Livewire::actingAs($admin)
            ->test('pages::homepage.index')
            ->set('hero', UploadedFile::fake()->image('hero.jpg', 1200, 600))
            ->set('hogar', UploadedFile::fake()->image('h.jpg', 600, 450))
            ->call('save')
            ->assertHasNoErrors();

        expect(file_exists($heroPath))->toBeTrue();
        expect(file_exists($hogarPath))->toBeTrue();
        expect(filesize($heroPath))->toBeGreaterThan(0);
        expect(filesize($hogarPath))->toBeGreaterThan(0);
    } finally {
        if ($heroBackup !== null) {
            file_put_contents($heroPath, $heroBackup);
        } elseif (file_exists($heroPath)) {
            @unlink($heroPath);
        }
        if ($hogarBackup !== null) {
            file_put_contents($hogarPath, $hogarBackup);
        } elseif (file_exists($hogarPath)) {
            @unlink($hogarPath);
        }
    }
});
