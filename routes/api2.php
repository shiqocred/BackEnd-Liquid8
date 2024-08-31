<?php

use App\Http\Controllers\ArchiveStorageController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BundleController;
use App\Http\Controllers\BundleQcdController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckConnectionController;
use App\Http\Controllers\ColorTagController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DestinationController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FilterQcdController;
use App\Http\Controllers\GenerateController;
use App\Http\Controllers\MigrateController;
use App\Http\Controllers\MigrateDocumentController;
use App\Http\Controllers\NewProductController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaletController;
use App\Http\Controllers\PaletFilterController;
use App\Http\Controllers\PaletImageController;
use App\Http\Controllers\PaletProductController;
use App\Http\Controllers\PalletBrandController;
use App\Http\Controllers\ProductApproveController;
use App\Http\Controllers\ProductBrandController;
use App\Http\Controllers\ProductBundleController;
use App\Http\Controllers\ProductConditionController;
use App\Http\Controllers\ProductFilterController;
use App\Http\Controllers\ProductOldController;
use App\Http\Controllers\ProductQcdController;
use App\Http\Controllers\ProductStatusController;
use App\Http\Controllers\PromoController;
use App\Http\Controllers\RepairController;
use App\Http\Controllers\RepairFilterController;
use App\Http\Controllers\RepairProductController;
use App\Http\Controllers\RiwayatCheckController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleDocumentController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SpecialTransactionController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::fallback(function () {
   return response()->json(['status' => false, 'message' => 'Not Found!'], 404);
});

//patokan urutan role : Admin,Spv,Team leader,Admin Kasir,Crew,Reparasi,

// =========================================== Dashboard ==================================================

// Dashboard : Admin,Spv,Team leader,Admin Kasir
Route::middleware(['auth:sanctum', 'check.role:Admin,Spv,Team leader,Admin Kasir'])->group(function () {
   Route::get('dashboard', [DashboardController::class, 'index']);
   Route::get('dashboard2', [DashboardController::class, 'index2']);
   Route::get('dashboard/summary-transaction', [DashboardController::class, 'summaryTransaction']);
   Route::get('dashboard/summary-sales', [DashboardController::class, 'summarySales']);
   Route::get('dashboard/storage-report', [DashboardController::class, 'storageReport']);
   Route::get('dashboard/monthly-analytic-sales', [DashboardController::class, 'monthlyAnalyticSales']);
   Route::get('dashboard/yearly-analytic-sales', [DashboardController::class, 'yearlyAnalyticSales']);
   Route::get('dashboard/general-sales', [DashboardController::class, 'generalSale']);
   Route::get('generateExcel_StorageReport', [DashboardController::class, 'generateExcel_StorageReport']);
   Route::get('dashboard/analytic-slow-moving', [DashboardController::class, 'analyticSlowMoving']);
   Route::get('export/product-expired', [DashboardController::class, 'productExpiredExport']);
});


//=========================================== inbound ==========================================================
//inbound process, check history, check product, : Admin,Spv,Team leader

Route::middleware(['auth:sanctum', 'check.role:Admin,Spv,Team leader'])->group(function () {
   //generates file excel -> input data ekspedisi 
   Route::post('/generate', [GenerateController::class, 'processExcelFiles']);
   Route::post('/generate/merge-headers', [GenerateController::class, 'mapAndMergeHeaders']);

   //product old
   Route::get('product_olds-search', [ProductOldController::class, 'searchByDocument']);
   // Route::delete('delete-all-products-old', [ProductOldController::class, 'deleteAll']);
   Route::get('product_olds-search', [ProductOldController::class, 'searchByDocument']);
   Route::get('search_barcode_product', [ProductOldController::class, 'searchByBarcode']);

   //product approve
   Route::resource('product-approves', ProductApproveController::class)->except(['destroy']);
   Route::get('productApprovesByDoc', [ProductApproveController::class, 'searchByDocument']);
   // Route::delete('delete_all_by_codeDocument', [ProductApproveController::class, 'delete_all_by_codeDocument']);
   Route::get('product-approveByDoc/{code_document}', [ProductApproveController::class, 'productsApproveByDoc'])
   ->where('code_document', '.*');

   
   //document
   Route::resource('/documents', DocumentController::class)->except(['destroy']);
   // Route::delete('/delete-all-documents', [DocumentController::class, 'deleteAll']);
   Route::get('/documentDone', [DocumentController::class, 'documentDone']);
   Route::get('/documentInProgress', [DocumentController::class, 'documentInProgress']);
});

Route::middleware(['auth:sanctum', 'check.role:Admin'])->group(function () {
   Route::post('register', [AuthController::class, 'register']);
   Route::resource('users', UserController::class)->except(['store']);
   Route::resource('roles', RoleController::class);
   Route::get('generateApikey/{userId}', [UserController::class, 'generateApiKey']);
});
