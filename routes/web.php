<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Livewire\AuditLogs\Index as AuditLogsIndex;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Categories\Index as CategoriesIndex;
use App\Livewire\Dashboard;
use App\Livewire\Products\Index as ProductsIndex;
use App\Livewire\StockMovements\Index as StockMovementsIndex;
use App\Livewire\Suppliers\Index as SuppliersIndex;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('login', Login::class)->name('login');
    Route::get('register', Register::class)->name('register');
    Route::get('forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('reset-password/{token}', ResetPassword::class)->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Route::get('dashboard', Dashboard::class)->name('dashboard');
    Route::get('categories', CategoriesIndex::class)->name('categories.index');
    Route::get('products', ProductsIndex::class)->name('products.index');
    Route::get('stock-movements', StockMovementsIndex::class)->name('stock-movements.index');
    Route::get('audit-logs', AuditLogsIndex::class)->name('audit-logs.index');
    Route::get('suppliers', SuppliersIndex::class)->name('suppliers.index');
    Route::post('logout', LogoutController::class)->name('logout');
});
