<?php

namespace App\Http\Controllers;

use App\Models\RepairFilter;
use Illuminate\Http\Request;
use App\Models\New_product;
use App\Models\RepairProduct;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\ResponseResource;

class RepairFilterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $userId = auth()->id();
        $product_filtersByuser = RepairFilter::where('user_id', $userId)->get();
        $totalNewPrice = $product_filtersByuser->sum('new_price_product');

        $totalNewPriceWithCategory = $product_filtersByuser->whereNotNull('new_category_product')->sum('new_price_product');
        $totalOldPriceWithoutCategory = $product_filtersByuser->whereNull('new_category_product')->sum('old_price_product');
        $totalNewPriceWithoutCtgrTagColor = $product_filtersByuser
            ->whereNull('new_category_product')->whereNull('new_tag_product')->whereNull('old_price_product')->sum('new_price_product');
        $totalOldPriceWithoutCtgrTagColor = $product_filtersByuser->whereNull('new_category_product')
            ->whereNull('new_tag_product')->whereNull('new_price_product')->sum('old_price_product');


        $totalNewPrice = $totalNewPriceWithCategory + $totalOldPriceWithoutCategory + $totalNewPriceWithoutCtgrTagColor + $totalOldPriceWithoutCtgrTagColor;
        $product_filters = RepairFilter::where('user_id', $userId)->paginate(100);
        return new ResponseResource(true, "list product filter", [
            'total_new_price' => $totalNewPrice,
            'data' => $product_filters,
        ]);
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
    public function store($id)
    {
        DB::beginTransaction();
        $userId = auth()->id();
        try {
            $product = New_product::findOrFail($id);
            $product->user_id = $userId;
            $productFilter = RepairFilter::create($product->toArray());
            $product->delete();
            DB::commit();
            return new ResponseResource(true, "berhasil menambah list product reapir", $productFilter);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(RepairFilter $repairFilter)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(RepairFilter $repairFilter)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, RepairFilter $repairFilter)
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
            $product_filter = RepairFilter::findOrFail($id);
            New_product::create($product_filter->toArray());
            $product_filter->delete();
            DB::commit();
            return new ResponseResource(true, "berhasil menghapus list product repair", $product_filter);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
