<?php

use App\Livewire\Academics\ReportCard;
use App\Livewire\Assessments;
use App\Livewire\Attendance;
use App\Livewire\Auth\Login;
use App\Livewire\Classes;
use App\Livewire\Dashboard;
use App\Livewire\EnglishProgrammes;
use App\Livewire\FeeStructures;
use App\Livewire\Guardians;
use App\Livewire\Invoices;
use App\Livewire\Staff;
use App\Livewire\Students;
use App\Livewire\Subjects;
use App\Livewire\Teaching;
use App\Livewire\TeachingGroups;
use App\Models\Payment;
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
        Route::get('/{student}/report-card', ReportCard::class)->name('report-card');
        Route::get('/{student}/english-placement', Students\EnglishPlacement::class)->name('english-placement');
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

    Route::prefix('fee-structures')->name('fee-structures.')->group(function () {
        Route::get('/', FeeStructures\Index::class)->name('index');
        Route::get('/create', FeeStructures\Create::class)->name('create');
        Route::get('/{feeStructure}', FeeStructures\Show::class)->name('show');
        Route::get('/{feeStructure}/edit', FeeStructures\Edit::class)->name('edit');
    });

    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/', Invoices\Index::class)->name('index');
        Route::get('/create', Invoices\Create::class)->name('create');
        Route::get('/{invoice}', Invoices\Show::class)->name('show');
    });

    Route::prefix('english-programmes')->name('english-programmes.')->group(function () {
        Route::get('/', EnglishProgrammes\Index::class)->name('index');
        Route::get('/create', EnglishProgrammes\Create::class)->name('create');
        Route::get('/{englishProgramme}', EnglishProgrammes\Show::class)->name('show');
        Route::get('/{englishProgramme}/edit', EnglishProgrammes\Edit::class)->name('edit');
    });

    Route::prefix('teaching-groups')->name('teaching-groups.')->group(function () {
        Route::get('/', TeachingGroups\Index::class)->name('index');
        Route::get('/create', TeachingGroups\Create::class)->name('create');
        Route::get('/{teachingGroup}', TeachingGroups\Show::class)->name('show');
        Route::get('/{teachingGroup}/edit', TeachingGroups\Edit::class)->name('edit');
    });

    Route::prefix('subjects')->name('subjects.')->group(function () {
        Route::get('/', Subjects\Index::class)->name('index');
        Route::get('/create', Subjects\Create::class)->name('create');
        Route::get('/{subject}/edit', Subjects\Edit::class)->name('edit');
    });

    // A teacher's own assignments -- the entry point into assessment work for
    // both classes and teaching groups.
    Route::get('/my-teaching', Teaching\MyAssignments::class)->name('my-teaching');

    Route::prefix('assessments')->name('assessments.')->group(function () {
        Route::get('/', Assessments\Index::class)->name('index');
        Route::get('/create', Assessments\Create::class)->name('create');
        Route::get('/{assessment}', Assessments\Show::class)->name('show');
    });

    Route::get('/payments/{payment}/receipt', function (Payment $payment) {
        abort_unless(request()->user()->can('finance.view'), 403);

        $payment->load('invoice.student', 'receivedBy');

        return view('receipts.show', ['payment' => $payment]);
    })->name('payments.receipt');
});
