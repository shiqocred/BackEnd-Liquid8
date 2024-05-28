<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Buyer;
use App\Models\Bundle;
use App\Models\New_product;
use App\Models\SaleDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\ResponseResource;
use Illuminate\Support\Facades\Validator;

class SaleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $userId = auth()->id();
        $sale = Sale::where('status_sale', 'proses')->where('user_id', auth()->id())->latest()->paginate(10);
        $saleDocument = SaleDocument::where('status_document_sale', 'proses')->where('user_id', auth()->id())->first();
        if ($saleDocument == null) {
            $codeDocumentSale = codeDocumentSale($userId);
            $saleBuyerName = '';
            $saleBuyerId = '';
        } else {
            $codeDocumentSale = $saleDocument->code_document_sale;
            $saleBuyerName = $saleDocument->buyer_name_document_sale;
            $saleBuyerId = $saleDocument->buyer_id_document_sale;
        }
        $totalSale = $sale->sum('product_price_sale');
        $data = [
            'code_document_sale' => $codeDocumentSale,
            'sale_buyer_name' => $saleBuyerName,
            'sale_buyer_id' => $saleBuyerId,
            'total_sale' => $totalSale,
        ];
        $data += $sale->toArray();
        $resource = new ResponseResource(true, "list data sale", $data);
        return $resource->response();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        $userId = auth()->id();

        $validator = Validator::make(
            $request->all(),
            [
                'sale_barcode' => 'required',
                'buyer_id' => 'required|numeric'
            ]
        );

        if ($validator->fails()) {
            return (new ResponseResource(false, "Input tidak valid!", $validator->errors()))->response()->setStatusCode(422);
        }

        try {
            $productSale = Sale::where('product_barcode_sale', $request->input('sale_barcode'))->where('status_sale', 'proses')->first();
            if ($productSale) {
                $saleDocumentCheck = SaleDocument::where('code_document_sale', $productSale->code_document_sale)->first();
                if ($saleDocumentCheck && $saleDocumentCheck->buyer_id_document_sale == $request->input('buyer_id')) {
                    return new ResponseResource(false, "Data sudah dimasukkan!", $productSale);
                }
            }

            // Cari data buyer
            $buyer = Buyer::find($request->buyer_id);
            if (!$buyer) {
                return (new ResponseResource(false, "Data Buyer tidak ditemukan!", []))->response()->setStatusCode(404);
            }

            // Cari product atau bundle berdasarkan barcode
            $newProduct = New_product::where('new_barcode_product', $request->sale_barcode)->first();
            $bundle = Bundle::where('barcode_bundle', $request->sale_barcode)->first();

            if ($newProduct) {
                $data = [
                    $newProduct->new_name_product,
                    $newProduct->new_category_product,
                    $newProduct->new_barcode_product,
                    $newProduct->new_price_product
                ];
            } elseif ($bundle) {
                $data = [
                    $bundle->name_bundle,
                    $bundle->category,
                    $bundle->barcode_bundle,
                    $bundle->total_price_custom_bundle
                ];
            } else {
                return (new ResponseResource(false, "Barcode tidak ditemukan!", []))->response()->setStatusCode(404);
            }

            $saleDocument = SaleDocument::where('status_document_sale', 'proses')->where('user_id', $userId)->first();

            if (!$saleDocument) {
                $saleDocumentRequest = [
                    'code_document_sale' => codeDocumentSale($userId),
                    'buyer_id_document_sale' => $buyer->id,
                    'buyer_name_document_sale' => $buyer->name_buyer,
                    'buyer_phone_document_sale' => $buyer->phone_buyer,
                    'buyer_address_document_sale' => $buyer->address_buyer,
                    'total_price_document_sale' => 0,
                    'total_product_document_sale' => 0,
                    'status_document_sale' => 'proses',
                ];

                $createSaleDocument = (new SaleDocumentController)->store(new Request($saleDocumentRequest));
                if ($createSaleDocument->getStatusCode() != 201) {
                    return $createSaleDocument;
                }
                $saleDocument = $createSaleDocument->getData()->data->resource;
            }

            $sale = Sale::create(
                [
                    'user_id' => auth()->id(),
                    'code_document_sale' => $saleDocument->code_document_sale,
                    'product_name_sale' => $data[0],
                    'product_category_sale' => $data[1],
                    'product_barcode_sale' => $data[2],
                    'product_price_sale' => $data[3],
                    'product_qty_sale' => 1,
                    'status_sale' => 'proses'
                ]
            );

            DB::commit();
            return new ResponseResource(true, "data berhasil di tambahkan!", $sale);
        } catch (\Exception $e) {
            DB::rollBack();
            return (new ResponseResource(false, "Data gagal ditambahkan!", $e->getMessage()))->response()->setStatusCode(500);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(Sale $sale)
    {
        $resource = new ResponseResource(true, "data sale", $sale);
        return $resource->response();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Sale $sale)
    {
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Sale $sale)
    {
        try {
            $checkSale = Sale::where('status_sale', 'proses')->where('user_id', auth()->id())->first();
            if ($checkSale == null) {
                return response()->json(['status' => false, 'message' => 'sale not found'], 404);
            }
            $allSale = Sale::where('code_document_sale', $sale->code_document_sale)
                ->where('user_id', auth()->id())
                ->where('status_sale', 'proses')
                ->get();
            if ($allSale->count() <= 1) {
                $saleDocument = SaleDocument::where('code_document_sale', $sale->code_document_sale)->where('user_id', auth()->id())->first();
                $saleDocument->delete();
            }
            $sale->delete();
            $resource = new ResponseResource(true, "data berhasil di hapus", $sale);
        } catch (\Exception $e) {
            $resource = new ResponseResource(false, "data gagal di hapus", $e->getMessage());
        }
        return $resource->response();
    }

    public function products()
    {
        if (request()->has('q')) {
            $searchQuery = request()->q;
            $products = New_product::whereJsonContains('new_quality', ['damaged' => null])
                ->whereJsonContains('new_quality', ['abnormal' => null])
                ->select('new_barcode_product as barcode', 'new_name_product as name', 'new_category_product as category', 'created_at as created_date')
                ->where('new_barcode_product', 'like', '%' . $searchQuery . '%')
                ->orWhere('new_name_product', 'like', '%' . $searchQuery . '%')
                ->orWhere('new_category_product', 'like', '%' . $searchQuery . '%')
                ->union(Bundle::select('barcode_bundle as barcode', 'name_bundle as name', 'category', 'created_at as created_date')
                    ->where('barcode_bundle', 'like', '%' . $searchQuery . '%')
                    ->orWhere('name_bundle', 'like', '%' . $searchQuery . '%')
                    ->orWhere('category', 'like', '%' . $searchQuery . '%'))
                ->orderBy('created_date', 'desc')
                ->paginate(10);
        } else {
            $products = New_product::whereJsonContains('new_quality', ['damaged' => null])
                ->whereJsonContains('new_quality', ['abnormal' => null])
                ->select('new_barcode_product as barcode', 'new_name_product as name', 'new_category_product as category', 'created_at as created_date')
                ->union(Bundle::select('barcode_bundle as barcode', 'name_bundle as name', 'category', 'created_at as created_date'))
                ->orderBy('created_date', 'desc')
                ->paginate(10);
        }
        $resource = new ResponseResource(true, "list data product", $products);
        return $resource->response();
    }

    public function updatePriceSale(Request $request, Sale $sale)
    {



        $validator = Validator::make($request->all(), [
            'product_price_sale' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            $resource = new ResponseResource(false, "Input tidak valid!", $validator->errors());
            return $resource->response()->setStatusCode(422);
        }

        try {
            DB::beginTransaction();
            // $product = New_product::where('new_barcode_product', $sale->product_barcode_sale)->first();
            // $product->new_price_product = $request->input('product_price_sale');
            // $product->save();
            $persentage_diskon = $request->input('product_price_sale');
            $current_price = $sale->product_price_sale;
            $diskon = $current_price - ($current_price * ($persentage_diskon / 100));
            $sale->product_price_sale = $diskon;
            $sale->save();

            DB::commit();
            return new ResponseResource(true, "data berhasil di update", $sale);
        } catch (\Exception $e) {
            DB::rollBack();

            return (new ResponseResource(false, "Data gagal ditambahkan", $e->getMessage()))
                ->setStatusCode(500);
        }
    }
}
