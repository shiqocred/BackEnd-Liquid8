<?php

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
use App\Http\Controllers\PaletProductController;
use App\Http\Controllers\ProductApproveController;
use App\Http\Controllers\ProductBundleController;
use App\Http\Controllers\ProductFilterController;
use App\Http\Controllers\ProductOldController;
use App\Http\Controllers\ProductQcdController;
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
use App\Models\New_product;
use App\Models\PaletProduct;
use App\Models\ProductApprove;
use App\Models\Repair;
use App\Models\RiwayatCheck;
use App\Models\SpecialTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//patokan urutan role : Admin,Spv,Team leader,Admin Kasir,Crew,Reparasi,

// Route ini berfungsi jika route nya tidak di temukan. maka, akan ke muncul pesan 404
Route::fallback(function () {
   return response()->json(['status' => false, 'message' => 'Not Found!'], 404);
});

Route::middleware(['auth:sanctum', 'check.role:Admin,Spv,Admin Kasir,Reparasi','Team leader'])->group(function () {
   // =========================================== repair station ==================================================

   Route::get('repair', [NewProductController::class, 'showRepair']);
   Route::put('repair/update/{id}', [NewProductController::class, 'updateRepair']);
   Route::post('repair/multiple-update', [NewProductController::class, 'MultipleUpdateRepair']);
   Route::post('repair/all-update', [NewProductController::class, 'updateAllDamagedOrAbnormal']);
   Route::get('/excelolds', [NewProductController::class, 'excelolds']);

   //list dump
   Route::get('/dumps', [NewProductController::class, 'listDump']);
   Route::put('/update-dumps/{id}', [NewProductController::class, 'updateDump']);
   Route::put('/update-repair-dump/{id}', [RepairProductController::class, 'updateRepair']);
   Route::put('/update-priceDump/{id}', [NewProductController::class, 'updatePriceDump']);
   Route::get('/export-dumps-excel/{id}', [NewProductController::class, 'exportDumpToExcel']);

   //qcd
   Route::get('qcd/filter_product', [FilterQcdController::class, 'index']);
   Route::post('qcd/filter_product/{id}/add', [FilterQcdController::class, 'store']);
   Route::delete('qcd/destroy/{id}', [FilterQcdController::class, 'destroy']);
   Route::get('bundle/qcd', [BundleQcdController::class, 'index']);
   Route::get('bundle/qcd/{bundleQcd}', [BundleQcdController::class, 'show']);
   Route::post('bundle/qcd', [ProductQcdController::class, 'store']);
   Route::delete('bundle/qcd/{bundleQcd}', [BundleQcdController::class, 'destroy']);
   Route::delete('bundle/qcd/{bundleQcd}/destroy', [BundleQcdController::class, 'destroyBundle']);

   // =========================================== repair moving product ==================================================

   //filters product bundle
   Route::get('repair-mv/filter_product', [RepairFilterController::class, 'index']);
   Route::post('repair-mv/filter_product/{id}/add', [RepairFilterController::class, 'store']);
   Route::delete('repair-mv/filter_product/destroy/{id}', [RepairFilterController::class, 'destroy']);

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

   Route::get('new_products/{new_product}', [NewProductController::class, 'show']);
   Route::get('new_products', [NewProductController::class, 'index']);

   Route::get('getProductRepair', [RepairController::class, 'getProductRepair']);
});

