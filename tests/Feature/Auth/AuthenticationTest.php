<?php

use App\Models\User;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('welcome.user', absolute: false));

    $this->assertAuthenticated();
});

test('the welcome screen greets the user by name and continues to the dashboard', function () {
    $user = User::factory()->create(['name' => 'María Pérez']);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $this->get(route('welcome.user'))
        ->assertOk()
        ->assertSee('María Pérez')
        ->assertSee(route('dashboard'), escape: false);
});

test('the welcome screen continues to the page the user was trying to open', function () {
    $user = User::factory()->create();

    $this->get(route('printers'))->assertRedirect(route('login'));
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('welcome.user', absolute: false));

    $this->get(route('welcome.user'))->assertSee(route('printers'), escape: false);
});

test('guests can not open the welcome screen', function () {
    $this->get(route('welcome.user'))->assertRedirect(route('login'));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrorsIn('email');

    $this->assertGuest();
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});
