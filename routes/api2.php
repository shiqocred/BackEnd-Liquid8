<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\PaletController;
use App\Http\Controllers\PromoController;
use App\Http\Controllers\BundleController;
use App\Http\Controllers\RepairController;
use App\Http\Controllers\MigrateController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ColorTagController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\GenerateController;
use App\Http\Controllers\BundleQcdController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FilterQcdController;
use App\Http\Controllers\NewProductController;
use App\Http\Controllers\PaletImageController;
use App\Http\Controllers\ProductOldController;
use App\Http\Controllers\ProductQcdController;
use App\Http\Controllers\DestinationController;
use App\Http\Controllers\PaletFilterController;
use App\Http\Controllers\PalletBrandController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaletProductController;
use App\Http\Controllers\ProductBrandController;
use App\Http\Controllers\RepairFilterController;
use App\Http\Controllers\RiwayatCheckController;
use App\Http\Controllers\SaleDocumentController;
use App\Http\Controllers\FilterStagingController;
use App\Http\Controllers\ProductBundleController;
use App\Http\Controllers\ProductFilterController;
use App\Http\Controllers\ProductStatusController;
use App\Http\Controllers\RepairProductController;
use App\Http\Controllers\ArchiveStorageController;
use App\Http\Controllers\ProductApproveController;
use App\Http\Controllers\StagingApproveController;
use App\Http\Controllers\StagingProductController;
use App\Http\Controllers\CheckConnectionController;
use App\Http\Controllers\MigrateDocumentController;
use App\Http\Controllers\ProductConditionController;
use App\Http\Controllers\SpecialTransactionController;

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

// end dashboard =========================================== Dashboard ==================================================


//=========================================== inbound ==========================================================

//inbound process, check history, check product, Manual inbound : Admin,Spv,Team leader
Route::middleware(['auth:sanctum', 'check.role:Admin,Spv,Team leader'])->group(function () {
   //generates file excel -> input data ekspedisi 
   Route::post('/generate', [GenerateController::class, 'processExcelFiles']);
   Route::post('/generate/merge-headers', [GenerateController::class, 'mapAndMergeHeaders']);

   //scam
   //change barcode
   Route::post('changeBarcodeDocument', [DocumentController::class, 'changeBarcodeDocument']);

   //product approve 
   Route::resource('product-approves', ProductApproveController::class)->except(['destroy']);

   Route::get('productApprovesByDoc', [ProductApproveController::class, 'searchByDocument']);
   // Route::delete('delete_all_by_codeDocument', [ProductApproveController::class, 'delete_all_by_codeDocument']);
   Route::get('product-approveByDoc/{code_document}', [ProductApproveController::class, 'productsApproveByDoc'])
      ->where('code_document', '.*');

   // Route::delete('/delete-all-documents', [DocumentController::class, 'deleteAll']);
   Route::get('/documentDone', [DocumentController::class, 'documentDone']);
   Route::get('/documentInProgress', [DocumentController::class, 'documentInProgress']);
   Route::get('get-latestPrice', [NewProductController::class, 'getLatestPrice']);

   //riwayat
   Route::resource('historys', RiwayatCheckController::class)->except(['destroy']);
   Route::get('riwayat-document/code_document', [RiwayatCheckController::class, 'getByDocument']);
   Route::post('history/exportToExcel', [RiwayatCheckController::class, 'exportToExcel']);

   //notifications
   Route::resource('notifications', NotificationController::class)->except(['destroy']);

   //manual inbound
   Route::post('add_product', [NewProductController::class, 'addProductByAdmin']);
});

//manifest inbound, histroy index : Admin,Spv,Team leader,Crew
Route::middleware(['auth:sanctum', 'check.role:Admin,Spv,Team leader,Crew'])->group(function () {
   //product old
   Route::get('product_olds-search', [ProductOldController::class, 'searchByDocument']);
   // Route::delete('delete-all-products-old', [ProductOldController::class, 'deleteAll']);
   Route::get('product_olds-search', [ProductOldController::class, 'searchByDocument']);
   Route::get('search_barcode_product', [ProductOldController::class, 'searchByBarcode']);

   //send approve
   Route::post('product-approves', [ProductApproveController::class, 'store']);

   //document
   Route::resource('/documents', DocumentController::class)->except(['destroy']);

   //riwayat
   Route::get('historys', [RiwayatCheckController::class, 'index']);
   Route::post('historys', [RiwayatCheckController::class, 'store']);
});

//inbound bulking
Route::middleware(['auth:sanctum', 'check.role:Admin,Spv'])->group(function () {
   //bulking
   Route::post('/excelOld', [StagingProductController::class, 'processExcelFilesCategoryStaging']);
   Route::post('/bulkingInventory', [NewProductController::class, 'processExcelFilesCategory']);
   // Route::post('/excelOld/merge', [NewProductController::class, 'mapAndMergeHeadersCategory']);
   Route::post('/bulking_tag_warna', [NewProductController::class, 'processExcelFilesTagColor']);
});

//end inbound =========================================== inbound ==========================================================

//=========================================== Staging ==========================================================

