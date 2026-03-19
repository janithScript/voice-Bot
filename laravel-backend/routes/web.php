<?php
use App\Http\Controllers\VoiceBotController;
use App\Http\Controllers\AppointmentController;
use Illuminate\Support\Facades\Route;

// Voicebot UI
Route::get('/', [VoiceBotController::class, 'index'])->name('voicebot');

// API routes for the voice bot
Route::prefix('api')->group(function () {
    Route::post('/voice/process', [VoiceBotController::class, 'processVoice']);
    Route::post('/appointment/book', [VoiceBotController::class, 'bookAppointment']);
    Route::get('/slots', [VoiceBotController::class, 'getSlots']);
});

// Admin: view all appointments
Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments');
