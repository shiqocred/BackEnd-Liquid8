<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Models\Bundle;
use App\Models\Category;
use App\Models\New_product;
use App\Models\Sale;
use App\Models\SaleDocument;
use Brick\Math\BigInteger;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SaleDocumentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $saleDocument = SaleDocument::with('user:id,name')->where('status_document_sale', 'selesai')->latest()->paginate(10);
        $resource = new ResponseResource(true, "list document sale", $saleDocument);
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

    public function saleFinish()
    {
        try {
            $saleDocument = SaleDocument::where('status_document_sale', 'proses')->first();
            if ($saleDocument == null) {
                throw new Exception("data sale belum dibuat!");
            }
            $sale = Sale::where('code_document_sale', $saleDocument->code_document_sale)->get();

            foreach ($sale as $val) {
                $newProduct = New_product::where('new_barcode_product', $val->product_barcode_sale)->first();
                $bundle = Bundle::where('barcode_bundle', $val->product_barcode_sale)->first();
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
                $val->update(['status_sale' => 'selesai']);
            }

            $saleDocument->update(
                [
                    'total_product_document_sale' => count($sale),
                    'total_price_document_sale' => $sale->sum('product_price_sale'),
                    'status_document_sale' => 'selesai'
                ]
            );

            $resource = new ResponseResource(true, "data berhasil di simpan!", $saleDocument);
        } catch (\Exception $e) {
            $resource = new ResponseResource(false, "data gagal di simpan!", $e->getMessage());
        }

        return $resource->response();
    }

    public function combinedReport(Request $request)
    {
        $codeDocument = $request->input('code_document_sale');
        $saleDocument = SaleDocument::where('code_document_sale', $codeDocument)->first();


        if (!$saleDocument) {
            return response()->json([
                'data' => null,
                'message' => 'Dokumen penjualan tidak ditemukan',
            ], 404);
        }

        $categoryReport = $this->generateCategoryReport($saleDocument);
        // $barcodeReport = $this->generateBarcodeReport($saleDocument);

        return response()->json([
            'data' => [
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
            $category = Category::where('name_category', $sale->product_category_sale)->first();

            if ($product) {
                $product->new_quantity_product = $sale->product_qty_sale;
                $products->push($product);
            }
            if ($category) {
                $categories->push($category);
            }
        }

        if ($products->count() > 0) {
            $categoryReport = $products->groupBy('new_category_product')
                ->map(function ($group) use (&$totalPrice, $categories) {
                    $totalPricePerCategory = $group->sum(function ($item) {
                        return $item->new_quantity_product * $item->new_price_product;
                    });
                    $totalPrice += $totalPricePerCategory;
                    $category = $categories->firstWhere('name_category', $group->first()->new_category_product);

                    return [
                        'category' => $group->first()->new_category_product,
                        'total_quantity' => $group->sum('new_quantity_product'),
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
