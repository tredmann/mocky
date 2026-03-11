<?php

use App\Http\Controllers\MockController;
use App\Http\Controllers\SoapController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('inbox', 'pages::inbox')->name('inbox');

    Route::livewire('collections/create', 'pages::collections.create')->name('collections.create');
    Route::livewire('collections/{collection}', 'pages::collections.show')->name('collections.show');
    Route::livewire('collections/{collection}/edit', 'pages::collections.edit')->name('collections.edit');

    Route::livewire('collections/{collection}/endpoints/create', 'pages::endpoints.create')->name('endpoints.create');
    Route::livewire('collections/{collection}/endpoints/{endpoint}', 'pages::endpoints.show')->name('endpoints.show');
    Route::livewire('collections/{collection}/endpoints/{endpoint}/edit', 'pages::endpoints.edit')->name('endpoints.edit');
    Route::livewire('collections/{collection}/endpoints/{endpoint}/logs', 'pages::endpoints.logs')->name('endpoints.logs');
});

Route::any('/mock/{collectionSlug}/{endpointSlug}/{path?}', [MockController::class, 'handle'])
    ->where('path', '.*')
    ->name('mock');

Route::post('/soap/{collectionSlug}/{endpointSlug}', [SoapController::class, 'handle'])
    ->name('soap');

require __DIR__.'/settings.php';
