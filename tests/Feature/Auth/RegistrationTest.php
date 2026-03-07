<?php

use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('registration screen redirects to login when users exist', function () {
    User::factory()->create();

    $response = $this->get(route('register'));

    $response->assertRedirect(route('login'));
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('first registered user has verified email', function () {
    $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(User::first()->hasVerifiedEmail())->toBeTrue();
});

test('registration is blocked when users already exist', function () {
    User::factory()->create();

    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'new@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    expect(User::count())->toBe(1);
});
