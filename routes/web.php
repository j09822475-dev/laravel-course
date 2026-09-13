<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\SessionController;
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