Route::middleware(['auth:sanctum', 'check.role:Admin,Admin Kasir'])->group(function () {
   //=========================================== outbound ==========================================================

   //migrate
   Route::resource('migrates', MigrateController::class);
   Route::get('displayMigrate', [MigrateController::class, 'displayMigrate']);
   Route::post('migrate-finish', [MigrateDocumentController::class, 'MigrateDocumentFinish']);
   Route::resource('migrate-documents', MigrateDocumentController::class);

   //sale
   Route::resource('sales', SaleController::class);
   Route::put('/sales/{sale}', [SaleController::class, 'updatePriceSale']);
   Route::put('/update_price_sales/{sale}', [SaleController::class, 'livePriceUpdates']);
   Route::resource('sale-documents', SaleDocumentController::class);
   Route::post('sale-finish', [SaleDocumentController::class, 'saleFinish']);
   Route::get('sale-report', [SaleDocumentController::class, 'combinedReport']);
   Route::get('sale-products', [SaleController::class, 'products']);

   Route::apiResource('buyers', BuyerController::class);

   Route::get('new_products/{new_product}', [NewProductController::class, 'show']);
   Route::get('new_products', [NewProductController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'check.role:Admin,Spv,Team leader'])->group(function () {

   //=========================================== inbound ==========================================================
   //generates file excel -> input data ekspedisi 
   Route::post('/generate', [GenerateController::class, 'processExcelFiles']);
   Route::post('/generate/merge-headers', [GenerateController::class, 'mapAndMergeHeaders']);

   Route::post('/excelOld', [NewProductController::class, 'processExcelFiles']);
   Route::post('/excelOld/merge', [NewProductController::class, 'mapAndMergeHeaders']);


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
   Route::get('product-bundle/{new_product}/{bundle}/add', [ProductBundleController::class, 'addProductBundle']);
   Route::delete('product-bundle/{productBundle}', [ProductBundleController::class, 'destroy']);

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
   Route::get('palet/{palet}', [PaletController::class, 'show']);
   Route::post('palet', [PaletProductController::class, 'store']);
   Route::delete('palet/{palet}', [PaletController::class, 'destroy']);
   Route::put('palet/{palet}', [PaletController::class, 'update']);

   Route::get('product-palet/{new_product}/{palet}/add', [PaletProductController::class, 'addProductPalet']);
   Route::delete('product-palet/{paletProduct}', [PaletProductController::class, 'destroy']);

   //categories discount
   Route::resource('categories', CategoryController::class);

   //colortags diskon
   Route::resource('color_tags', ColorTagController::class);

   //product
   Route::post('new_products', [NewProductController::class, 'store']);

   Route::put('new_products/{new_product}', [NewProductController::class, 'update']);
   Route::get('new_products/{new_product}', [NewProductController::class, 'show']);
   Route::delete('new_products/{new_product}', [NewProductController::class, 'destroy']);

   //migrate
   Route::resource('migrates', MigrateController::class);
   Route::put('migrate-add/{new_product}', [MigrateController::class, 'addMigrate']);
   Route::post('migrate-finish', [MigrateDocumentController::class, 'MigrateDocumentFinish']);
   Route::resource('migrate-documents', MigrateDocumentController::class);
});

Route::middleware(['auth:sanctum', 'check.role:Admin,Spv,Team leader,Crew'])->group(function () {

   // =========================================== Dashboard ==================================================
   Route::get('dashboard', [DashboardController::class, 'index']);

   // =========================================== Category ==================================================
   Route::get('list-category', [CategoryController::class, 'index']);

   //=========================================== inbound ==========================================================

   //product old
   Route::resource('product_olds', ProductOldController::class);
   Route::delete('delete-all-products-old', [ProductOldController::class, 'deleteAll']);
   Route::get('product_olds-search', [ProductOldController::class, 'searchByDocument']);
   Route::get('search_barcode_product', [ProductOldController::class, 'searchByBarcode']);

   //product approve
   Route::resource('product-approves', ProductApproveController::class);
   Route::get('productApprovesByDoc', [ProductApproveController::class, 'searchByDocument']);
   Route::delete('delete_all_by_codeDocument', [ProductApproveController::class, 'delete_all_by_codeDocument']);


   //new product (hasil scan)
   // Route::resource('new_products', NewProductController::class);
   Route::get('new_products', [NewProductController::class, 'index']);
   Route::get('get-latestPrice', [NewProductController::class, 'getLatestPrice']); //baru
   Route::post('new_products', [NewProductController::class, 'store']);

   Route::get('countColor', [NewProductController::class, 'totalPerColor']); //baru

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


   Route::delete('/delete-all-new-products', [NewProductController::class, 'deleteAll']);
   Route::get('new_product/cronjob/expired', [NewProductController::class, 'expireProducts']);
   Route::get('new_product/expired', [NewProductController::class, 'listProductExp']);
   Route::get('new_product/display-expired', [NewProductController::class, 'listProductExpDisplay']);
   Route::post('new_product/excelImport', [NewProductController::class, 'excelImport']);
   Route::get('/new_product/document', [NewProductController::class, 'byDocument']);

   //document
   Route::resource('/documents', DocumentController::class);
   Route::delete('/delete-all-documents', [DocumentController::class, 'deleteAll']);
   Route::get('/documentInProgress', [DocumentController::class, 'documentInProgress']);

   //categories discount
   Route::get('categories', [CategoryController::class, 'index']);

   //colortags diskon
   Route::get('color_tags', [ColorTagController::class, 'index']);
   Route::get('product_byColor', [NewProductController::class, 'getTagColor']);
   Route::get('product_byCategory', [NewProductController::class, 'getByCategory']);

   //riwayat
   Route::resource('historys', RiwayatCheckController::class);
   Route::get('riwayat-document/code_document', [RiwayatCheckController::class, 'getByDocument']);
   Route::post('history/exportToExcel', [RiwayatCheckController::class, 'exportToExcel']);
   Route::get('/testEmail', [RiwayatCheckController::class, 'sendEmail']);

   Route::resource('notifications', NotificationController::class);
});

Route::middleware(['auth:sanctum', 'check.role:Admin,Spv,Team leader,Admin Kasir'])->group(function () {
   // Route::get('/spv/approve/{notificationId}', [NotificationController::class, 'approveTransaction'])->name('admin.approve');
   Route::get('new_products', [NewProductController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'check.role:Admin,Spv'])->group(function () {
   Route::post('add_product', [NewProductController::class, 'addProductByAdmin']);
   Route::post('/check-price', [NewProductController::class, 'checkPrice']);
   Route::resource('destinations', DestinationController::class);
   Route::get('/spv/approve/{notificationId}', [NotificationController::class, 'approveTransaction'])->name('admin.approve');

   //export data by menu
   Route::post('export_product_byCategory', [NewProductController::class, 'export_product_byCategory']);
   Route::post('exportCategory', [CategoryController::class, 'exportCategory']);
   Route::post('exportBundlesDetail/{id}', [BundleController::class, 'exportBundlesDetail']);
   Route::post('exportProductExpired', [NewProductController::class, 'export_product_expired']);
   Route::post('exportPalletsDetail/{id}', [PaletController::class, 'exportPalletsDetail']);
   Route::post('exportRepairDetail/{id}', [RepairController::class, 'exportRepairDetail']);
   Route::post('exportMigrateDetail/{id}', [MigrateDocumentController::class, 'exportMigrateDetail']);
   Route::post('exportBuyers', [BuyerController::class, 'exportBuyers']);
   Route::post('exportUsers', [UserController::class, 'exportUsers']);
});

Route::middleware(['auth:sanctum', 'check.role:Admin,Spv,Crew,Reparasi'])->group(function () {
   Route::get('notificationByRole', [NotificationController::class, 'getNotificationByRole']);
   Route::get('documents-approve', [ProductApproveController::class, 'documentsApprove']);
   Route::get('product-approveByDoc/{code_document}', [ProductApproveController::class, 'productsApproveByDoc'])
      ->where('code_document', '.*');
});

Route::middleware(['auth:sanctum', 'check.role:Admin'])->group(function () {
   Route::post('register', [AuthController::class, 'register']);
   Route::resource('users', UserController::class)->except(['store']);
   Route::resource('roles', RoleController::class);
});



//login
Route::post('login', [AuthController::class, 'login']);

//export urgent
Route::post('exportBundles', [BundleController::class, 'exportBundles']);
Route::post('exportNp', [NewProductController::class, 'exportNewProducts']);

Route::delete('cleargenerate', [GenerateController::class, 'deleteAll']);

Route::delete('deleteAll', [GenerateController::class, 'deleteAllData']);

// route untuk cek koneksi
Route::get('cek-ping-with-image', [CheckConnectionController::class, 'checkPingWithImage']);


Route::post('generateExcel_injectDisplay', [GenerateController::class, 'uploadExcel']);
Route::post('filter-cleanExcel-injectDisplay', [GenerateController::class, 'filterAndCleanExcelOld']);
Route::post('injectDisplay', [GenerateController::class, 'insertCleanedData']);
Route::post('createDummyData/{count}', [GenerateController::class, 'createDummyData']);

//download template
Route::post('downloadTemplate', [GenerateController::class, 'exportTemplaye']);


// Route::post('/excelOld2', [NewProductController::class, 'processExcelFiles2']);
// Route::post('/excelOld/merge2', [NewProductController::class, 'mapAndMergeHeaders2']);



