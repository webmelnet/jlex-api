<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\OrderQueueController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\ImageController;
use App\Http\Controllers\Api\FeaturedProductController;
use App\Http\Controllers\Api\HeroSlideController;
use App\Http\Controllers\Api\SalesBannerController;
use App\Http\Controllers\Api\PromoVideoController;
use App\Http\Controllers\Api\ShiftSettlementController;
use App\Http\Controllers\Api\ExchangeController;
use App\Http\Controllers\Api\WebsiteConfigurationController;
use App\Http\Controllers\Api\AppSettingController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    $user = $request->user();
    $user->role = $user->getRoleAttribute();
    return $user;
});

Route::post('/verify-invitation/{token}', [UserController::class, 'verifyInvitation']);
Route::post('/set-password', [UserController::class, 'setPassword']);

// Public store routes (no auth required — read-only catalogue data)
Route::prefix('website')->group(function () {
    Route::get('/settings', [WebsiteConfigurationController::class, 'index']);
    Route::get('/featured-products', [FeaturedProductController::class, 'index']);
    Route::get('/hero-slides', [HeroSlideController::class, 'index']);
    Route::get('/sales-banners', [SalesBannerController::class, 'index']);
    Route::get('/promo-videos', [PromoVideoController::class, 'index']);
});

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/search', [ProductController::class, 'search']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/brands', [BrandController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {

    // User Routes
    Route::delete('/users/{user}/force', [UserController::class, 'forceDelete']);
    Route::post('/users/{user}/restore', [UserController::class, 'restore']);
    Route::get('/users/trashed', [UserController::class, 'trashedUsers']);
    Route::get('/users/role/{role}', [UserController::class, 'getUsersByRole']);
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);
    Route::post('/users/{user}/update-roles', [UserController::class, 'updateRoles']);
    Route::apiResource('/users', UserController::class);

    // Role Routes
    Route::apiResource('/roles', RoleController::class);

    // Product Routes
    Route::post('/products/bulk', [ProductController::class, 'bulkStore']); // Must be before resource routes
    Route::get('/products/export', [ProductController::class, 'export']);
    Route::post('/products/import', [ProductController::class, 'import']);
    Route::get('/products/last-sku', [ProductController::class, 'getLastSku']);
    Route::get('/products/low-stock', [ProductController::class, 'lowStock']);
    Route::get('/products/out-of-stock', [ProductController::class, 'outOfStock']);
    Route::get('/products/near-expiration', [ProductController::class, 'nearExpiration']);
    Route::get('/products/trashed', [ProductController::class, 'trashedProducts']);
    Route::post('/products/{id}/restore', [ProductController::class, 'restore']);
    Route::delete('/products/{id}/force', [ProductController::class, 'forceDelete']);
    // index & show are public (registered above); only protect write operations
    Route::apiResource('/products', ProductController::class)->except(['index', 'show']);

    // Category Routes
    Route::get('/categories/trashed', [CategoryController::class, 'trashedCategories']);
    Route::post('/categories/reorder', [CategoryController::class, 'reorder']);
    Route::post('/categories/{id}/restore', [CategoryController::class, 'restore']);
    Route::delete('/categories/{id}/force', [CategoryController::class, 'forceDelete']);
    Route::delete('/categories/{category}/image', [CategoryController::class, 'removeImage']);
    Route::apiResource('/categories', CategoryController::class)->except(['index']);

    // Brand Routes
    Route::get('/brands/trashed', [BrandController::class, 'trashedBrands']);
    Route::post('/brands/reorder', [BrandController::class, 'reorder']);
    Route::post('/brands/{id}/restore', [BrandController::class, 'restore']);
    Route::delete('/brands/{id}/force', [BrandController::class, 'forceDelete']);
    Route::apiResource('/brands', BrandController::class)->except(['index']);

    // Supplier Routes
    Route::get('/suppliers/trashed', [SupplierController::class, 'trashedSuppliers']);
    Route::post('/suppliers/{id}/restore', [SupplierController::class, 'restore']);
    Route::delete('/suppliers/{id}/force', [SupplierController::class, 'forceDelete']);
    Route::apiResource('/suppliers', SupplierController::class);

    // Customer Routes
    Route::post('/customers/{customer}/add-loyalty-points', [CustomerController::class, 'addLoyaltyPoints']);
    Route::post('/customers/{customer}/deduct-loyalty-points', [CustomerController::class, 'deductLoyaltyPoints']);
    Route::get('/customers/trashed', [CustomerController::class, 'trashedCustomers']);
    Route::post('/customers/{id}/restore', [CustomerController::class, 'restore']);
    Route::delete('/customers/{id}/force', [CustomerController::class, 'forceDelete']);
    Route::apiResource('/customers', CustomerController::class);

    // Shift Settlement Routes
    Route::post('/shift-settlements', [ShiftSettlementController::class, 'store']);
    Route::get('/shift-settlements/today', [ShiftSettlementController::class, 'today']);
    Route::get('/shift-settlements/by-date', [ShiftSettlementController::class, 'byDate']);

    // Exchange Routes
    Route::post('/exchanges/lookup-sale', [ExchangeController::class, 'lookupSale']);
    Route::apiResource('/exchanges', ExchangeController::class)->only(['index', 'store', 'show']);

    // Sale Routes
    Route::post('/sales/{sale}/cancel', [SaleController::class, 'cancel']);
    Route::get('/sales/today', [SaleController::class, 'todaySales']);
    Route::get('/sales/report', [SaleController::class, 'report']);
    Route::get('/sales/trashed', [SaleController::class, 'trashedSales']);
    Route::post('/sales/{id}/restore', [SaleController::class, 'restore']);
    Route::delete('/sales/{id}/force', [SaleController::class, 'forceDelete']);
    Route::post('/sales/upload-gcash-screenshot', [SaleController::class, 'uploadGcashScreenshot']);
    Route::apiResource('/sales', SaleController::class);

    // Order Queue Routes
    Route::post('/order-queue/{orderQueue}/claim', [OrderQueueController::class, 'claim']);
    Route::post('/order-queue/{orderQueue}/release', [OrderQueueController::class, 'release']);
    Route::post('/order-queue/{orderQueue}/cancel', [OrderQueueController::class, 'cancel']);
    Route::get('/order-queue', [OrderQueueController::class, 'index']);
    Route::post('/order-queue', [OrderQueueController::class, 'store']);
    Route::get('/order-queue/{orderQueue}', [OrderQueueController::class, 'show']);

    // Purchase Order Routes
    Route::post('/purchase-orders/{purchaseOrder}/receive-items', [PurchaseOrderController::class, 'receiveItems']);
    Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel']);
    Route::get('/purchase-orders/trashed', [PurchaseOrderController::class, 'trashedPurchaseOrders']);
    Route::post('/purchase-orders/{id}/restore', [PurchaseOrderController::class, 'restore']);
    Route::delete('/purchase-orders/{id}/force', [PurchaseOrderController::class, 'forceDelete']);
    Route::apiResource('/purchase-orders', PurchaseOrderController::class);

    // Stock/Inventory Routes
    Route::post('/stock/adjust', [StockController::class, 'adjust']);
    Route::get('/stock/movements', [StockController::class, 'movements']);
    Route::get('/stock/inventory-value', [StockController::class, 'inventoryValue']);
    Route::get('/stock/low-stock-alert', [StockController::class, 'lowStockAlert']);
    Route::get('/stock/report', [StockController::class, 'report']);

    // Website / CMS Routes (authenticated)
    Route::prefix('website')->group(function () {
        Route::get('/featured-products/all', [FeaturedProductController::class, 'adminIndex']);
        Route::post('/featured-products', [FeaturedProductController::class, 'sync']);

        Route::get('/hero-slides/all', [HeroSlideController::class, 'adminIndex']);
        Route::post('/hero-slides', [HeroSlideController::class, 'store']);
        Route::post('/hero-slides/reorder', [HeroSlideController::class, 'reorder']);
        Route::put('/hero-slides/{slide}', [HeroSlideController::class, 'update']);
        Route::patch('/hero-slides/{slide}', [HeroSlideController::class, 'update']);
        Route::delete('/hero-slides/{slide}', [HeroSlideController::class, 'destroy']);

        Route::get('/sales-banners/all', [SalesBannerController::class, 'adminIndex']);
        Route::post('/sales-banners', [SalesBannerController::class, 'store']);
        Route::post('/sales-banners/reorder', [SalesBannerController::class, 'reorder']);
        Route::put('/sales-banners/{banner}', [SalesBannerController::class, 'update']);
        Route::patch('/sales-banners/{banner}', [SalesBannerController::class, 'update']);
        Route::delete('/sales-banners/{banner}', [SalesBannerController::class, 'destroy']);

        Route::get('/promo-videos/all', [PromoVideoController::class, 'adminIndex']);
        Route::post('/promo-videos', [PromoVideoController::class, 'store']);
        Route::post('/promo-videos/reorder', [PromoVideoController::class, 'reorder']);
        Route::put('/promo-videos/{video}', [PromoVideoController::class, 'update']);
        Route::patch('/promo-videos/{video}', [PromoVideoController::class, 'update']);
        Route::delete('/promo-videos/{video}', [PromoVideoController::class, 'destroy']);

        Route::get('/configurations', [WebsiteConfigurationController::class, 'adminIndex']);
        Route::post('/configurations', [WebsiteConfigurationController::class, 'bulkUpdate']);
    });

    // App Settings (feature toggles) — read: any authenticated user, write/admin-list: Superadmin only (enforced in controller)
    Route::prefix('app-settings')->group(function () {
        Route::get('/', [AppSettingController::class, 'index']);
        Route::get('/all', [AppSettingController::class, 'adminIndex']);
        Route::post('/', [AppSettingController::class, 'bulkUpdate']);
    });

    Route::prefix('images')->group(function () {
        // Get specific image
        Route::get('/{image}', [ImageController::class, 'show']);
        
        // Update image metadata
        Route::put('/{image}', [ImageController::class, 'update']);
        Route::patch('/{image}', [ImageController::class, 'update']);
        
        // Delete image(s)
        Route::delete('/{image}', [ImageController::class, 'destroy']);
        Route::delete('/', [ImageController::class, 'bulkDestroy']);
        
        // Set as primary
        Route::post('/{image}/set-primary', [ImageController::class, 'setPrimary']);
    });

});

// Public product show — registered LAST so specific sub-routes above take precedence
Route::get('/products/{product}', [ProductController::class, 'show']);