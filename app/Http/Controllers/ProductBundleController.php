<?php

namespace App\Http\Controllers;

use App\Models\Bundle;
use Illuminate\Http\Request;
use App\Models\Product_Bundle;
use App\Models\Product_Filter; 
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\ResponseResource;

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
        try {
            $product_filters = Product_Filter::all();
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
                    'new_tag_product' => $product->new_tag_product
                ];
            })->toArray();

            Product_Bundle::insert($insertData);

            Product_Filter::query()->delete();

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
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            Product_Bundle::where('bundle_id', $id)->delete();

            // $bundle = Bundle::findOrFail($id);
            // $bundle->delete();

            DB::commit();
            return new ResponseResource(true, "produk bundle  berhasil dihapus", null);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Gagal menghapus bundle: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menghapus bundle', 'error' => $e->getMessage()], 500);
        }
    }
}
