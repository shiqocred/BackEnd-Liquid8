<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ColorTagController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\GenerateController;
use App\Http\Controllers\NewProductController;
use App\Http\Controllers\ProductOldController;
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


Route::resource('product_olds', ProductOldController::class); 
Route::delete('delete-all-products-old', [ProductOldController::class, 'deleteAll']); 
Route::resource('new_products', NewProductController::class);
Route::delete('/delete-all-new-products', [NewProductController::class, 'deleteAll']);

Route::resource('categories', CategoryController::class);

Route::resource('/documents', DocumentController::class);
Route::delete('/delete-all-documents', [DocumentController::class, 'deleteAll']);


Route::get('product_olds-search', [ProductOldController::class, 'searchByDocument']);

Route::get('search_barcode_product', [ProductOldController::class, 'searchByBarcode']);

Route::resource('color_tags', ColorTagController::class);
Route::resource('categories', CategoryController::class);

Route::resource('historys', RiwayatCheckController::class);
Route::get('riwayat-document', [RiwayatCheckController::class, 'getByDocument']);
