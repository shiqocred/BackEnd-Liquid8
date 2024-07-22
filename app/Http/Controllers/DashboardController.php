<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Models\Document;
use App\Models\New_product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $thisYear = date('Y');

        $countInboundOutbound = New_Product::select(
            DB::raw('MONTH(created_at) as month'),
            DB::raw('SUM(CASE WHEN new_status_product IN ("sale", "migrate") THEN 1 ELSE 0 END) AS outbound_count'),
            DB::raw('COUNT(*) AS inbound_count')
        )
            ->whereYear('created_at', $thisYear)
            ->groupBy(DB::raw('MONTH(created_at)'))
            ->get();

        //Sale Category
        $totalNewProductSaleByCategory = New_product::select('new_category_product', DB::raw('COUNT(*) as total'))
            ->where('new_status_product', 'sale')
            ->groupBy('new_category_product')
            ->get();
        $totalNewProductSaleByCategory[] = ['all_total' => $totalNewProductSaleByCategory->sum('total')];

        //Inbound Data
        $document = Document::select('base_document', 'created_at', 'total_column_in_document')->latest()->paginate(8);

        //Expired Product
        $totalNewProductExpiredByCategory = New_product::select('new_category_product', DB::raw('COUNT(*) as total'))
            ->where('new_status_product', 'expired')
            ->groupBy('new_category_product')
            ->get();
        $totalNewProductExpiredByCategory[] = ['all_total' => $totalNewProductExpiredByCategory->sum('total')];

        //Product by Category
        $totalNewProductByCategory = New_product::select('new_category_product', DB::raw('COUNT(*) as total'))
            ->whereNotNull('new_category_product')
            ->whereIn('new_status_product', ['display', 'promo', 'bundle'])
            ->groupBy('new_category_product')
            ->get();
        $totalNewProductByCategory[] = ['all_total' => $totalNewProductByCategory->sum('total')];

        $resource = new ResponseResource(
            true,
            "Data dashboard analytic",
            [
                "chart_inbound_outbound" => $countInboundOutbound,
                "product_sales" => $totalNewProductSaleByCategory,
                "inbound_data" => $document,
                "expired_data" => $totalNewProductExpiredByCategory,
                "product_data" => $totalNewProductByCategory
            ]
        );
        return $resource->response();
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
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
