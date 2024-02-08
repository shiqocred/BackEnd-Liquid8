<?php

namespace App\Http\Controllers;

use App\Models\Palet;
use App\Models\New_product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\ResponseResource;

class PaletController extends Controller
{ 
    public function display()
    {
        $new_products = New_product::query()
            ->where(function ($queryBuilder) {
                $queryBuilder->where('new_status_product', 'display')
                    ->whereRaw('json_extract(new_quality, "$.lolos") is not null')
                    ->whereRaw('json_extract(new_quality, "$.lolos") = "lolos"');
            })
            ->paginate(50);
    
        return new ResponseResource(true, "Data produk dengan status display.", $new_products);
    }
    
    
    public function index(Request $request)
    {
        $query = $request->input('q');
        $palets = Palet::latest()
        ->with('paletProducts')
        ->where(function ($queryBuilder) use ($query){
            $queryBuilder->where('name_palet', 'LIKE', '%' . $query . '%')
            ->orWhere('category_palet', 'LIKE', '%' . $query . '%')
            ->orWhereHas('paletProducts', function($subQueryBuilder) use ($query) {
                $subQueryBuilder->where('new_name_product', 'LIKE', '%' . $query . '%')
                        ->orWhere('new_barcode_product', 'LIKE', '%' . $query . '%');
            });
        })->paginate(100);
        return new ResponseResource(true, "list palet", $palets);
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
    }

    /**
     * Display the specified resource.
     */
    public function show(Palet $palet)
    {
        return new ResponseResource(true, "detail palet", $palet);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Palet $palet)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Palet $palet)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Palet $palet)
    {
        DB::beginTransaction();
        try {
            $productPalet = $palet->paletProducts;

            foreach ($productPalet as $product) {
                New_product::create([
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
                ]);

                $product->delete();
            }

            $palet->delete();

            DB::commit();
            return new ResponseResource(true, "palet berhasil dihapus", null);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Gagal menghapus palet: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menghapus palet', 'error' => $e->getMessage()], 500);
        }
    }
}
