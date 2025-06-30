<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\TipController;
use App\Http\Controllers\TipCategoryController;
use App\Http\Controllers\SuccessStoryController;
use App\Http\Controllers\CommunityMessageController;
use App\Http\Controllers\MessageReplyController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::patch('/user', [AuthController::class, 'updateUserDetails']);
    Route::post('/user/image', [AuthController::class, 'updateUserImage']);
    Route::get('/user/profile', [AuthController::class, 'userProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

// Product routes
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/featured', [ProductController::class, 'featured']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/products', [ProductController::class, 'store']);
    Route::post('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
});

// Category routes
Route::get('/categories', [CategoryController::class, 'index']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
});

// Order routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/my-orders', [OrderController::class, 'myOrders']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);
    Route::patch('/orders/{orderId}/items/{itemId}/status', [OrderController::class, 'updateItemStatus']);
});

// Admin only routes
Route::middleware(['auth:sanctum', \App\Http\Middleware\AdminMiddleware::class])->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
});

// Consultation routes
Route::get('/experts', [ConsultationController::class, 'getExperts']);

Route::middleware('auth:sanctum')->group(function () {
    // Farmer routes
    Route::post('/consultations', [ConsultationController::class, 'store']);
    Route::get('/consultations/my-bookings', [ConsultationController::class, 'myBookings']);

    // Expert routes
    Route::get('/consultations/my-expert-bookings', [ConsultationController::class, 'myExpertBookings']);
    Route::patch('/consultations/{id}/accept', [ConsultationController::class, 'accept']);
    Route::patch('/consultations/{id}/decline', [ConsultationController::class, 'decline']);
    Route::patch('/consultations/{id}/complete', [ConsultationController::class, 'complete']);

    // Common routes (both farmer and expert can cancel)
    Route::patch('/consultations/{id}/cancel', [ConsultationController::class, 'cancel']);
});

// Tips and Advice Routes
// Public routes (no auth required)
Route::get('/tips', [TipController::class, 'index']);
Route::get('/tips/featured', [TipController::class, 'featured']);
Route::get('/tip-categories', [TipCategoryController::class, 'index']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Tip Interactions (All authenticated users)
    Route::get('/tips/saved', [TipController::class, 'savedTips']);
    Route::post('/tips/{id}/like', [TipController::class, 'toggleLike']);
    Route::post('/tips/{id}/save', [TipController::class, 'toggleSave']);

    // Tips Management (Experts only)
    Route::middleware([\App\Http\Middleware\ExpertMiddleware::class])->group(function () {
        Route::get('/tips/my-tips', [TipController::class, 'myTips']);
        Route::post('/tips', [TipController::class, 'store']);
        Route::put('/tips/{id}', [TipController::class, 'update']);
        Route::delete('/tips/{id}', [TipController::class, 'destroy']);
    });

    // Tip Categories Management (Admin only)
    Route::middleware([\App\Http\Middleware\AdminMiddleware::class])->group(function () {
        Route::post('/tip-categories', [TipCategoryController::class, 'store']);
        Route::put('/tip-categories/{id}', [TipCategoryController::class, 'update']);
        Route::delete('/tip-categories/{id}', [TipCategoryController::class, 'destroy']);
    });
});

// Public tip detail route (must be after all other tip routes)
Route::get('/tips/{id}', [TipController::class, 'show']);

// Success Stories Routes
// Public routes (no auth required)
Route::get('/success-stories', [SuccessStoryController::class, 'index']);
Route::get('/success-stories/{id}', [SuccessStoryController::class, 'show']);
Route::get('/success-stories/{id}/comments', [SuccessStoryController::class, 'getComments']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Success Stories Management
    Route::post('/success-stories', [SuccessStoryController::class, 'store']);
    Route::put('/success-stories/{id}', [SuccessStoryController::class, 'update']);
    Route::delete('/success-stories/{id}', [SuccessStoryController::class, 'destroy']);
    Route::get('/success-stories/my-stories', [SuccessStoryController::class, 'myStories']);
    Route::post('/success-stories/test-upload', [SuccessStoryController::class, 'testUpload']);

    // Success Stories Interactions
    Route::post('/success-stories/{id}/like', [SuccessStoryController::class, 'toggleLike']);
    Route::post('/success-stories/{id}/comments', [SuccessStoryController::class, 'addComment']);
});

// Community Discussion Routes
// Public routes (no auth required)
Route::get('/community/messages', [CommunityMessageController::class, 'index']);
Route::get('/community/messages/poll', [CommunityMessageController::class, 'poll']);
Route::get('/community/messages/{id}', [CommunityMessageController::class, 'show']);
Route::get('/community/messages/latest', [CommunityMessageController::class, 'getLatest']);
Route::get('/community/messages/{messageId}/replies', [MessageReplyController::class, 'index']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Community Messages Management
    Route::post('/community/messages', [CommunityMessageController::class, 'store']);
    Route::put('/community/messages/{id}', [CommunityMessageController::class, 'update']);
    Route::delete('/community/messages/{id}', [CommunityMessageController::class, 'destroy']);
    Route::post('/community/messages/{id}/like', [CommunityMessageController::class, 'toggleLike']);

    // Message Replies Management
    Route::post('/community/messages/{messageId}/replies', [MessageReplyController::class, 'store']);
    Route::put('/community/messages/{messageId}/replies/{replyId}', [MessageReplyController::class, 'update']);
    Route::delete('/community/messages/{messageId}/replies/{replyId}', [MessageReplyController::class, 'destroy']);
    Route::post('/community/messages/{messageId}/replies/{replyId}/like', [MessageReplyController::class, 'toggleLike']);
});

// Password Reset Routes
Route::post('/password/request-reset', [AuthController::class, 'requestPasswordReset']);
Route::post('/password/reset', [AuthController::class, 'resetPassword']); 