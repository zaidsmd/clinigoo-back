<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\CityController as AdminCityController;
use App\Http\Controllers\Admin\PatientController as AdminPatientController;
use App\Http\Controllers\Admin\ProfessionalController as AdminProfessionalController;
use App\Http\Controllers\Admin\ProfessionController as AdminProfessionController;
use App\Http\Controllers\Admin\ProfessionDocumentController as AdminProfessionDocumentController;
use App\Http\Controllers\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\Client\AddressController;
use App\Http\Controllers\Api\Client\BeneficiaryController;
use App\Http\Controllers\Api\Client\CareRequestController;
use App\Http\Controllers\Api\Client\ProfessionController as ClientProfessionController;
use App\Http\Controllers\Api\Client\ProfessionDocumentController as ClientProfessionDocumentController;
use App\Http\Controllers\Api\Client\ServiceController as ClientServiceController;
use App\Http\Controllers\Api\ProfessionalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Auth Routes (Client / General)
Route::group([
    'prefix' => 'auth',
], function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware('auth:api');
    Route::get('me', [AuthController::class, 'me'])->middleware('auth:api');
    Route::put('password', [AuthController::class, 'updatePassword'])->middleware('auth:api');
    Route::put('preferences', [AuthController::class, 'updatePreferences'])->middleware('auth:api');
    Route::get('profile', [AuthController::class, 'profile'])->middleware('auth:api');
    Route::put('profile', [AuthController::class, 'updateProfile'])->middleware('auth:api');
});

// Admin Auth Routes
Route::group([
    'prefix' => 'admin/auth',
], function () {
    Route::post('login', [AdminAuthController::class, 'login']);
    Route::post('logout', [AdminAuthController::class, 'logout'])->middleware('auth:admin');
    Route::post('refresh', [AdminAuthController::class, 'refresh']);
    Route::get('me', [AdminAuthController::class, 'me'])->middleware('auth:admin');
    Route::get('profile', [AdminAuthController::class, 'profile'])->middleware('auth:admin');
    Route::put('profile', [AdminAuthController::class, 'updateProfile'])->middleware('auth:admin');
    Route::put('password', [AdminAuthController::class, 'updatePassword'])->middleware('auth:admin');
});

// Public Professional Registration & Status & Uniqueness
Route::post('public/professionals/check-unique', [ProfessionalController::class, 'checkUniqueness']);
Route::post('professionals/check-unique', [ProfessionalController::class, 'checkUniqueness']);
Route::post('public/professionals', [ProfessionalController::class, 'publicRegister']);
Route::get('public/professionals/{id}', [ProfessionalController::class, 'publicShow']);
Route::post('professionals', [ProfessionalController::class, 'publicRegister']);
Route::get('professionals/{id}', [ProfessionalController::class, 'publicShow']);

// Cities (Villes)
Route::get('public/cities', [CityController::class, 'index']);
Route::get('cities', [CityController::class, 'index']);

// Professional Dossier & Profile Routes
Route::group([
    'middleware' => ['auth:api'],
    'prefix' => 'professional',
], function () {
    Route::get('me', [ProfessionalController::class, 'dossier']);
    Route::get('dossier', [ProfessionalController::class, 'dossier']);
    Route::put('profile', [ProfessionalController::class, 'updateProfile']);
    Route::put('sensitive-info', [ProfessionalController::class, 'updateSensitiveInfo']);
    Route::put('availability', [ProfessionalController::class, 'updateAvailability']);
    Route::post('documents', [ProfessionalController::class, 'uploadDocument']);
    Route::get('documents/{document}/download', [ProfessionalController::class, 'downloadDocument']);
    Route::post('submit', [ProfessionalController::class, 'submit']);
});

// Admin Professional Management Routes
Route::group([
    'middleware' => ['auth:admin'],
    'prefix' => 'admin/professionals',
], function () {
    Route::get('/', [AdminProfessionalController::class, 'index']);
    Route::get('{id}', [AdminProfessionalController::class, 'show']);
    Route::post('{id}/documents/{documentId}/verify', [AdminProfessionalController::class, 'verifyDocument']);
    Route::post('{id}/verify', [AdminProfessionalController::class, 'verifyDossier']);
    Route::post('{id}/review-changes', [AdminProfessionalController::class, 'reviewSensitiveChanges']);
    Route::put('{id}/services', [AdminProfessionalController::class, 'updateServices']);
    Route::put('{id}/password', [AdminProfessionalController::class, 'updatePassword']);
    Route::get('{id}/history', [AdminProfessionalController::class, 'history']);
    Route::get('{id}/documents/{documentId}/download', [AdminProfessionalController::class, 'downloadDocument']);
});

