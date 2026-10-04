<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\ShareController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');
Route::put('/profile', [DashboardController::class, 'update'])->name('profile.update');

Route::post('/sessions', [SessionController::class, 'store'])->name('sessions.store');
Route::get('/sessions/{session}', [SessionController::class, 'show'])->name('sessions.show');
Route::post('/sessions/{session}/answer', [SessionController::class, 'answer'])->name('sessions.answer');
Route::get('/sessions/{session}/report', [SessionController::class, 'report'])->name('sessions.report');
Route::delete('/sessions/{session}', [SessionController::class, 'destroy'])->name('sessions.destroy');

Route::get('/questions', [QuestionController::class, 'index'])->name('questions.index');
Route::get('/questions/{question}', [QuestionController::class, 'show'])->name('questions.show');

Route::get('/progress', ProgressController::class)->name('progress');

// Аккаунт: регистрация и вход нужны, чтобы прогресс жил не в браузере, а в профиле.
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// Делиться прогрессом может только владелец аккаунта: у гостя нет устойчивого профиля.
Route::middleware('auth')->group(function () {
    Route::post('/share', [ShareController::class, 'store'])->name('share.store');
    Route::put('/share', [ShareController::class, 'update'])->name('share.update');
    Route::delete('/share', [ShareController::class, 'destroy'])->name('share.destroy');
});

Route::get('/p/{token}', [ShareController::class, 'show'])->name('share.show');

Route::view('/offline', 'offline')->name('offline');
