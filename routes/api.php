<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChildController;
use App\Http\Controllers\Api\ParentController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\MobileAuthController;
use App\Http\Controllers\Api\MobileChildController;
use App\Http\Controllers\Api\MobileReportController;
use App\Http\Controllers\Api\NurseDashboardController;
use App\Http\Controllers\Api\PoliceDashboardController;
use App\Http\Controllers\Api\PoliceController;

/*
|--------------------------------------------------------------------------
| API عام — React + Mobile (نفس قاعدة البيانات)
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
| ولي الأمر (user)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:user', 'session.timeout'])->group(function () {
    Route::get('/my-children', [ParentController::class, 'index']);
    Route::get('/my-children/{child}', [ParentController::class, 'show']);
    Route::post('/missing-reports', [ParentController::class, 'reportMissing']);
    Route::get('/my-reports', [ParentController::class, 'myReports']);
    Route::get('/parent/reports', [ParentController::class, 'getReports']);
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
| ممرضة / أدمن — تسجيل طفل وقائمة الأطفال (جدول children)
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
| شرطة / أدمن — بحث وسجلات التحقق وربط الـ AI
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
    Route::get('/logs', [AdminController::class, 'verificationLogs']);
    Route::get('/verification-logs', [AdminController::class, 'verificationLogs']);

    // Missing reports (shared by police and admin)
    Route::get('/active-missing-reports', [AdminController::class, 'activeMissingReports']);
    Route::get('/all-reports', [AdminController::class, 'allReports']);
    Route::get('/missing-reports/{report}', [AdminController::class, 'missingReportDetails']);
    Route::put('/missing-reports/{report}/status', [AdminController::class, 'updateMissingReportStatus']);
});

/*
|--------------------------------------------------------------------------
| أدمن — إدارة النظام والـ Dashboard
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:admin', 'session.timeout'])->group(function () {
    // Dashboard stats
    Route::get('/admin/dashboard/stats', [AdminController::class, 'dashboardStats']);
    Route::get('/admin/dashboard/children', [AdminController::class, 'childrenOverview']);

    // Users management
    Route::get('/admin/users', [AdminController::class, 'users']);
    Route::post('/admin/users', [AdminController::class, 'createUser']);
    Route::put('/admin/users/{user}', [AdminController::class, 'updateUser']);
    Route::delete('/admin/users/{user}', [AdminController::class, 'deleteUser']);

    // Children management
    Route::get('/admin/children', [AdminController::class, 'children']);
    Route::delete('/admin/children/{child}', [AdminController::class, 'deleteChild']);
    Route::get('/admin/verification-logs', [AdminController::class, 'verificationLogs']);

    // Settings
    Route::get('/admin/settings', [AdminController::class, 'settings']);
    Route::put('/admin/settings', [AdminController::class, 'updateSettings']);

    // Notifications
    Route::get('/admin/notifications', [AdminController::class, 'notifications']);
    Route::get('/admin/notifications/unread-count', [AdminController::class, 'notificationsUnreadCount']);
    Route::patch('/admin/notifications/{notification}/read', [AdminController::class, 'markNotificationRead']);
    Route::patch('/admin/notifications/read-all', [AdminController::class, 'markAllNotificationsRead']);
});

/*
|--------------------------------------------------------------------------
| روابط الموبايل المخصصة (Mobile Application APIs — Flutter)
|--------------------------------------------------------------------------
*/

// 1. روابط الحسابات المفتوحة للموبايل (بدون Token)
Route::post('/mobile/login', [MobileAuthController::class, 'login']);
Route::post('/mobile/register', [MobileAuthController::class, 'register']);

// 2. روابط الموبايل المحمية (تتطلب Token من Sanctum)
Route::middleware(['auth:sanctum', 'session.timeout'])->group(function () {
    // Auth
    Route::post('/mobile/logout', [MobileAuthController::class, 'logout']);
    Route::get('/mobile/profile', [MobileAuthController::class, 'profile']);
    Route::post('/mobile/profile', [MobileAuthController::class, 'updateProfile']);
    Route::put('/mobile/password', [MobileAuthController::class, 'updatePassword']);

    // Children
    Route::get('/mobile/children', [MobileChildController::class, 'index']);
    Route::get('/mobile/children/{child}', [MobileChildController::class, 'show']);
    Route::post('/mobile/children/register', [MobileChildController::class, 'registerChild']);
    Route::post('/mobile/children/{child}/photo', [MobileChildController::class, 'uploadPhoto']);
    Route::post('/mobile/children/{child}/footprint', [MobileChildController::class, 'uploadFootprint']);
    Route::post('/mobile/children/search', [MobileChildController::class, 'searchMissing']);

    // Missing Reports
    Route::post('/mobile/reports/missing', [MobileReportController::class, 'reportMissing']);
    Route::get('/mobile/reports', [MobileReportController::class, 'myReports']);
    Route::get('/mobile/reports/{report}', [MobileReportController::class, 'show']);

    // Verification Logs
    Route::get('/mobile/verification-logs', [MobileReportController::class, 'verificationLogs']);
});