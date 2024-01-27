<?php

namespace App\Http\Controllers;

use App\Models\Palet;
use App\Models\PaletFilter;
use App\Models\PaletProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\ResponseResource;

class PaletProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $product_palets = PaletProduct::latest()->paginate(100);
        return new ResponseResource(true, "list product palet", $product_palets);
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
            $product_filters = PaletFilter::all();

            if ($product_filters->isEmpty()) {
                return new ResponseResource(false, "Tidak ada produk filter yang tersedia saat ini", $product_filters);
            }

            $palet = Palet::create([
                'name_palet' => $request->name_palet,
                'category_palet' => $request->category_palet,
                'total_price_palet' => $request->total_price_palet,
                'total_product_palet' => $request->total_product_palet,
                'palet_barcode' => $request->palet_barcode,
            ]);
    
    
            $insertData = $product_filters->map(function ($product) use ($palet) {
                return [
                    'palet_id' => $palet->id,
                    'code_document' => $product->code_document,
                    'old_barcode_product' => $product->old_barcode_product,
                    'new_barcode_product' => $product->new_barcode_product,
                    'new_name_product' => $product->new_name_product,
                    'new_quantity_product' => $product->new_quantity_product,
                    'new_price_product' => $product->new_price_product,
                    'new_date_in_product' => $product->new_date_in_product,
                    'new_status_product' => $product->new_status_product,
                    'new_quality' => $product->new_quality,
                    'new_category_product' => $product->new_category_product,
                    'new_tag_product' => $product->new_tag_product
                ];
            })->toArray();
    
            PaletProduct::insert($insertData);
    
            PaletFilter::query()->delete();
             
            DB::commit();
            return new ResponseResource(true, "Palet berhasil dibuat", $palet);
        } catch(\Exception $e) {
            DB::rollback();
            Log::error("Gagal membuat palet: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal memindahkan product ke palet', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource. 
     */
    public function show(PaletProduct $paletProduct)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PaletProduct $paletProduct)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PaletProduct $paletProduct)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaletProduct $paletProduct)
    {
        //
    }
}
