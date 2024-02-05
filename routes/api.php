<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BundleController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ColorTagController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\GenerateController;
use App\Http\Controllers\MigrateController;
use App\Http\Controllers\MigrateDocumentController;
use App\Http\Controllers\NewProductController;
use App\Http\Controllers\PaletController;
use App\Http\Controllers\PaletFilterController;
use App\Http\Controllers\PaletProductController;
use App\Http\Controllers\ProductBundleController;
use App\Http\Controllers\ProductFilterController;
use App\Http\Controllers\ProductOldController;
use App\Http\Controllers\PromoController;
use App\Http\Controllers\RiwayatCheckController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleDocumentController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SpecialTransactionController;
use App\Http\Controllers\UserController;
use App\Models\New_product;
use App\Models\RiwayatCheck;
use App\Models\SpecialTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


Route::middleware(['auth:sanctum', 'check.role:Reparasi, Spv, Admin'])->group(function () {
    // =========================================== repair station ==================================================
    Route::get('repair', [NewProductController::class, 'showRepair']);
    Route::put('repair/update/{id}', [NewProductController::class, 'updateRepair']);
    Route::post('repair/multiple-update', [NewProductController::class, 'MultipleUpdateRepair']);
    Route::post('repair/all-update', [NewProductController::class, 'updateAllDamagedOrAbnormal']);
    Route::get('/excelolds', [NewProductController::class, 'excelolds']);

    //list dump
    Route::get('/dumps', [NewProductController::class, 'listDump']);
    Route::put('/update-dumps/{id}', [NewProductController::class, 'updateDump']);
});

Route::middleware(['auth:sanctum', 'check.role:Admin kasir'])->group(function () {
    //=========================================== outbound ==========================================================

    //migrate
    Route::resource('migrates', MigrateController::class);
    Route::resource('migrate-documents', MigrateDocumentController::class);
});

Route::middleware(['auth:sanctum', 'check.role:Spv, Team leader, Admin'])->group(function () {

    //=========================================== storage ==========================================================

    //slow moving products 
    //filters product bundle
    Route::get('bundle/filter_product', [ProductFilterController::class, 'index']);
    Route::post('bundle/filter_product/{id}/add', [ProductFilterController::class, 'store']);
    Route::delete('bundle/filter_product/destroy/{id}', [ProductFilterController::class, 'destroy']);

    //bundle
    Route::get('bundle', [BundleController::class, 'index']);
    Route::get('bundle/{bundle}', [BundleController::class, 'show']);
    Route::post('bundle', [ProductBundleController::class, 'store']);
    Route::delete('bundle/{bundle}', [BundleController::class, 'destroy']);

    Route::get('bundle/product', [ProductBundleController::class, 'index']);
    Route::delete('bundle/destroy/{id}', [ProductBundleController::class, 'destroy']);

    //promo
    Route::get('promo', [PromoController::class, 'index']);
    Route::get('promo/{id}', [PromoController::class, 'show']);
    Route::post('promo', [PromoController::class, 'store']);
    Route::put('promo/{promo}', [PromoController::class, 'update']);
    Route::delete('promo/destroy/{promoId}/{productId}', [PromoController::class, 'destroy']);

    //palet filter
    Route::get('palet/filter_product', [PaletFilterController::class, 'index']);
    Route::post('palet/filter_product/{id}/add', [PaletFilterController::class, 'store']);
    Route::delete('palet/filter_product/destroy/{id}', [PaletFilterController::class, 'destroy']);

    //palet
    Route::get('palet/display', [PaletController::class, 'display']);
    Route::get('palet', [PaletController::class, 'index']);
    Route::post('palet', [PaletProductController::class, 'store']);
    Route::delete('palet/{palet}', [PaletController::class, 'destroy']);

     //categories discount
     Route::resource('categories', CategoryController::class);

     //colortags diskon
     Route::resource('color_tags', ColorTagController::class);
});



Route::middleware(['auth:sanctum', 'check.role:crew, Team leader, Spv, Admin'])->group(function () {
    //=========================================== inbound ==========================================================

    //generates file excel -> input data ekspedisi 
    Route::post('/generate', [GenerateController::class, 'processExcelFiles']);
    Route::post('/generate/merge-headers', [GenerateController::class, 'mapAndMergeHeaders']);
    Route::post('/excelOld', [NewProductController::class, 'processExcelFiles']);
    Route::post('/excelOld/merge', [NewProductController::class, 'mapAndMergeHeaders']);

    //product old
    Route::resource('product_olds', ProductOldController::class);
    Route::delete('delete-all-products-old', [ProductOldController::class, 'deleteAll']);
    Route::get('product_olds-search', [ProductOldController::class, 'searchByDocument']);
    Route::get('search_barcode_product', [ProductOldController::class, 'searchByBarcode']);

    //new product (hasil scan)
    Route::resource('new_products', NewProductController::class);
    Route::delete('/delete-all-new-products', [NewProductController::class, 'deleteAll']);
    Route::get('new_product/cronjob/expired', [NewProductController::class, 'expireProducts']);
    Route::get('new_product/expired', [NewProductController::class, 'listProductExp']);
    Route::post('new_product/excelImport', [NewProductController::class, 'excelImport']);
    Route::get('/new_product/document', [NewProductController::class, 'byDocument']);

    //document
    Route::resource('/documents', DocumentController::class);
    Route::delete('/delete-all-documents', [DocumentController::class, 'deleteAll']);

     //categories discount
     Route::get('categories', [CategoryController::class, 'index']);

     //colortags diskon
     Route::get('color_tags', [ColorTagController::class, 'index']);

    //riwayat
    Route::resource('historys', RiwayatCheckController::class);
    Route::get('riwayat-document', [RiwayatCheckController::class, 'getByDocument']);

    Route::get('/admin/approve/{userId}/{transactionId}', [SpecialTransactionController::class, 'approveTransaction'])->name('admin.approve');

});


Route::middleware(['auth:sanctum', 'check.role:Admin'])->group(function () {
    Route::resource('users', UserController::class)->except(['store']);
    Route::resource('roles', RoleController::class);

});

Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);
