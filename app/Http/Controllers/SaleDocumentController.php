<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
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
        $saleDocument = SaleDocument::where('status_document_sale', 'selesai')->latest()->paginate(10);
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
        $resource = new ResponseResource(true, "data document sale", $saleDocument->load('sales'));
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
                $newProduct->update(['new_status_product' => 'sale']);
                $val->update(['status_sale' => 'selesai']);
            }

            $saleDocument->update(
                [
                    'total_product_document_sale' => count($sale),
                    'total_price_document_sale' => BigInteger::of($sale->sum('product_price_sale')),
                    'status_document_sale' => 'selesai'
                ]
            );

            $resource = new ResponseResource(true, "data berhasil di simpan!", $saleDocument);
        } catch (\Exception $e) {
            $resource = new ResponseResource(false, "data gagal di simpan!", $e->getMessage());
        }

        return $resource->response();
    }
}