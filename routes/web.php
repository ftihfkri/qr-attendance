<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CheckinController;
use App\Http\Controllers\ElectionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VotingController;
use Illuminate\Support\Facades\Route;

// ---- Public ----
Route::get('/', fn () => redirect('/login'));

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1'); // 10/min (brute force)
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/register', [AuthController::class, 'showRegister']);
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');

// Public check-in (the QR points here)
Route::get('/checkin', [CheckinController::class, 'show']);
Route::get('/checkin/members', [CheckinController::class, 'searchMembers'])->middleware('throttle:60,1'); // roster autocomplete
Route::post('/checkin', [CheckinController::class, 'store'])->middleware('throttle:30,1'); // cap spam/enumeration

// Public board-election ballot (members scan the QR to vote)
Route::get('/vote/{token}', [VotingController::class, 'show']);
Route::get('/vote/{token}/voters', [VotingController::class, 'voterSearch'])->middleware('throttle:60,1'); // name autocomplete
Route::get('/vote/{token}/results', [VotingController::class, 'results'])->middleware('throttle:120,1');
Route::post('/vote/{token}', [VotingController::class, 'vote'])->middleware('throttle:30,1');

// ---- Authenticated (admin + staff share the same interface) ----
Route::middleware('auth')->group(function () {
    Route::get('/admin', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/admin/verify', [AdminController::class, 'verify']);
    Route::get('/admin/venue', [AdminController::class, 'getVenue']);
    Route::post('/admin/venue', [AdminController::class, 'setVenue']);
    Route::get('/admin/submission', [AdminController::class, 'getSubmission']);
    Route::post('/admin/submission', [AdminController::class, 'setSubmission']);
    Route::get('/admin/attendance', [AdminController::class, 'attendanceList']);
    Route::post('/admin/attendance/{id}', [AdminController::class, 'updateAttendance']);
    Route::delete('/admin/attendance/{id}', [AdminController::class, 'deleteAttendance']);
    Route::get('/admin/export', [AdminController::class, 'export']);
    Route::post('/admin/clear', [AdminController::class, 'clear']);
    Route::post('/admin/manual', [AdminController::class, 'manualAdd']);

    // Membership roster + attendance status
    Route::get('/admin/upload', [AdminController::class, 'uploadPage']);
    Route::post('/admin/memberships/upload', [AdminController::class, 'membershipUpload']);
    Route::get('/admin/roster', [AdminController::class, 'roster']);
    Route::post('/admin/roster/mark', [AdminController::class, 'markAttendance']);

    // Board Election (staff + admin)
    Route::get('/admin/election', [ElectionController::class, 'index']);
    Route::get('/admin/election/results', [ElectionController::class, 'results']);
    Route::get('/admin/election/candidates/search', [ElectionController::class, 'candidateSearch']);
    Route::post('/admin/election/candidates', [ElectionController::class, 'addCandidate']);
    Route::delete('/admin/election/candidates/{id}', [ElectionController::class, 'removeCandidate']);
    Route::post('/admin/election/voting', [ElectionController::class, 'setVoting']);
    Route::post('/admin/election/clear', [ElectionController::class, 'clear']);

    // ---- Admin role only: user management ----
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/users', [UserController::class, 'index']);
        Route::get('/admin/users/list', [UserController::class, 'list']);
        Route::post('/admin/users', [UserController::class, 'store']);
        Route::delete('/admin/users/{id}', [UserController::class, 'destroy']);
    });
});
