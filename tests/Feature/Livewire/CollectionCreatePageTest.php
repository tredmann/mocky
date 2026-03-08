<?php

use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login', function () {
    $this->get(route('collections.create'))->assertRedirect(route('login'));
});

test('authenticated user can view the create collection page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::collections.create')->assertOk();
});

test('save creates a collection and redirects', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::collections.create')
        ->set('name', 'My API')
        ->set('description', 'Test description')
        ->call('save');

    $this->assertDatabaseHas('endpoint_collections', [
        'user_id' => $user->id,
        'name' => 'My API',
        'description' => 'Test description',
    ]);
});

test('save redirects to the new collection', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::collections.create')
        ->set('name', 'My API')
        ->call('save')
        ->assertRedirect();
});

test('save fails validation when name is empty', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::collections.create')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);
});
