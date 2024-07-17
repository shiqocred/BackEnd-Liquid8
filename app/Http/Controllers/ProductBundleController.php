<?php

namespace App\Http\Controllers;

use App\Models\Bundle;
use Illuminate\Http\Request;
use App\Models\Product_Bundle;
use App\Models\Product_Filter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\ResponseResource;
use App\Models\New_product;
use Carbon\Carbon;

class ProductBundleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $product_bundles = Product_Bundle::latest()->paginate(100);
        return new ResponseResource(true, "list product bundle", $product_bundles);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        $userId = auth()->id();
        try {
            $product_filters = Product_Filter::where('user_id', $userId)->get();
            if ($product_filters->isEmpty()) {
                return new ResponseResource(false, "Tidak ada produk filter yang tersedia saat ini", $product_filters);
            }

            $bundle = Bundle::create([
                'name_bundle' => $request->name_bundle,
                'total_price_bundle' => $request->total_price_bundle,
                'total_price_custom_bundle' => $request->total_price_custom_bundle,
                'total_product_bundle' => $request->total_product_bundle,
                'barcode_bundle' => $request->barcode_bundle,
                'category' => $request->category,
                'name_color' => $request->name_color,
            ]);

            $insertData = $product_filters->map(function ($product) use ($bundle) {
                return [
                    'bundle_id' => $bundle->id,
                    'code_document' => $product->code_document,
                    'old_barcode_product' => $product->old_barcode_product,
                    'new_barcode_product' => $product->new_barcode_product,
                    'new_name_product' => $product->new_name_product,
                    'new_quantity_product' => $product->new_quantity_product,
                    'new_price_product' => $product->new_price_product,
                    'old_price_product' => $product->old_price_product,
                    'new_date_in_product' => $product->new_date_in_product,
                    'new_status_product' => 'bundle',
                    'new_quality' => $product->new_quality,
                    'new_category_product' => $product->new_category_product,
                    'new_tag_product' => $product->new_tag_product,
                    'new_discount' => $product->new_discount,
                    'display_price' => $product->display_price,
                    'created_at' => now(),  
                    'updated_at' => now(),
                ];
            })->toArray();

            Product_Bundle::insert($insertData);

            Product_Filter::where('user_id', $userId)->delete();

            DB::commit();
            return new ResponseResource(true, "Bundle berhasil dibuat", $bundle);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Gagal membuat bundle: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal memindahkan product ke bundle', 'error' => $e->getMessage()], 500);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(Product_Bundle $product_Bundle)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product_Bundle $product_Bundle)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product_Bundle $product_Bundle)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product_Bundle $productBundle)
    {
        DB::beginTransaction();
        try {
            New_product::create([
                'code_document' => $productBundle->code_document,
                'old_barcode_product' => $productBundle->old_barcode_product,
                'new_barcode_product' => $productBundle->new_barcode_product,
                'new_name_product' => $productBundle->new_name_product,
                'new_quantity_product' => $productBundle->new_quantity_product,
                'new_price_product' => $productBundle->new_price_product,
                'new_date_in_product' => $productBundle->new_date_in_product,
                'new_status_product' => 'display',
                'new_quality' => $productBundle->new_quality,
                'new_category_product' => $productBundle->new_category_product,
                'new_tag_product' => $productBundle->new_tag_product,
                'new_discount' => $productBundle->new_discount,
                'display_price' => $productBundle->display_price
            ]);

            $bundle = Bundle::findOrFail($productBundle->bundle_id);
            $bundle->update([
                'total_price_custom_bundle' => $bundle->total_price_custom_bundle - $productBundle->new_price_product,
                'total_product_bundle' => $bundle->total_product_bundle - 1,
            ]);

            $productBundle->delete();

            DB::commit();
            return new ResponseResource(true, "produk bundle  berhasil dihapus", $productBundle);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Gagal menghapus bundle: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menghapus bundle', 'error' => $e->getMessage()], 500);
        }
    }

    public function addProductBundle(New_product $new_product, Bundle $bundle)
    {

        DB::beginTransaction();
        try {

            $productBundle = Product_Bundle::create([
                'bundle_id' => $bundle->id,
                'code_document' => $new_product->code_document,
                'old_barcode_product' => $new_product->old_barcode_product,
                'new_barcode_product' => $new_product->new_barcode_product,
                'new_name_product' => $new_product->new_name_product,
                'new_quantity_product' => $new_product->new_quantity_product,
                'new_price_product' => $new_product->new_price_product,
                'old_price_product' => $new_product->old_price_product,
                'new_date_in_product' => $new_product->new_date_in_product,
                'new_status_product' => 'bundle',
                'new_quality' => $new_product->new_quality,
                'new_category_product' => $new_product->new_category_product,
                'new_tag_product' => $new_product->new_tag_product,
                'new_discount' => $new_product->new_discount,
                'display_price' => $new_product->display_price
            ]);

            $bundle->update([
                'total_price_custom_bundle' => $bundle->total_price_custom_bundle + $productBundle->new_price_product,
                'total_product_bundle' => $bundle->total_product_bundle + 1,
            ]);

            $new_product->delete();

            DB::commit();
            return new ResponseResource(true, "Product bundle berhasil di tambahkan", $productBundle);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Gagal membuat bundle: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal memindahkan product ke bundle', 'error' => $e->getMessage()], 500);
        }
    }
}
