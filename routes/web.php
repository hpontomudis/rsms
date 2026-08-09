<?php

use App\Livewire\Attendance;
use App\Livewire\Auth\Login;
use App\Livewire\Classes;
use App\Livewire\Dashboard;
use App\Livewire\Guardians;
use App\Livewire\Staff;
use App\Livewire\Students;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(Auth::check() ? 'dashboard' : 'login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    Route::prefix('students')->name('students.')->group(function () {
        Route::get('/', Students\Index::class)->name('index');
        Route::get('/create', Students\Create::class)->name('create');
        Route::get('/{student}', Students\Show::class)->name('show');
        Route::get('/{student}/edit', Students\Edit::class)->name('edit');
    });

    Route::prefix('guardians')->name('guardians.')->group(function () {
        Route::get('/', Guardians\Index::class)->name('index');
        Route::get('/create', Guardians\Create::class)->name('create');
        Route::get('/{guardian}', Guardians\Show::class)->name('show');
        Route::get('/{guardian}/edit', Guardians\Edit::class)->name('edit');
    });

    Route::prefix('staff')->name('staff.')->group(function () {
        Route::get('/', Staff\Index::class)->name('index');
        Route::get('/create', Staff\Create::class)->name('create');
        Route::get('/{staff}', Staff\Show::class)->name('show');
        Route::get('/{staff}/edit', Staff\Edit::class)->name('edit');
    });

    Route::prefix('classes')->name('classes.')->group(function () {
        Route::get('/', Classes\Index::class)->name('index');
        Route::get('/create', Classes\Create::class)->name('create');
        Route::get('/{schoolClass}', Classes\Show::class)->name('show');
        Route::get('/{schoolClass}/edit', Classes\Edit::class)->name('edit');
    });

    Route::prefix('attendance')->name('attendance.')->group(function () {
        Route::get('/', Attendance\Take::class)->name('take');
        Route::get('/report', Attendance\Report::class)->name('report');
    });
});
