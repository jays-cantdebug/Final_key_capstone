<?php

use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\AssessmentHistoryController;
use App\Http\Controllers\AssessmentWizardController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ClassificationThresholdController;
use App\Http\Controllers\CounselingSessionController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DassQuestionController;
use App\Http\Controllers\FlaggedCaseController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PredictionFeedbackController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuestionnaireController;
use App\Http\Controllers\QuestionnaireVersionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Reports\AssessmentReportController;
use App\Http\Controllers\Reports\AssessmentSummaryReportController;
use App\Http\Controllers\Reports\CounselingReportController;
use App\Http\Controllers\Reports\FlaggedStudentsReportController;
use App\Http\Controllers\Reports\StudentHistoryReportController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\YearLevelController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/psychometrician/dashboard', [DashboardController::class, 'psychometrician'])
        ->middleware('role:psychometrician')
        ->name('psychometrician.dashboard');

    Route::get('/guidance-counselor/dashboard', [DashboardController::class, 'guidanceCounselor'])
        ->middleware('role:guidance_counselor')
        ->name('guidance-counselor.dashboard');

    Route::middleware('role:psychometrician')->group(function (): void {
        Route::resource('students', StudentController::class)->except(['create', 'store']);

        Route::resource('questionnaires', QuestionnaireController::class)->except(['destroy']);

        Route::prefix('questionnaires/{questionnaire}/versions')
            ->name('questionnaires.versions.')
            ->group(function (): void {
                Route::get('/create', [QuestionnaireVersionController::class, 'create'])->name('create');
                Route::post('/', [QuestionnaireVersionController::class, 'store'])->name('store');
                Route::get('/{version}', [QuestionnaireVersionController::class, 'show'])->name('show');
                Route::get('/{version}/edit', [QuestionnaireVersionController::class, 'edit'])->name('edit');
                Route::put('/{version}', [QuestionnaireVersionController::class, 'update'])->name('update');
                Route::delete('/{version}', [QuestionnaireVersionController::class, 'destroy'])->name('destroy');
                Route::patch('/{version}/activate', [QuestionnaireVersionController::class, 'activate'])->name('activate');
                Route::patch('/{version}/archive', [QuestionnaireVersionController::class, 'archive'])->name('archive');
            });

        Route::prefix('questionnaires/{questionnaire}/versions/{version}/questions')
            ->name('questionnaires.versions.questions.')
            ->group(function (): void {
                Route::get('/create', [DassQuestionController::class, 'create'])->name('create');
                Route::post('/', [DassQuestionController::class, 'store'])->name('store');
                Route::get('/{question}/edit', [DassQuestionController::class, 'edit'])->name('edit');
                Route::put('/{question}', [DassQuestionController::class, 'update'])->name('update');
                Route::delete('/{question}', [DassQuestionController::class, 'destroy'])->name('destroy');
            });

        Route::resource('users', UserController::class)->except(['destroy']);
        Route::patch('/users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
        Route::patch('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
        Route::patch('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('/settings/records', [SettingsController::class, 'records'])->name('settings.records');

        Route::get('/settings/classification-thresholds', [ClassificationThresholdController::class, 'index'])->name('settings.classification-thresholds');
        Route::put('/settings/classification-thresholds', [ClassificationThresholdController::class, 'update'])->name('settings.classification-thresholds.update');
        Route::post('/settings/classification-thresholds/restore', [ClassificationThresholdController::class, 'restore'])->name('settings.classification-thresholds.restore');

        Route::resource('courses', CourseController::class)->only(['create', 'store', 'edit', 'update', 'destroy']);
        Route::resource('year-levels', YearLevelController::class)->parameters(['year-levels' => 'year_level'])->only(['create', 'store', 'edit', 'update', 'destroy']);
        Route::resource('sections', SectionController::class)->only(['create', 'store', 'edit', 'update', 'destroy']);

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');

        Route::get('assessments/create', [AssessmentWizardController::class, 'showStudentStep'])->name('assessments.create');

        Route::prefix('assessments/create')
            ->name('assessments.create.')
            ->group(function (): void {
                Route::get('/retake/{student}', [AssessmentWizardController::class, 'startRetake'])->name('retake');
                Route::post('/student', [AssessmentWizardController::class, 'confirmStudent'])->name('student');
                Route::get('/questionnaire', [AssessmentWizardController::class, 'showQuestionnaireStep'])->name('questionnaire');
                Route::post('/questionnaire', [AssessmentWizardController::class, 'storeResponses'])->name('questionnaire.store');
                Route::get('/result', [AssessmentWizardController::class, 'showResultStep'])->name('result');
                Route::post('/submit', [AssessmentWizardController::class, 'submit'])->name('submit');
            });

        Route::post('/assessments/{assessment}/feedback', [PredictionFeedbackController::class, 'store'])->name('assessments.feedback.store');
    });

    Route::get('/assessments', [AssessmentHistoryController::class, 'index'])
        ->middleware('role:psychometrician,guidance_counselor')
        ->name('assessments.index');

    Route::get('/assessments/{assessment}', [AssessmentController::class, 'show'])
        ->middleware('role:psychometrician,guidance_counselor')
        ->name('assessments.show');

    Route::middleware('role:guidance_counselor')->group(function (): void {
        Route::get('/flagged-cases', [FlaggedCaseController::class, 'index'])->name('flagged-cases.index');

        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::patch('/notifications/{notification}/archive', [NotificationController::class, 'archive'])->name('notifications.archive');
        Route::patch('/notifications/{notification}/unarchive', [NotificationController::class, 'unarchive'])->name('notifications.unarchive');
        Route::get('/notifications/{notification}/view', [NotificationController::class, 'view'])->name('notifications.view');

        Route::resource('counseling-sessions', CounselingSessionController::class);
    });

    Route::get('/reports', [ReportController::class, 'index'])
        ->middleware('role:psychometrician,guidance_counselor')
        ->name('reports.index');

    Route::middleware('role:psychometrician,guidance_counselor')
        ->prefix('reports')
        ->name('reports.')
        ->group(function (): void {
            Route::get('/assessments/{assessment}/print', [AssessmentReportController::class, 'print'])->name('assessment.print');
            Route::get('/assessments/{assessment}/pdf', [AssessmentReportController::class, 'pdf'])->name('assessment.pdf');

            Route::get('/student-history/print', [StudentHistoryReportController::class, 'print'])->name('student-history.print');
            Route::get('/student-history/pdf', [StudentHistoryReportController::class, 'pdf'])->name('student-history.pdf');

            Route::get('/assessment-summary', [AssessmentSummaryReportController::class, 'index'])->name('assessment-summary');
            Route::get('/assessment-summary/print', [AssessmentSummaryReportController::class, 'print'])->name('assessment-summary.print');
            Route::get('/assessment-summary/pdf', [AssessmentSummaryReportController::class, 'pdf'])->name('assessment-summary.pdf');
        });

    Route::middleware('role:guidance_counselor')
        ->prefix('reports')
        ->name('reports.')
        ->group(function (): void {
            Route::get('/flagged-students/print', [FlaggedStudentsReportController::class, 'print'])->name('flagged-students.print');
            Route::get('/flagged-students/pdf', [FlaggedStudentsReportController::class, 'pdf'])->name('flagged-students.pdf');

            Route::get('/counseling/print', [CounselingReportController::class, 'print'])->name('counseling.print');
            Route::get('/counseling/pdf', [CounselingReportController::class, 'pdf'])->name('counseling.pdf');
        });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
