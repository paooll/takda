<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\BusinessDashboardController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\QrCodeController;
use App\Http\Controllers\Api\QueueController;
use App\Http\Controllers\Api\TicketController;
use Illuminate\Support\Facades\Route;

// Public: browsing and joining a queue must not require an account.
Route::get('/businesses', [BusinessController::class, 'index']);
Route::get('/businesses/{business}', [BusinessController::class, 'show']);
Route::get('/businesses/{business}/queues', [QueueController::class, 'index']);
Route::get('/businesses/{business}/queues/{queue}/qr', [QrCodeController::class, 'image']);
Route::get('/businesses/{business}/queues/{queue}/qr.json', [QrCodeController::class, 'show']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Customer
    Route::post('/queues/{queue}/join', [TicketController::class, 'store']);
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
    Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy']);

    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::patch('/appointments/{appointment}', [AppointmentController::class, 'update']);
    Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    // Business owner
    Route::post('/businesses', [BusinessController::class, 'store']);
    Route::patch('/businesses/{business}', [BusinessController::class, 'update']);

    Route::post('/businesses/{business}/queues', [QueueController::class, 'store']);
    Route::patch('/businesses/{business}/queues/{queue}', [QueueController::class, 'update']);

    Route::get('/businesses/{business}/dashboard', [BusinessDashboardController::class, 'analytics']);
    Route::get('/businesses/{business}/queues/{queue}/board', [BusinessDashboardController::class, 'board']);
    Route::post('/businesses/{business}/queues/{queue}/call-next', [BusinessDashboardController::class, 'callNext']);
    Route::post('/businesses/{business}/queues/{queue}/tickets/{ticket}/complete', [BusinessDashboardController::class, 'complete']);
});
