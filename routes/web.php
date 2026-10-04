<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LowAttendanceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TeacherController;
use Illuminate\Support\Facades\Route;

// Only logged-out visitors
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
});

// Any logged-in teacher. What they see inside is scoped per subject in the controllers.
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/students', [StudentController::class, 'index'])->name('students.index');

    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/attendance/mark', [AttendanceController::class, 'mark'])->name('attendance.mark');
    Route::post('/attendance/mark', [AttendanceController::class, 'store'])->name('attendance.store');

    Route::get('/reports/attendance', [ReportController::class, 'attendance'])->name('reports.attendance');
    Route::get('/reports/low-attendance', [LowAttendanceController::class, 'show'])->name('reports.low-attendance');
    Route::get('/reports/low-attendance/pdf', [LowAttendanceController::class, 'pdf'])->name('reports.low-attendance.pdf');

    // Class teacher only
    Route::middleware('role:class_teacher')->group(function () {
        Route::resource('students', StudentController::class)->except(['index', 'show']);
        Route::resource('subjects', SubjectController::class)->except(['show']);
        Route::resource('teachers', TeacherController::class)->except(['show']);
    });
});
