<?php

use App\Http\Controllers\BundleController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ColorTagController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\GenerateController;
use App\Http\Controllers\NewProductController;
use App\Http\Controllers\PaletController;
use App\Http\Controllers\PaletFilterController;
use App\Http\Controllers\PaletProductController;
use App\Http\Controllers\ProductBundleController;
use App\Http\Controllers\ProductFilterController;
use App\Http\Controllers\ProductOldController;
use App\Http\Controllers\PromoController;
use App\Http\Controllers\RiwayatCheckController;
use App\Models\RiwayatCheck;
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


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

//generates file excel
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


//categories discount
Route::resource('categories', CategoryController::class);

//document
Route::resource('/documents', DocumentController::class);
Route::delete('/delete-all-documents', [DocumentController::class, 'deleteAll']);

//colortags diskon
Route::resource('color_tags', ColorTagController::class);

//riwayat
Route::resource('historys', RiwayatCheckController::class);
Route::get('riwayat-document', [RiwayatCheckController::class, 'getByDocument']);

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

