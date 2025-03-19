<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\ServicesController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductTransactionController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ServiceUsageController;
use App\Http\Controllers\ServiceTransactionController;
use App\Models\Location;

/**
 * @OA\Info(
 *     title="Your API Title",
 *     version="1.0.0",
 *     description="API documentation for your Laravel application.",
 *     @OA\Contact(
 *         email="support@yourapp.com"
 *     ),
 *     @OA\License(
 *         name="Apache 2.0",
 *         url="http://www.apache.org/licenses/LICENSE-2.0.html"
 *     )
 * )
 *
 * @OA\Server(
 *     url="http://your-app-url/api",
 *     description="API Server"
 * )
 */

Route::post('/service-transactions', [ServiceTransactionController::class, 'store']);
Route::get('/verify-email/{token}', 'AuthController@verifyEmail');

// Authentication routes
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login'])->name('login');

Route::post('password/forgot', [AuthController::class, 'forgotPassword']);
Route::post('password/reset', [AuthController::class, 'resetPassword']);
Route::post('AddCustomer', [AuthController::class, 'customerAdd']);
Route::get('/product-sale-report',[ProductTransactionController::class,'productSale']);
Route::get('/service-purchased-report',[ServiceTransactionController::class,'servicePurchased']);
Route::get('/service-used-report',[ServiceTransactionController::class,'serviceUsed']);
Route::get('stats/{id}', [UserProfileController::class, 'stats']);
Route::post('/product-saled-report',[ProductTransactionController::class,'productSaled']);
Route::post('/service-purchase-report',[ServiceTransactionController::class,'servicePurchase']);
Route::post('/service-use-report',[ServiceTransactionController::class,'serviceUse']);
Route::post('getUserd',[AuthController::class,'getUserd']);
Route::post('/customerDayUsage',[ServiceTransactionController::class,'customerDayUsage']);
Route::get('/customers/search', [CustomerController::class, 'searchCustomers']);
Route::post('/customers/date-range', [CustomerController::class, 'customerDateRange']);
Route::get('/users', [CustomerController::class, 'getAllUsers']);

// Route::group(['middleware' => 'auth:api'], function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('me', [AuthController::class, 'me']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::get('/totalSpend/{id}',[ServiceTransactionController::class,'totalSpend']);

    Route::get('getUser',[AuthController::class,'getUser']);
    Route::get('/customers', [CustomerController::class, 'getAllCustomers']);
    Route::get('/user-profiles', [UserProfileController::class, 'index']);
    Route::post('user-profiles', [UserProfileController::class, 'store']);
    Route::get('user-profiles/{id}', [UserProfileController::class, 'show']);
    Route::put('user-profiles/{id}', [UserProfileController::class, 'update']);
    Route::delete('user-profiles/{id}', [UserProfileController::class, 'destroy']);
    Route::post('/getUserByLocation',[UserProfileController::class,'getUserByLocation']);
    Route::get('/clearData',[UserProfileController::class,'clearData']);

    // Restore soft-deleted user route
    Route::patch('user-profiles/{id}/restore', [UserProfileController::class, 'restore']);



    Route::get('services', [ServicesController::class, 'index']);
    Route::post('services', [ServicesController::class, 'store']);
    Route::get('services/{id}', [ServicesController::class, 'show']);
    Route::put('services/{id}', [ServicesController::class, 'update']);
    Route::delete('services/{id}', [ServicesController::class, 'destroy']);
    Route::post('/update/minutes',[ServiceTransactionController::class,'creditMinutes']);



    // Product routes
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']); // Get all products
        Route::post('/', [ProductController::class, 'store']); // Create a new product
        Route::get('/{id}', [ProductController::class, 'show']); // Get a product by ID
        Route::put('/{id}', [ProductController::class, 'update']); // Update a product
        Route::delete('/{id}', [ProductController::class, 'destroy']); // Delete a product
    });



    Route::get('/locations',[LocationController::class,'index']);
    Route::post('/locations',[LocationController::class,'store']);
    Route::get('/locations/{id}',[LocationController::class]);
    Route::put('/location/{id}',[LocationController::class,'update']);
    Route::delete("/locations/{id}",[LocationController::class,'destroy']);


    Route::post('getQR',[LocationController::class,'generate']);



    Route::get('/product-transactions', [ProductTransactionController::class, 'index']);
    Route::post('/product-transactions', [ProductTransactionController::class, 'store']);
    Route::get('/product-transactions/{id}', [ProductTransactionController::class, 'show']);
    Route::put('/product-transactions/{id}', [ProductTransactionController::class, 'update']);
    Route::delete('/product-transactions/{id}', [ProductTransactionController::class, 'destroy']);
    Route::post('/product-all',[ProductTransactionController::class,'storeAll']);



    Route::get('/service-transactions', [ServiceTransactionController::class, 'index']);

    Route::get('/service-transactions/{id}', [ServiceTransactionController::class, 'show']);
    Route::put('/service-transactions/{id}', [ServiceTransactionController::class, 'update']);
    Route::delete('/service-transactions/{id}', [ServiceTransactionController::class, 'destroy']);
    Route::get('/getTransaction/{id}',[ServicesController::class,'getAvailableService']);
    Route::get('/UsedMin/{id}',[ServiceTransactionController::class,'minuteUsed']);
    Route::get('/UsedMin',[ServiceTransactionController::class,'minuteUsed']);

// });