// Admin,Spv,Admin Kasir
Route::middleware(['auth:sanctum', 'check.role:Admin,Spv,Admin Kasir'])->group(function () {
   //store nya untuk mindah ke approve staging
   Route::resource('staging_products', StagingProductController::class);
   Route::get('staging/filter_product', [FilterStagingController::class, 'index']);
   Route::post('staging/filter_product/{id}/add', [FilterStagingController::class, 'store']);
   Route::delete('staging/filter_product/destroy/{id}', [FilterStagingController::class, 'destroy']);
});
//product staging approve
Route::middleware(['auth:sanctum', 'check.role:Admin,Spv'])->group(function () {
   //untuk spv me approve staging ke inventory 
   Route::resource('staging_approves', StagingApproveController::class);
});

//end staging =========================================== Staging ==========================================================


//=========================================== Inventory ==========================================================
//product by category,color : Admin,Spv,Team leader,Admin Kasir

Route::middleware(['auth:sanctum', 'check.role:Admin,Spv,Team leader,Admin Kasir'])->group(function () {
   //colortags dan category
   Route::get('categories', [CategoryController::class, 'index']);

   Route::get('color_tags', [ColorTagController::class, 'index']);
   Route::get('product_byColor', [NewProductController::class, 'getTagColor']);
   Route::get('product_byCategory', [NewProductController::class, 'getByCategory']);

   //filters product repair
   Route::get('repair-mv/filter_product', [RepairFilterController::class, 'index']);
   Route::post('repair-mv/filter_product/{id}/add', [RepairFilterController::class, 'store']);
   // Route::delete('repair-mv/filter_product/destroy/{id}', [RepairFilterController::class, 'destroy']);

   //repair
   Route::get('repair-mv', [RepairController::class, 'index']);
   Route::get('repair-mv/{repair}', [RepairController::class, 'show']);
   Route::post('repair-mv', [RepairProductController::class, 'store']);
   Route::delete('repair-mv/{repair}', [RepairController::class, 'destroy']);
   Route::get('getByNameColor', [ColorTagController::class, 'getByNameColor']);

   Route::get('repair-mv/product', [RepairProductController::class, 'index']);
   Route::get('repair-product-mv/{repairProduct}', [RepairProductController::class, 'show']);
   Route::delete('repair-mv/destroy/{id}', [RepairProductController::class, 'destroy']);

   Route::put('product-repair/{repairProduct}', [RepairProductController::class, 'update']);
   Route::delete('product-repair/{repairProduct}', [RepairProductController::class, 'destroy']);
   Route::put('/update-repair-dump/{id}', [RepairProductController::class, 'updateRepair']);


});

Route::middleware(['auth:sanctum', 'check.role:Admin,Spv,Team leader'])->group(function () {
   /// moving products 

   Route::get('new_product/display-expired', [NewProductController::class, 'listProductExpDisplay']);

   //filters product bundle
   Route::get('bundle/filter_product', [ProductFilterController::class, 'index']);
   Route::post('bundle/filter_product/{id}/add', [ProductFilterController::class, 'store']);
   Route::delete('bundle/filter_product/destroy/{id}', [ProductFilterController::class, 'destroy']);

   //bundle
   Route::get('bundle', [BundleController::class, 'index']);
   Route::get('bundle/{bundle}', [BundleController::class, 'show']);
   Route::post('bundle', [ProductBundleController::class, 'store']);
   Route::delete('bundle/{bundle}', [BundleController::class, 'destroy']);
   Route::get('product-bundle/{new_product}/{bundle}/add', [ProductBundleController::class, 'addProductBundle']);

   Route::get('bundle/product', [ProductBundleController::class, 'index']);
   Route::delete('bundle/destroy/{id}', [ProductBundleController::class, 'destroy']);

   //palet filter
   Route::get('palet/filter_product', [PaletFilterController::class, 'index']);
   Route::post('palet/filter_product/{id}/add', [PaletFilterController::class, 'store']);
   Route::delete('palet/filter_product/destroy/{id}', [PaletFilterController::class, 'destroy']);

   //palet
   Route::get('palet/display', [PaletController::class, 'display']);
   Route::get('palet', [PaletController::class, 'index']);
   Route::get('palet/{palet}', [PaletController::class, 'show']);
   Route::post('palet', [PaletProductController::class, 'store']);
   Route::delete('palet/{palet}', [PaletController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'check.role:Admin,Spv'])->group(function () {
   //colortags dan category
   Route::resource('categories', CategoryController::class)->except(['destroy']);
   //colortags diskon
   Route::resource('color_tags', ColorTagController::class)->except(['destroy']);
});



//admin
Route::middleware(['auth:sanctum', 'check.role:Admin'])->group(function () {
   Route::post('register', [AuthController::class, 'register']);
   Route::resource('users', UserController::class)->except(['store']);
   Route::resource('roles', RoleController::class);
   Route::get('generateApikey/{userId}', [UserController::class, 'generateApiKey']);
});


//login
Route::post('login', [AuthController::class, 'login']);

Route::delete('cleargenerate', [GenerateController::class, 'deleteAll']);

Route::delete('deleteAll', [GenerateController::class, 'deleteAllData']);

// route untuk cek koneksi
Route::get('cek-ping-with-image', [CheckConnectionController::class, 'checkPingWithImage']);
