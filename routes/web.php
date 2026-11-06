<?php

use App\Http\Controllers\Admin\LoginController;
use App\Livewire\Admin\AdventureEdit;
use App\Livewire\Admin\AdventureIndex;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::view('/impressum', 'legal.impressum')->name('impressum');
Route::view('/datenschutz', 'legal.datenschutz')->name('datenschutz');

Route::prefix('verwaltung')
    ->middleware('admin.configured')
    ->group(function (): void {
        Route::get('/', [LoginController::class, 'show'])->name('admin.login');
        Route::post('/login', [LoginController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('admin.login.store');
        Route::post('/logout', [LoginController::class, 'destroy'])
            ->middleware('admin.auth')
            ->name('admin.logout');

        Route::middleware('admin.auth')->group(function (): void {
            Route::get('/abenteuer', AdventureIndex::class)->name('admin.adventures.index');
            Route::get('/abenteuer/{slug}', AdventureEdit::class)->name('admin.adventures.edit');
        });
    });