// Admin Patients Routes
Route::group([
    'middleware' => ['auth:admin'],
    'prefix' => 'admin/patients',
], function () {
    Route::get('/', [AdminPatientController::class, 'index']);
    Route::post('/', [AdminPatientController::class, 'store']);
    Route::get('{id}', [AdminPatientController::class, 'show']);
    Route::match(['put', 'patch'], '{id}', [AdminPatientController::class, 'update']);
    Route::delete('{id}', [AdminPatientController::class, 'destroy']);
});

// Admin Services Routes
Route::group([
    'middleware' => ['auth:admin'],
    'prefix' => 'admin/services',
], function () {
    Route::get('/', [AdminServiceController::class, 'index']);
    Route::post('/', [AdminServiceController::class, 'store']);
    Route::get('{id}', [AdminServiceController::class, 'show']);
    Route::put('{id}', [AdminServiceController::class, 'update']);
    Route::delete('{id}', [AdminServiceController::class, 'destroy']);
});

// Admin Professions & Profession Documents Routes
Route::group([
    'middleware' => ['auth:admin'],
    'prefix' => 'admin',
], function () {
    // Profession Documents
    Route::get('profession-documents', [AdminProfessionDocumentController::class, 'index']);
    Route::post('profession-documents', [AdminProfessionDocumentController::class, 'store']);
    Route::get('profession-documents/{document}', [AdminProfessionDocumentController::class, 'show']);
    Route::put('profession-documents/{document}', [AdminProfessionDocumentController::class, 'update']);
    Route::delete('profession-documents/{document}', [AdminProfessionDocumentController::class, 'destroy']);

    // Professions
    Route::get('professions', [AdminProfessionController::class, 'index']);
    Route::post('professions', [AdminProfessionController::class, 'store']);
    Route::get('professions/{profession}', [AdminProfessionController::class, 'show']);
    Route::put('professions/{profession}', [AdminProfessionController::class, 'update']);
    Route::delete('professions/{profession}', [AdminProfessionController::class, 'destroy']);

    // Cities (Villes)
    Route::get('cities', [AdminCityController::class, 'index']);
    Route::post('cities', [AdminCityController::class, 'store']);
    Route::match(['put', 'patch'], 'cities/{city}', [AdminCityController::class, 'update']);
    Route::delete('cities/{city}', [AdminCityController::class, 'destroy']);
});

// Public Professions & Documents Routes
Route::get('professions', [ClientProfessionController::class, 'index']);
Route::get('professions/{profession}', [ClientProfessionController::class, 'show']);
Route::get('profession-documents', [ClientProfessionDocumentController::class, 'index']);
Route::get('profession-documents/{document}', [ClientProfessionDocumentController::class, 'show']);
Route::get('professions/{profession}/documents', [ClientProfessionDocumentController::class, 'getDocumentsByProfession']);
Route::get('professions/{profession}/services', [ClientProfessionController::class, 'getProfessionServices']);

Route::group(['prefix' => 'public'], function () {
    Route::get('professions', [ClientProfessionController::class, 'index']);
    Route::get('professions/{profession}', [ClientProfessionController::class, 'show']);
    Route::get('profession-documents', [ClientProfessionDocumentController::class, 'index']);
    Route::get('profession-documents/{document}', [ClientProfessionDocumentController::class, 'show']);
    Route::get('professions/{profession}/documents', [ClientProfessionDocumentController::class, 'getDocumentsByProfession']);
    Route::get('professions/{profession}/services', [ClientProfessionController::class, 'getProfessionServices']);
});

// Authenticated Client Routes
Route::middleware('auth:api')->group(function () {
    Route::get('services/recent', [ClientServiceController::class, 'recent']);
    Route::apiResource('services', ClientServiceController::class)->only('index', 'show');
    Route::apiResource('addresses', AddressController::class);
    Route::apiResource('beneficiaries', BeneficiaryController::class);
    Route::apiResource('care-requests', CareRequestController::class)->except('destroy');

    Route::get('user', function (Request $request) {
        return $request->user();
    });
});
