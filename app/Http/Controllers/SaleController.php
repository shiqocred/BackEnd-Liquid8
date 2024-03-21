<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Models\Bundle;
use App\Models\Buyer;
use App\Models\New_product;
use App\Models\Sale;
use App\Models\SaleDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SaleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $sale = Sale::where('status_sale', 'proses')->latest()->paginate(10);
        $saleDocument = SaleDocument::where('status_document_sale', 'proses')->first();
        if ($saleDocument == null) {
            $codeDocumentSale = codeDocumentSale();
            $saleBuyerName = '';
        } else {
            $codeDocumentSale = $saleDocument->code_document_sale;
            $saleBuyerName = $saleDocument->buyer_name_document_sale;
        }
        $totalSale = $sale->sum('product_price_sale');
        $data = [
            'code_document_sale' => $codeDocumentSale,
            'sale_buyer_name' => $saleBuyerName,
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
        $validator = Validator::make(
            $request->all(),
            [
                'sale_barcode' => 'required',
                'buyer_id' => 'required|numeric'
            ]
        );

        if ($validator->fails()) {
            $resource = new ResponseResource(false, "Input tidak valid!", $validator->errors());
            return $resource->response()->setStatusCode(422);
        }

        try {
            $buyer = Buyer::find($request->buyer_id);
            if ($buyer == null) {
                $resource = new ResponseResource(false, "Data Buyer tidak di temukan!", []);
                return $resource->response()->setStatusCode(404);
            }

            $newProduct = New_product::where('new_barcode_product', $request->sale_barcode)->first();
            $bundle = Bundle::where('barcode_bundle', $request->sale_barcode)->first();

            if ($newProduct != null) {
                $data = [
                    $newProduct->new_name_product,
                    $newProduct->new_barcode_product,
                    $newProduct->new_price_product
                ];
            } else if ($bundle != null) {
                $data = [
                    $bundle->name_bundle,
                    $bundle->barcode_bundle,
                    $bundle->total_price_custom_bundle
                ];
            } else {
                $resource = new ResponseResource(false, "Barcode tidak di temukan!", []);
                return $resource->response()->setStatusCode(404);
            }

            $saleDocument = SaleDocument::where('status_document_sale', 'proses')->first();

            if ($saleDocument == null) {
                $saleDocumentRequest['code_document_sale'] = codeDocumentSale();
                $saleDocumentRequest['buyer_name_document_sale'] = $buyer->name_buyer;
                $saleDocumentRequest['buyer_phone_document_sale'] = $buyer->phone_buyer;
                $saleDocumentRequest['buyer_address_document_sale'] = $buyer->address_buyer;
                $saleDocumentRequest['total_price_document_sale'] = 0;
                $saleDocumentRequest['total_product_document_sale'] = 0;
                $saleDocumentRequest['status_document_sale'] = 'proses';

                $createSaleDocument = (new SaleDocumentController)->store(new Request($saleDocumentRequest));
                if ($createSaleDocument->getStatusCode() != 201) {
                    return $createSaleDocument;
                }
                $saleDocument = $createSaleDocument->getData()->data->resource;
            }

            $sale = Sale::create(
                [
                    'code_document_sale' => $saleDocument->code_document_sale,
                    'product_name_sale' => $data[0],
                    'product_barcode_sale' => $data[1],
                    'product_price_sale' => $data[2],
                    'product_qty_sale' => 1,
                    'status_sale' => 'proses'
                ]
            );

            $resource = new ResponseResource(true, "data berhasil di tambahkan!", $sale);
        } catch (\Exception $e) {
            $resource = new ResponseResource(false, "data gagal di tambahkan!", $e->getMessage());
        }

        return $resource->response();
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
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Sale $sale)
    {
        try {
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
            $products = New_product::select('new_barcode_product as barcode', 'new_name_product as name', 'new_category_product as category', 'created_at as created_date')
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
            $products = New_product::select('new_barcode_product as barcode', 'new_name_product as name', 'new_category_product as category', 'created_at as created_date')
                ->union(Bundle::select('barcode_bundle as barcode', 'name_bundle as name', 'category', 'created_at as created_date'))
                ->orderBy('created_date', 'desc')
                ->paginate(10);
        }
        $resource = new ResponseResource(true, "list data product", $products);
        return $resource->response();
    }
}
