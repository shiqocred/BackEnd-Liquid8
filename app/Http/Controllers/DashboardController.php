<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Models\Buyer;
use App\Models\Document;
use App\Models\New_product;
use App\Models\Sale;
use App\Models\SaleDocument;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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

    public function index2(Request $request)
    {
        $year = $request->year ?? date('Y');
        $month = $request->month ?? date('m');

        $summeryMontlyCustomerTransaction = SaleDocument::with('buyer:id,name_buyer')
            ->selectRaw('buyer_id_document_sale, SUM(total_price_document_sale) as total_sales, DATE_FORMAT(created_at, "%M") as month')
            ->whereYear('created_at', $year)
            ->groupBy(DB::raw('DATE_FORMAT(created_at, "%M")'), 'buyer_id_document_sale')
            ->get();

        $categoriesSaleMontly = Sale::selectRaw('COUNT(product_category_sale) as total_category_sale, SUM(product_price_sale) as total_sales, DATE_FORMAT(created_at, "%M")  as month')
            ->where('status_sale', 'selesai')
            ->whereYear('created_at', $year)
            ->groupBy(DB::raw('DATE_FORMAT(created_at, "%M") '), 'product_category_sale')
            ->get();

        $dailyTransactionSummary = SaleDocument::selectRaw('SUM(total_price_document_sale) as display_price, (SUM(total_price_document_sale) * 0.2) as initial_capital, DATE_FORMAT(created_at, "%d") as day')
            ->where('status_document_sale', 'selesai')
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->groupBy(DB::raw('DATE_FORMAT(created_at, "%d")'))
            ->get();

        // $monthlyTransactionSummary = SaleDocument::selectRaw('SUM(total_price_document_sale) as display_price, (SUM(total_price_document_sale) * 0.2) as initial_capital, DATE_FORMAT(created_at, "%M") as day')
        //     ->where('status_document_sale', 'selesai')
        //     ->whereYear('created_at', $year)
        //     ->groupBy(DB::raw('DATE_FORMAT(created_at, "%M")'))
        //     ->get();

        $typeBuyer = Buyer::selectRaw('type_buyer, COUNT(*) as total_buyer')
            ->groupBy('type_buyer')
            ->get()
            ->pluck('total_buyer', 'type_buyer')
            ->toArray();

        $monthlyTransactionSummary = SaleDocument::selectRaw('
            SUM(sale_documents.total_price_document_sale) as display_price,
            SUM(sale_documents.total_price_document_sale) * 0.2 as initial_capital,
            DATE_FORMAT(sale_documents.created_at, "%M") as month
                ')
            ->with(['sales.newProduct' => function ($query) {
                $query->selectRaw('SUM(new_products.old_price_product) as total_old_price');
            }])
            ->where('sale_documents.status_document_sale', 'selesai')
            ->whereYear('sale_documents.created_at', $year)
            ->groupBy(DB::raw('DATE_FORMAT(sale_documents.created_at, "%M")'))
            ->get();

        // Format hasil untuk menambahkan total_old_price
        // $monthlyTransactionSummary = $monthlyTransactionSummary->map(function ($saleDocument) {
        //     $saleDocument->total_old_price = $saleDocument->sales->sum(function ($sale) {
        //         return $sale->product->total_old_price ?? 0;
        //     });
        //     return $saleDocument;
        // });


        // dd($monthlyTransactionSummary);

        $resource = new ResponseResource(
            true,
            "Data dashboard analytic",
            // [
            //     "monthly_transaction_customer" => ,
            //     "monthly_categories_sale" => ,
            //     "monthly_transaction_summary" => ,
            //     "daily_transaction_summary" => ,
            //     "type_buyer" => ,
            // ]
            $categoriesSaleMontly
        );
        return $resource->response();
    }

    public function summaryTransaction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'm' => 'nullable|date_format:m', // Format bulan (01-12)
            'y' => 'nullable|date_format:Y', // Format tahun (misalnya, 2024)
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Invalid input format. Year should be in format YYYY.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $year = $request->input('y', Carbon::now()->format('Y'));

        //tanggal sekarang
        $currentDate = Carbon::now();
        $currentYear = $currentDate->format('Y');

        //bulan yang di pilih
        $selectedDate = Carbon::createFromFormat('Y', $year);
        $selectedYear = $selectedDate->format('Y');

        //bulan seblumnya
        $prevMonthDate = $selectedDate->copy()->subYear();
        $prevYear = $prevMonthDate->format('Y');

        //bulan yang akan datang
        $nextMonthDate = $selectedDate->copy()->addYear();
        $nextYear = $nextMonthDate->format('Y');

        $summaryTransactionTotal = SaleDocument::selectRaw('
                COUNT(*) as total_transaction,
                COUNT(DISTINCT buyer_id_document_sale) as total_customer,
                SUM(total_price_document_sale) as value_transaction
            ')
            ->where('status_document_sale', 'selesai')
            ->whereYear('created_at', $year)
            ->get();

        $summaryTransaction = SaleDocument::selectRaw('
                DATE_FORMAT(created_at, "%M") as month,
                COUNT(*) as total_transaction,
                COUNT(DISTINCT buyer_id_document_sale) as total_customer,
                SUM(total_price_document_sale) as value_transaction
            ')
            ->where('status_document_sale', 'selesai')
            ->whereYear('created_at', $year)
            ->groupBy('month')
            ->get();

        $resource = new ResponseResource(
            true,
            "Data Summary Transaksi",
            [
                'year' => [
                    'current_year' => [
                        'year' => $currentYear,
                    ],
                    'prev_year' => [
                        'year' => $prevYear,
                    ],
                    'selected_year' => [
                        'year' => $selectedYear,
                    ],
                    'next_year' => [
                        'year' => $nextYear,
                    ],
                ],
                'final_total' => $summaryTransactionTotal,
                'charts' => $summaryTransaction
            ]
        );

        return $resource->response();
    }

    public function summarySales(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'm' => 'nullable|date_format:m', // Format bulan (01-12)
            'y' => 'nullable|date_format:Y', // Format tahun (misalnya, 2024)
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Invalid input format. Month should be in format MM and year should be in format YYYY.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $month = $request->input('m', Carbon::now()->format('m'));
        $year = $request->input('y', Carbon::now()->format('Y'));

        //tanggal sekarang
        $currentDate = Carbon::now();
        $currentMonth = $currentDate->format('m');
        $currentYear = $currentDate->format('Y');

        //bulan yang di pilih
        $selectedDate = Carbon::createFromFormat('Y-m', $year . '-' . $month);
        $selectedMonth = $selectedDate->format('F');
        $selectedYear = $selectedDate->format('Y');

        //bulan seblumnya
        $prevMonthDate = $selectedDate->copy()->subMonth();
        $prevMonth = $prevMonthDate->format('m');
        $prevYear = $prevMonthDate->format('Y');

        //bulan yang akan datang
        $nextMonthDate = $selectedDate->copy()->addMonth();
        $nextMonth = $nextMonthDate->format('m');
        $nextYear = $nextMonthDate->format('Y');

        $anualSales = Sale::selectRaw('
                SUM(product_qty_sale) as qty_sale,
                SUM(display_price) as display_price_sale,
                SUM(product_price_sale) as after_discount_sale
            ')
            ->where('status_sale', 'selesai')
            ->whereYear('created_at', $year)
            ->get();

        $summarySales = Sale::selectRaw('
                product_category_sale,
                SUM(product_qty_sale) as qty_sale,
                SUM(display_price) as display_price_sale,
                SUM(product_price_sale) as after_discount_sale
            ')
            ->where('status_sale', 'selesai')
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->groupBy('product_category_sale')
            ->get();

        $resource = new ResponseResource(
            true,
            "Data Summary Penjualan",
            [
                'month' => [
                    'current_month' => [
                        'month' => $currentMonth,
                        'year' => $currentYear,
                    ],
                    'prev_month' => [
                        'month' => $prevMonth,
                        'year' => $prevYear,
                    ],
                    'selected_month' => [
                        'month' => $selectedMonth,
                        'year' => $selectedYear,
                    ],
                    'next_month' => [
                        'month' => $nextMonth,
                        'year' => $nextYear,
                    ],
                ],
                'anual_sales' => $anualSales,
                'chart' => $summarySales
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
