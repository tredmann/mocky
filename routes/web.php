<?php

use App\Http\Controllers\MockController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('endpoints/create', 'pages::endpoints.create')->name('endpoints.create');
    Route::livewire('endpoints/{endpoint}', 'pages::endpoints.show')->name('endpoints.show');
    Route::livewire('endpoints/{endpoint}/edit', 'pages::endpoints.edit')->name('endpoints.edit');
    Route::livewire('endpoints/{endpoint}/logs', 'pages::endpoints.logs')->name('endpoints.logs');
});

Route::any('/mock/{endpoint}/{path?}', [MockController::class, 'handle'])
    ->where('path', '.*')
    ->name('mock');

require __DIR__.'/settings.php';
