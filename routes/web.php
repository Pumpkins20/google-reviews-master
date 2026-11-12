<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Auth\LoginController;

// Auth Routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Protected Dashboard Routes
Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return redirect('/dashboard');
    });
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/dashboard/comparison', [\App\Http\Controllers\AiComparisonController::class, 'index'])->name('dashboard.comparison');
    Route::post('/dashboard/comparison/analyze', [\App\Http\Controllers\AiComparisonController::class, 'analyze'])->name('dashboard.comparison.analyze');
    Route::get('/dashboard/stores', [\App\Http\Controllers\StoreController::class, 'index'])->name('stores.index');
    Route::get('/dashboard/stores/{id}', [\App\Http\Controllers\StoreController::class, 'show'])->whereNumber('id')->name('stores.show');
    Route::post('/dashboard/stores/{id}/scrape', [\App\Http\Controllers\StoreController::class, 'scrape'])->whereNumber('id')->name('stores.scrape');
    Route::post('/dashboard/stores/{id}/photos/analyze', [\App\Http\Controllers\StoreController::class, 'analyzePhotos'])->whereNumber('id')->name('stores.photos.analyze');
    Route::get('/dashboard/photos', [\App\Http\Controllers\PhotoController::class, 'index'])->name('photos.index');
    Route::get('/dashboard/photos/{id}', [\App\Http\Controllers\PhotoController::class, 'show'])->whereNumber('id')->name('photos.show');
    Route::get('/dashboard/photos/{id}/report', [\App\Http\Controllers\PhotoController::class, 'report'])->whereNumber('id')->name('photos.report');
    Route::get('/dashboard/photos/{id}/report/go', [\App\Http\Controllers\PhotoController::class, 'reportRedirect'])->whereNumber('id')->name('photos.report.go');
    Route::post('/dashboard/photos/{id}/report', [\App\Http\Controllers\PhotoController::class, 'updateReport'])->whereNumber('id')->name('photos.report.update');
    Route::post('/dashboard/photos/{id}/analyze', [\App\Http\Controllers\PhotoController::class, 'analyze'])->whereNumber('id')->name('photos.analyze');
    Route::get('/dashboard/places/{place}', [DashboardController::class, 'show'])->name('dashboard.show');
});
