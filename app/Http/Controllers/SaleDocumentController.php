<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use App\Models\Sale;
use App\Models\Bundle;
use App\Models\Category;
use Brick\Math\BigInteger;
use App\Models\New_product;
use App\Models\SaleDocument;
use Illuminate\Http\Request;
use App\Http\Resources\ResponseResource;
use Illuminate\Support\Facades\Validator;

class SaleDocumentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = $request->input('q');
        $saleDocuments = SaleDocument::with('user:id,name')->where('status_document_sale', 'selesai')->latest();
        if ($query) {
            $saleDocuments = $saleDocuments->where(function ($data) use ($query) {
                $data->where('code_document_sale', 'LIKE', '%' . $query . '%')
                    ->orWhere('buyer_name_document_sale', 'LIKE', '%' . $query . '%');
            });
        }
        $saleDocuments = $saleDocuments->paginate(10);
        $resource = new ResponseResource(true, "list document sale", $saleDocuments);
        return $resource->response();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'code_document_sale' => 'required|unique:sale_documents',
                'buyer_name_document_sale'  => 'required',
                'total_product_document_sale' => 'required',
                'total_price_document_sale' => 'required',
            ]
        );

        if ($validator->fails()) {
            $resource = new ResponseResource(false, "Input tidak valid!", $validator->errors());
            return $resource->response()->setStatusCode(422);
        }
        try {
            $request['user_id'] = auth()->id();
            $saleDocument = SaleDocument::create($request->all());
            $resource = new ResponseResource(true, "Data berhasil ditambahkan!", $saleDocument);
        } catch (\Exception $e) {
            $resource = new ResponseResource(false, "Data gagal ditambahkan!", $e->getMessage());
        }
        return $resource->response();
    }

    /**
     * Display the specified resource.
     */
    public function show(SaleDocument $saleDocument)
    {
        $resource = new ResponseResource(true, "data document sale", $saleDocument->load('sales', 'user'));
        return $resource->response();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SaleDocument $saleDocument)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SaleDocument $saleDocument)
    {
        try {
            $saleDocument->delete();
            $resource = new ResponseResource(true, "data berhasil di hapus!", $saleDocument);
        } catch (\Exception $e) {
            $resource = new ResponseResource(false, "data gagal di hapus!", $e->getMessage());
        }
        return $resource->response();
    }

    public function saleFinish(Request $request)
    {
        try {
            $userId = $request->user()->id;
            $saleDocument = SaleDocument::where('status_document_sale', 'proses')
                ->where('user_id', $userId)
                ->first();
            if ($saleDocument == null) {
                throw new Exception("Data sale belum dibuat!");
            }
            $sales = Sale::where('code_document_sale', $saleDocument->code_document_sale)->get();

            foreach ($sales as $sale) {
                $newProduct = New_product::where('new_barcode_product', $sale->product_barcode_sale)->first();
                $bundle = Bundle::where('barcode_bundle', $sale->product_barcode_sale)->first();
                if (!$newProduct && !$bundle) {
                    return response()->json(['error' => 'Both new product and bundle not found'], 404);
                } elseif (!$newProduct) {
                    $bundle->update(['product_status' => 'sale']);
                } elseif (!$bundle) {
                    $newProduct->update(['new_status_product' => 'sale']);
                } else {
                    $newProduct->update(['new_status_product' => 'sale']);
                    $bundle->update(['product_status' => 'sale']);
                }
                $sale->update(['status_sale' => 'selesai']);
            }

            $saleDocument->update([
                'total_product_document_sale' => count($sales),
                'total_price_document_sale' => $sales->sum('product_price_sale'),
                'status_document_sale' => 'selesai'
            ]);

            $resource = new ResponseResource(true, "Data berhasil disimpan!", $saleDocument);
        } catch (\Exception $e) {
            $resource = new ResponseResource(false, "Data gagal disimpan!", $e->getMessage());
        }

        return $resource->response();
    }

    public function combinedReport(Request $request)
    {
        $user = auth()->user();
        $name_user = $user->name;
        $codeDocument = $request->input('code_document_sale');
        $saleDocument = SaleDocument::where('code_document_sale', $codeDocument)->first();

        if (!$saleDocument) {
            return response()->json([
                'data' => null,
                'message' => 'Dokumen penjualan tidak ditemukan',
            ], 404);
        }

        $timezone = 'Asia/Jakarta';
        $currentTransactionTime = Carbon::parse($saleDocument->created_at)->timezone($timezone);

        $totalTransactionsBeforeCurrent = SaleDocument::whereDate('created_at', $currentTransactionTime->toDateString())
            ->where('created_at', '<', $currentTransactionTime)
            ->count();

        $pembeliKeBerapa = $totalTransactionsBeforeCurrent + 1;

        $categoryReport = $this->generateCategoryReport($saleDocument);
        // $barcodeReport = $this->generateBarcodeReport($saleDocument);

        return response()->json([
            'data' => [
                'name_user' => $name_user,
                'transactions_today' => $pembeliKeBerapa,
                'category_report' => $categoryReport,
                // 'NameBarcode_report' => $barcodeReport,
            ],
            'message' => 'Laporan penjualan',
            'buyer' => $saleDocument
        ]);
    }

    private function generateCategoryReport($saleDocument)
    {
        $totalPrice = 0;
        $categoryReport = [];
        $products = collect();
        $categories = collect();

        foreach ($saleDocument->sales as $sale) {
            $product = New_product::where('new_name_product', $sale->product_name_sale)
                ->where('new_status_product', 'sale')->where('new_barcode_product', $sale->product_barcode_sale)
                ->first();
            $category = Category::where('name_category', $product->new_category_product)->first();

            if ($product) {
                $product->new_quantity_product = $sale->product_qty_sale;
                $products->push($product);
            }
            if ($category) {
                $categories->push($category);
            }
        }

        if ($saleDocument->sales->count() > 0) {
            $categoryReport = $saleDocument->sales->groupBy(function ($sale) {
                $product = New_product::where('new_name_product', $sale->product_name_sale)
                    ->where('new_status_product', 'sale')
                    ->where('new_barcode_product', $sale->product_barcode_sale)
                    ->first();
                return $product ? $product->new_category_product : 'Unknown';
            })->map(function ($group) use (&$totalPrice, $categories) {
                $totalPricePerCategory = $group->sum(function ($sale) {
                    return $sale->product_qty_sale * $sale->product_price_sale;
                });
                $totalPrice += $totalPricePerCategory;
                $categoryName = $group->first()->product_name_sale;
                $category = $categories->firstWhere('name_category', $categoryName);

                return [
                    'category' => $categoryName,
                    'total_quantity' => $group->sum('product_qty_sale'),
                    'total_price' => $totalPricePerCategory,
                    'total_discount' => $category ? $category->discount_category : null,
                ];
            })->values()->all();
        }

        return ["category_list" => $categoryReport, 'total_harga' => $totalPrice];
    }


    private function generateBarcodeReport($saleDocument)
    {
        $report = [];
        $totalPrice = 0;

        foreach ($saleDocument->sales as $index => $sale) {
            $productName = $sale->product_name_sale;
            $productBarcode = $sale->product_barcode_sale;
            $productPrice = $sale->product_price_sale;
            $productQty = $sale->product_qty_sale;

            $subtotalPrice = $productPrice * $productQty;

            $report[] = [
                $index + 1,
                $productName,
                $productBarcode,
                $subtotalPrice,
            ];

            $totalPrice += $subtotalPrice;
        }

        $report[] = ['Total Harga', $totalPrice];

        return $report;
    }
}
