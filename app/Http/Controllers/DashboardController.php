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

        $dailyTransactionSummary = SaleDocument::with('sales.newProduct')
            ->where('status_document_sale', 'selesai')
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->get()
            ->groupBy(function ($date) {
                return Carbon::parse($date->created_at)->format('d');
            })
            ->map(function ($saleDocuments, $day) {
                $saleValue = $saleDocuments->sum('total_price_document_sale');

                $totalOldPrice = $saleDocuments->sum(function ($saleDocument) {
                    return $saleDocument->sales->sum(function ($sale) {
                        return $sale->newProduct->old_price_product ?? 0;
                    });
                });
                $initialCapital = $totalOldPrice * 0.2;

                return [
                    'display_price' => $totalOldPrice,
                    'initial_capital' => $initialCapital,
                    'sale_value' => $saleValue,
                    'day' => $day,
                ];
            });

        $typeBuyer = Buyer::selectRaw('type_buyer, COUNT(*) as total_buyer')
            ->groupBy('type_buyer')
            ->get()
            ->pluck('total_buyer', 'type_buyer')
            ->toArray();

        $monthlyTransactionSummary = SaleDocument::with('sales.newProduct')
            ->where('status_document_sale', 'selesai')
            ->whereYear('created_at', $year)
            ->get()
            ->groupBy(function ($date) {
                return Carbon::parse($date->created_at)->format('F');
            })
            ->map(function ($saleDocuments, $month) {
                $saleValue = $saleDocuments->sum('total_price_document_sale');

                $totalOldPrice = $saleDocuments->sum(function ($saleDocument) {
                    return $saleDocument->sales->sum(function ($sale) {
                        return $sale->newProduct->old_price_product ?? 0;
                    });
                });
                $initialCapital = $totalOldPrice * 0.2;

                return [
                    'display_price' => $totalOldPrice,
                    'initial_capital' => $initialCapital,
                    'sale_value' => $saleValue,
                    'month' => $month,
                ];
            });

        $resource = new ResponseResource(
            true,
            "Data dashboard analytic",
            // [
            //     "monthly_transaction_customer" => ,
            //     "monthly_transaction_summary" => ,
            //     "daily_transaction_summary" => ,
            //     "type_buyer" => ,
            // ]
            $dailyTransactionSummary
        );
        return $resource->response();
    }

    public function summaryTransaction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'y' => 'nullable|date_format:Y|digits:4', // Format tahun (misalnya, 2024)
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
            ->first();

        // Casting nilai agar menjadi integer atau float
        $summaryTransactionTotal->total_transaction = (float) $summaryTransactionTotal->total_transaction;
        $summaryTransactionTotal->total_customer = (float) $summaryTransactionTotal->total_customer;
        $summaryTransactionTotal->value_transaction = (float) $summaryTransactionTotal->value_transaction;

        // Buat array kosong untuk menyimpan summary sales dari Januari sampai Desember
        $summaryTransaction = [];

        // Loop untuk menghasilkan summary sales untuk setiap bulan
        for ($month = 1; $month <= 12; $month++) {
            $saleDocument = SaleDocument::selectRaw('
                COUNT(*) as total_transaction,
                COUNT(DISTINCT buyer_id_document_sale) as total_customer,
                SUM(total_price_document_sale) as value_transaction
                ')
                ->where('status_document_sale', 'selesai')
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->first(); // Menggunakan first() untuk mengambil satu hasil

            // Jika tidak ada data untuk bulan ini, isi dengan nilai default
            if (!$saleDocument) {
                $saleDocument = (object) [
                    'total_transaction' => 0,
                    'total_customer' => 0,
                    'value_transaction' => 0,
                ];
            } else {
                // Casting nilai agar menjadi integer atau float
                $saleDocument->total_transaction = (float) $saleDocument->total_transaction;
                $saleDocument->total_customer = (float) $saleDocument->total_customer;
                $saleDocument->value_transaction = (float) $saleDocument->value_transaction;
            }

            // Tambahkan hasil ke dalam array summarySales
            $summaryTransaction[] = [
                'month' => Carbon::createFromDate($year, $month, 1)->format('F'), // Format nama bulan
                'total_transaction' => $saleDocument->total_transaction,
                'total_customer' => $saleDocument->total_customer,
                'value_transaction' => $saleDocument->value_transaction,
            ];
        }

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
            'm' => 'nullable|date_format:m|digits:2', // Format bulan (01-12)
            'y' => 'nullable|date_format:Y|digits:4', // Format tahun (misalnya, 2024)
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
            ->first();

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
            ->get()
            ->map(function ($item) {
                return [
                    'product_category_sale' => $item->product_category_sale,
                    'qty_sale' => (int) $item->qty_sale,
                    'display_price_sale' => (float) $item->display_price_sale,
                    'after_discount_sale' => (float) $item->after_discount_sale,
                ];
            });

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

    public function storageReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        //tanggal sekarang
        $currentDate = Carbon::now();
        $currentMonth = $currentDate->format('M');
        $currentYear = $currentDate->format('Y');

        $fromInput = $request->input('from');
        $toInput = $request->input('to');

        $fromDate = $fromInput
            ? Carbon::parse($fromInput)->startOfDay()
            : Carbon::now()->startOfMonth()->startOfDay();
        $toDate = $toInput
            ? Carbon::parse($toInput)->endOfDay()
            : Carbon::now()->endOfMonth()->endOfDay();

        $categoryCount = New_product::selectRaw('
                new_category_product as category_product,
                COUNT(new_category_product) as total_category
            ')
            ->whereNotNull('new_category_product')
            ->where('new_tag_product', NULL)
            ->whereRaw("JSON_EXTRACT(new_quality, '$.\"lolos\"') = 'lolos'")
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->groupBy('new_category_product')
            ->get();

        $resource = new ResponseResource(
            true,
            "Laporan Data Perkategori",
            [
                'month' => [
                    'current_month' => [
                        'month' => $currentMonth,
                        'year' => $currentYear,
                    ],
                    'date_from' => [
                        'date' => $fromDate->format('d') ?? null,
                        'month' => $fromDate->format('F') ?? null,
                        'year' => $fromDate->format('Y') ?? null,
                    ],
                    'date_to' => [
                        'date' => $toDate->format('d') ?? null,
                        'month' => $toDate->format('F') ?? null,
                        'year' => $toDate->format('Y') ?? null,
                    ],
                ],
                'chart' => $categoryCount
            ]
        );

        return $resource->response();
    }

    public function generalSale(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $fromInput = $request->input('from');
        $toInput = $request->input('to');

        $fromDate = $fromInput
            ? Carbon::parse($fromInput)->startOfDay()
            : Carbon::now()->startOfMonth()->startOfDay();
        $toDate = $toInput
            ? Carbon::parse($toInput)->endOfDay()
            : Carbon::now()->endOfMonth()->endOfDay();

        //tanggal sekarang
        $currentDate = Carbon::now();
        $currentMonth = $currentDate->format('M');
        $currentYear = $currentDate->format('Y');

        $generalSale = SaleDocument::selectRaw('
                SUM(total_price_document_sale) as total_price_sale,
                SUM(total_old_price_document_sale) as total_display_price,
                code_document_sale,
                buyer_name_document_sale,
                DATE(created_at) as tgl
            ')
            ->where('status_document_sale', 'selesai')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->groupBy('tgl', 'code_document_sale')
            ->get()
            ->groupBy('tgl')
            ->map(function ($salesOnDate) {
                $total_price_sale = $salesOnDate->sum('total_price_sale');
                $total_display_price = $salesOnDate->sum('total_display_price');

                return [
                    "date" => $salesOnDate->first()->tgl,
                    "total_price_sale" => $total_price_sale,
                    "total_display_price" => $total_display_price,
                ];
            })->values();

        $listDocumentSale = SaleDocument::selectRaw('
                total_price_document_sale as total_purchase,
                total_old_price_document_sale as total_display_price,
                code_document_sale,
                buyer_name_document_sale
            ')
            ->where('status_document_sale', 'selesai')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->get();

        $resource = new ResponseResource(
            true,
            "Laporan Data General",
            [
                'month' => [
                    'current_month' => [
                        'month' => $currentMonth,
                        'year' => $currentYear,
                    ],
                    'date_from' => [
                        'date' => $fromDate->format('d') ?? null,
                        'month' => $fromDate->format('F') ?? null,
                        'year' => $fromDate->format('Y') ?? null,
                    ],
                    'date_to' => [
                        'date' => $toDate->format('d') ?? null,
                        'month' => $toDate->format('F') ?? null,
                        'year' => $toDate->format('Y') ?? null,
                    ],
                ],
                'chart' => $generalSale,
                'list_document_sale' => $listDocumentSale
            ]
        );

        return $resource->response();
    }

    public function analitycSales(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $fromInput = $request->input('from');
        $toInput = $request->input('to');

        $fromDate = $fromInput
            ? Carbon::parse($fromInput)->startOfDay()
            : Carbon::now()->startOfMonth()->startOfDay();
        $toDate = $toInput
            ? Carbon::parse($toInput)->endOfDay()
            : Carbon::now()->endOfMonth()->endOfDay();

        //tanggal sekarang
        $currentDate = Carbon::now();
        $currentMonth = $currentDate->format('M');
        $currentYear = $currentDate->format('Y');

        $analitycSales = Sale::selectRaw('
                    DATE(created_at) as tgl,
                    product_category_sale,
                    COUNT(product_category_sale) as total_category,
                    SUM(display_price) as display_price_sale,
                    SUM(product_price_sale) as purchase
                ')
            ->where('status_sale', 'selesai')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->groupBy('tgl', 'product_category_sale')
            ->orderBy('tgl')
            ->get()
            ->groupBy('tgl')
            ->map(function ($salesOnDate) {
                return [
                    'date' => $salesOnDate->first()->tgl,
                    'categories' => $salesOnDate->mapWithKeys(function ($item) {
                        return [
                            $item->product_category_sale => [
                                'total_category' => $item->total_category,
                                'display_price_sale' => (float) $item->display_price_sale,
                                'purchase' => (float) $item->purchase,
                            ],
                        ];
                    })
                ];
            })->values();

        $resource = new ResponseResource(
            true,
            "Laporan Data Sale",
            [
                'month' => [
                    'current_month' => [
                        'month' => $currentMonth,
                        'year' => $currentYear,
                    ],
                    'date_from' => [
                        'date' => $fromDate->format('d') ?? null,
                        'month' => $fromDate->format('F') ?? null,
                        'year' => $fromDate->format('Y') ?? null,
                    ],
                    'date_to' => [
                        'date' => $toDate->format('d') ?? null,
                        'month' => $toDate->format('F') ?? null,
                        'year' => $toDate->format('Y') ?? null,
                    ],
                ],
                'chart' => $analitycSales
            ]
        );

        return $resource->response();
    }
}
