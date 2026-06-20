<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChildController;
use App\Http\Controllers\Api\ParentController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AdminNotificationController;
use App\Http\Controllers\Api\AdminReportController;
use App\Http\Controllers\Api\MobileChildController;
use App\Http\Controllers\Api\NurseDashboardController;
use App\Http\Controllers\Api\PoliceDashboardController;
use App\Http\Controllers\Api\PoliceController;

/*
|--------------------------------------------------------------------------
| Public Auth (web + mobile)
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'session.timeout'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/user/profile', [AuthController::class, 'updateProfile']);
    Route::put('/user/password', [AuthController::class, 'updatePassword']);
    Route::get('/settings', [AuthController::class, 'settings']);
    Route::put('/settings', [AuthController::class, 'updateSettings']);
});

/*
|--------------------------------------------------------------------------
| Parent (user role)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:user', 'session.timeout'])->group(function () {
    Route::get('/my-children', [ParentController::class, 'index']);
    Route::get('/my-children/{child}', [ParentController::class, 'show']);
    Route::post('/missing-reports', [ParentController::class, 'reportMissing']);
    Route::get('/my-reports', [ParentController::class, 'myReports']);
    Route::get('/my-reports/{report}', [ParentController::class, 'reportDetail']);
    Route::get('/parent/reports', [ParentController::class, 'getReports']);
    Route::get('/parent/verification-logs', [ParentController::class, 'verificationLogs']);
    Route::post('/children/register-by-parent', [ChildController::class, 'storeByParent']);
});

/*
|--------------------------------------------------------------------------
| Admin can also report missing children (no ownership constraint)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:admin', 'session.timeout'])->group(function () {
    Route::post('/admin/missing-reports', [ParentController::class, 'reportMissing']);
});

/*
|--------------------------------------------------------------------------
| Nurse / Admin — Child registration & listing
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:nurse,admin', 'session.timeout'])->group(function () {
    Route::post('/children/register', [ChildController::class, 'store']);
    Route::get('/children', [ChildController::class, 'index']);
    Route::get('/children/{child}', [ChildController::class, 'show']);

    // Child linking (shared by nurse and admin)
    Route::post('/children/{child}/link-parent', [AdminController::class, 'linkChildToParent']);
    Route::post('/children/{child}/unlink-parent', [AdminController::class, 'unlinkChildFromParent']);

    // Nurse dashboard
    Route::get('/nurse/dashboard', [NurseDashboardController::class, 'index']);
});

/*
|--------------------------------------------------------------------------
| Police / Admin — Search, verification logs, missing reports
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:police,admin', 'session.timeout'])->group(function () {
    // Police dashboard
    Route::get('/police/dashboard', [PoliceDashboardController::class, 'index']);
    Route::get('/police/search', [PoliceController::class, 'search']);

    // Child search & footprint
    Route::post('/children/text-search', [ChildController::class, 'textSearch']);
    Route::post('/children/search-by-footprint', [ChildController::class, 'searchByFootprint']);
    Route::post('/children/validate-footprint', [ChildController::class, 'validateFootprint']);
    Route::post('/children/register-found', [ChildController::class, 'registerFound']);

    // Verification logs
    Route::get('/logs', [AdminReportController::class, 'verificationLogs']);
    Route::get('/verification-logs', [AdminReportController::class, 'verificationLogs']);

    // Missing reports (shared by police and admin)
    Route::get('/active-missing-reports', [AdminReportController::class, 'activeReports']);
    Route::get('/all-reports', [AdminReportController::class, 'allReports']);
    Route::get('/missing-reports/{report}', [AdminReportController::class, 'show']);
    Route::put('/missing-reports/{report}/status', [AdminReportController::class, 'updateStatus']);
});

/*
|--------------------------------------------------------------------------
| Admin — System management & dashboard
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:admin', 'session.timeout'])->group(function () {
    // Dashboard stats
    Route::get('/admin/dashboard/stats', [AdminController::class, 'dashboardStats']);
    Route::get('/admin/dashboard/children', [AdminController::class, 'childrenOverview']);

    // Users management
    Route::get('/admin/users', [AdminUserController::class, 'index']);
    Route::post('/admin/users', [AdminUserController::class, 'store']);
    Route::put('/admin/users/{user}', [AdminUserController::class, 'update']);
    Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy']);

    // Children management
    Route::get('/admin/children', [AdminController::class, 'children']);
    Route::delete('/admin/children/{child}', [AdminController::class, 'deleteChild']);
    Route::get('/admin/verification-logs', [AdminReportController::class, 'verificationLogs']);

    // Settings
    Route::get('/admin/settings', [AdminController::class, 'settings']);
    Route::put('/admin/settings', [AdminController::class, 'updateSettings']);

    // Notifications
    Route::get('/admin/notifications', [AdminNotificationController::class, 'index']);
    Route::get('/admin/notifications/unread-count', [AdminNotificationController::class, 'unreadCount']);
    Route::patch('/admin/notifications/{notification}/read', [AdminNotificationController::class, 'markRead']);
    Route::patch('/admin/notifications/read-all', [AdminNotificationController::class, 'markAllRead']);
});

/*
|--------------------------------------------------------------------------
| Mobile Application APIs (Flutter) — all point to unified controllers
|--------------------------------------------------------------------------
*/

// Open mobile routes (no token required)
Route::post('/mobile/login', [AuthController::class, 'login']);
Route::post('/mobile/register', [AuthController::class, 'register']);

// Protected mobile routes
Route::middleware(['auth:sanctum', 'session.timeout'])->group(function () {
    // Auth
    Route::post('/mobile/logout', [AuthController::class, 'logout']);
    Route::get('/mobile/profile', [AuthController::class, 'me']);
    Route::post('/mobile/profile', [AuthController::class, 'updateProfile']);
    Route::put('/mobile/password', [AuthController::class, 'updatePassword']);

    // Children
    Route::get('/mobile/children', [MobileChildController::class, 'index']);
    Route::get('/mobile/children/{child}', [MobileChildController::class, 'show']);
    Route::post('/mobile/children/register', [MobileChildController::class, 'registerChild']);
    Route::post('/mobile/children/{child}/photo', [MobileChildController::class, 'uploadPhoto']);
    Route::post('/mobile/children/{child}/footprint', [MobileChildController::class, 'uploadFootprint']);
    Route::post('/mobile/children/search', [MobileChildController::class, 'searchMissing']);

    // Missing Reports
    Route::post('/mobile/reports/missing', [ParentController::class, 'reportMissing']);
    Route::get('/mobile/reports', [ParentController::class, 'myReports']);
    Route::get('/mobile/reports/{report}', [ParentController::class, 'reportDetail']);

    // Verification Logs
    Route::get('/mobile/verification-logs', [ParentController::class, 'verificationLogs']);
});