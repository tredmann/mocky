<?php

use App\Models\EndpointCollection;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated user can view the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::dashboard')->assertOk();
});

test('own collections are listed', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Livewire::test('pages::dashboard')->assertSee($collection->name);
});

test('other users collections are not visible', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $other->id]);
    $this->actingAs($user);

    Livewire::test('pages::dashboard')->assertDontSee($collection->name);
});

test('cancelImport resets import state', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->set('showImport', true)
        ->set('importType', 'openapi')
        ->call('cancelImport')
        ->assertSet('showImport', false)
        ->assertSet('importType', 'native')
        ->assertSet('importError', null);
});
