<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\ManualAuthController;
use App\Http\Controllers\AdminController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', [ManualAuthController::class, 'showLogin'])->name('login');
Route::post('/login', [ManualAuthController::class, 'login']);
Route::post('/logout', [ManualAuthController::class, 'logout'])->name('logout');

Route::get('/auth/google', [GoogleController::class, 'redirectToGoogle'])->name('google.login');
Route::get('/auth/google/callback', [GoogleController::class, 'handleGoogleCallback']);

Route::middleware(['auth', 'single.session'])->group(function () {
    Route::get('/dashboard', function (\Illuminate\Http\Request $request) {
        // Admin කෙනෙක් නම් සහ URL එකේ 'view=client' නැත්නම් Admin Dashboard එකට යවන්න
        if (auth()->check() && auth()->user()->is_admin && !$request->has('view')) {
            return redirect()->route('admin.dashboard');
        }
        
        return app()->call([app(DashboardController::class), 'index']);
    })->name('dashboard');
    Route::post('/settings/update', [DashboardController::class, 'updateSettings'])->name('settings.update');

    // WhatsApp QR Connection for Option B (whatsapp-web.js)
    Route::get('/whatsapp/connect', [DashboardController::class, 'showConnect'])->name('whatsapp.connect');
    Route::post('/whatsapp/initiate-connect', [DashboardController::class, 'initiateConnect'])->name('whatsapp.initiate');
    Route::get('/whatsapp/connection-status', [DashboardController::class, 'connectionStatus'])->name('whatsapp.status');
    Route::post('/whatsapp/disconnect', [DashboardController::class, 'disconnectWhatsApp'])->name('whatsapp.disconnect');
});

// Admin Routes
Route::middleware(['auth', 'admin', 'single.session'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'index'])->name('dashboard');
    Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
    Route::post('/users/{id}/update', [AdminController::class, 'updateUser'])->name('users.update');
    Route::post('/users/{id}/delete', [AdminController::class, 'deleteUser'])->name('users.delete');
});