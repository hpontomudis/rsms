<?php

use App\Documents\AnnualProgrammeDocumentBuilder;
use App\Documents\DailyJournalDocumentBuilder;
use App\Documents\LearningPathwayDocumentBuilder;
use App\Documents\ReportCardDocumentBuilder;
use App\Documents\SemesterProgrammeDocumentBuilder;
use App\Documents\TeachingModuleDocumentBuilder;
use App\Livewire\Academics;
use App\Livewire\Academics\ReportCard;
use App\Livewire\Assessments;
use App\Livewire\Attendance;
use App\Livewire\Auth\Login;
use App\Livewire\Classes;
use App\Livewire\Curricula;
use App\Livewire\Dashboard;
use App\Livewire\EnglishProgrammes;
use App\Livewire\FeeStructures;
use App\Livewire\Guardians;
use App\Livewire\Invoices;
use App\Livewire\LearningPhases;
use App\Livewire\Planning;
use App\Livewire\Staff;
use App\Livewire\Students;
use App\Livewire\Subjects;
use App\Livewire\Teaching;
use App\Livewire\TeachingGroups;
use App\Models\AcademicPeriod;
use App\Models\AcademicRecord;
use App\Models\AcademicYear;
use App\Models\AnnualProgramme;
use App\Models\DailyJournal;
use App\Models\LearningPathway;
use App\Models\Payment;
use App\Models\SemesterProgramme;
use App\Models\Student;
use App\Models\TeachingModule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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

    Route::get('/learning-phases', LearningPhases\Index::class)->name('learning-phases');

    Route::prefix('curricula')->name('curricula.')->group(function () {
        Route::get('/', Curricula\Index::class)->name('index');
        Route::get('/create', Curricula\Create::class)->name('create');
        Route::get('/{curriculum}', Curricula\Show::class)->name('show');
        Route::get('/{curriculum}/edit', Curricula\Edit::class)->name('edit');
        Route::get('/{curriculum}/scopes/{scope}', Curricula\ScopeShow::class)->name('scopes.show');
        Route::get('/{curriculum}/scopes/{scope}/pathways/{pathway}', Curricula\PathwayShow::class)->name('pathways.show');
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

    // Operating plans. Prota is anchored to a roster, so its URL carries the
    // programme and never a teaching assignment.
    Route::prefix('planning')->name('planning.')->group(function () {
        Route::get('/', Planning\Index::class)->name('annual.index');
        Route::get('/create', Planning\Create::class)->name('annual.create');
        Route::get('/{annualProgramme}', Planning\AnnualProgrammeShow::class)->name('annual.show');
        Route::get('/semester/{semesterProgramme}', Planning\SemesterProgrammeShow::class)->name('semester.show');
    });

    // A teacher's own assignments -- the entry point into assessment work for
    // both classes and teaching groups.
    Route::get('/my-teaching', Teaching\MyAssignments::class)->name('my-teaching');

    // Modules and journals hang off the ASSIGNMENT, not the roster, so their
    // list URLs carry a class_subject and their detail URLs carry the record.
    Route::prefix('teaching')->name('teaching.')->group(function () {
        Route::get('/{classSubject}/modules', Teaching\Modules::class)->name('modules.index');
        Route::get('/modules/{teachingModule}', Teaching\ModuleShow::class)->name('modules.show');
        Route::get('/{classSubject}/journal', Teaching\Journals::class)->name('journal.index');
        Route::get('/journal/{dailyJournal}', Teaching\JournalShow::class)->name('journal.show');
    });

    Route::prefix('assessments')->name('assessments.')->group(function () {
        Route::get('/', Assessments\Index::class)->name('index');
        Route::get('/create', Assessments\Create::class)->name('create');
        Route::get('/{assessment}', Assessments\Show::class)->name('show');
    });

    /*
     * PRINTED DOCUMENTS.
     *
     * Plain controller-free routes rather than Livewire components: a document
     * is a one-shot render with its own layout and print stylesheet, and has no
     * interactivity to justify a component. Each authorises before building.
     */
    Route::prefix('documents')->name('documents.')->group(function () {
        Route::get('/students/{student}/report-card/year/{academicYear}', function (Student $student, AcademicYear $academicYear, ReportCardDocumentBuilder $builder) {
            Gate::authorize('view', $student);
            abort_unless(request()->user()->can('academics.view'), 403);

            return view('documents.report-card', ['document' => $builder->fromLiveYear($student, $academicYear)]);
        })->name('report-card.year');

        Route::get('/students/{student}/report-card/period/{academicPeriod}', function (Student $student, AcademicPeriod $academicPeriod, ReportCardDocumentBuilder $builder) {
            Gate::authorize('view', $student);
            abort_unless(request()->user()->can('academics.view'), 403);

            return view('documents.report-card', ['document' => $builder->fromLivePeriod($student, $academicPeriod)]);
        })->name('report-card.period');

        // The issued document. Reads snapshots only.
        Route::get('/academic-records/{academicRecord}', function (AcademicRecord $academicRecord, ReportCardDocumentBuilder $builder) {
            Gate::authorize('view', $academicRecord);
            abort_if($academicRecord->isDraft(), 404);

            return view('documents.report-card', ['document' => $builder->fromPublished($academicRecord->load('subjects'))]);
        })->name('academic-record');

        Route::get('/annual-programmes/{annualProgramme}', function (AnnualProgramme $annualProgramme, AnnualProgrammeDocumentBuilder $builder) {
            Gate::authorize('view', $annualProgramme);

            return view('documents.planning', ['document' => $builder->build($annualProgramme)]);
        })->name('annual-programme');

        Route::get('/semester-programmes/{semesterProgramme}', function (SemesterProgramme $semesterProgramme, SemesterProgrammeDocumentBuilder $builder) {
            Gate::authorize('view', $semesterProgramme);

            return view('documents.planning', ['document' => $builder->build($semesterProgramme)]);
        })->name('semester-programme');

        Route::get('/learning-pathways/{learningPathway}', function (LearningPathway $learningPathway, LearningPathwayDocumentBuilder $builder) {
            abort_unless(request()->user()->can('academics.view'), 403);

            return view('documents.planning', ['document' => $builder->build($learningPathway)]);
        })->name('learning-pathway');

        Route::get('/teaching-modules/{teachingModule}', function (TeachingModule $teachingModule, TeachingModuleDocumentBuilder $builder) {
            Gate::authorize('view', $teachingModule);

            return view('documents.planning', ['document' => $builder->build($teachingModule)]);
        })->name('teaching-module');

        Route::get('/daily-journals/{dailyJournal}', function (DailyJournal $dailyJournal, DailyJournalDocumentBuilder $builder) {
            Gate::authorize('view', $dailyJournal);

            return view('documents.planning', ['document' => $builder->build($dailyJournal)]);
        })->name('daily-journal');
    });

    Route::get('/students/{student}/academic-records', Academics\AcademicRecords::class)->name('students.academic-records');

    Route::get('/payments/{payment}/receipt', function (Payment $payment) {
        abort_unless(request()->user()->can('finance.view'), 403);

        $payment->load('invoice.student', 'receivedBy');

        return view('receipts.show', ['payment' => $payment]);
    })->name('payments.receipt');
});
