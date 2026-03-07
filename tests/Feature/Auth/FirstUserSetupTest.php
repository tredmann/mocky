<?php

use App\Models\User;

test('visitors are redirected to register when no users exist', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('register'));
});

test('login redirects to register when no users exist', function () {
    $response = $this->get(route('login'));

    $response->assertRedirect(route('register'));
});

test('register page is accessible when no users exist', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('no redirect to register when users exist', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
});

test('mock endpoints are not redirected when no users exist', function () {
    $response = $this->get('/mock/some-slug');

    $response->assertStatus(404);
});
