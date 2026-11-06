<?php

use App\Services\AdventureImportService;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    (new AdventureImportService)->import();
});

it('returns 404 for the admin area when no password hash is configured', function (): void {
    config(['admin.password_hash' => null]);

    $this->get('/verwaltung')->assertNotFound();
});

it('shows the password form and never links the admin area from the home page', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertDontSee('/verwaltung', false);

    $this->get('/verwaltung')
        ->assertOk()
        ->assertSee('Passwort')
        ->assertSee('noindex', false);
});

it('rejects a wrong password and accepts the correct one', function (): void {
    $this->from('/verwaltung')
        ->post('/verwaltung/login', ['password' => 'wrong'])
        ->assertRedirect('/verwaltung')
        ->assertSessionHasErrors('password');

    $this->post('/verwaltung/login', ['password' => 'secret'])
        ->assertRedirect(route('admin.adventures.index'));

    $this->get('/verwaltung/abenteuer')
        ->assertOk()
        ->assertSee('Abenteuer');
});

it('redirects guests from protected admin pages to the login', function (): void {
    $this->get('/verwaltung/abenteuer')
        ->assertRedirect(route('admin.login'));
});

it('can log out of the admin area', function (): void {
    $this->post('/verwaltung/login', ['password' => 'secret'])->assertRedirect();

    $this->post('/verwaltung/logout')
        ->assertRedirect(route('admin.login'));

    $this->get('/verwaltung/abenteuer')
        ->assertRedirect(route('admin.login'));
});

it('verifies the configured hash matches the documented test password', function (): void {
    expect(Hash::check('secret', (string) config('admin.password_hash')))->toBeTrue();
});
