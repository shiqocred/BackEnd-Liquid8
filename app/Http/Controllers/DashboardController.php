<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Models\Document;
use App\Models\New_product;
use App\Models\SaleDocument;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        $currentYear = now()->year;
        $factor = 100; // Faktor pengali untuk mengubah decimal menjadi bilangan bulat

        // Ambil semua transaksi selesai di tahun ini
        $allTransactions = SaleDocument::with('sales')
            ->where('status_document_sale', 'selesai')
            ->whereHas('sales', function ($query) {
                $query->where('status_sale', 'selesai');
            })
            ->whereYear('created_at', $currentYear)
            ->get();

        // Hitung total nilai transaksi sepanjang tahun dalam bentuk bilangan bulat
        $totalTransactionValueYear = $allTransactions->sum(function ($transaction) use ($factor) {
            return intval($transaction->total_price_document_sale * $factor);
        });

        // Kelompokkan transaksi berdasarkan bulan
        $summaryTransactionCustomer = $allTransactions->groupBy(function ($date) {
            return Carbon::parse($date->created_at)->format('F'); // Mengelompokkan berdasarkan nama bulan
        });

        $resultSummaryTransactionCustomer = [];

        foreach ($summaryTransactionCustomer as $month => $data) {
            $total_transactions = $data->count();
            $total_customers = $data->unique('buyer_id_document_sale')->count();
            $total_transaction_value = $data->sum(function ($transaction) use ($factor) {
                return intval($transaction->total_price_document_sale * $factor);
            });

            // Hitung persentase transaksi bulan ini terhadap total nilai transaksi sepanjang tahun
            $transaction_percentage = $totalTransactionValueYear > 0 ? ($total_transaction_value / $totalTransactionValueYear) * 100 : 0;

            // Konversi kembali nilai total transaksi ke bentuk decimal
            $total_transaction_value_decimal = $total_transaction_value / $factor;

            // Rata-rata transaksi menggunakan nilai desimal
            $average_transaction_value = $data->avg('total_price_document_sale');

            $resultSummaryTransactionCustomer[] = [
                'month' => $month,
                'total_transactions' => $total_transactions,
                'total_customers' => $total_customers,
                'total_transaction_value' => $total_transaction_value_decimal, // Konversi kembali ke decimal
                'transaction_percentage' => round($transaction_percentage, 2), // Dibulatkan ke dua desimal
                'average_transaction_value' => $average_transaction_value,
            ];
        }

        $resource = new ResponseResource(
            true,
            "Data dashboard analytic",
            [
                "summary_transaction_customer" => $resultSummaryTransactionCustomer,
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
