<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Imports\BulkySaleImport;
use App\Models\BulkyDocument;
use App\Models\BulkySale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class BulkySaleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_import' => 'required|file|mimes:xlsx,csv',
            'discount_bulky' => 'nullable|numeric|min:1|max:100',
            'after_price_bulky' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            $resource = new ResponseResource(false, "Input tidak valid!", $validator->errors());
            return $resource->response()->setStatusCode(422);
        }

        $bulkyDocument =  BulkyDocument::create([
            'total_product_bulky' => 0,
            'total_old_price_bulky' => 0,
            'discount_bulky' => $request->discount_bulky,
            'after_price_bulky' => $request->after_price_bulky,
        ]);

        $import = new BulkySaleImport($bulkyDocument->id);

        Excel::import($import, $request->file('file_import'));

        $bulkyDocument->load('bulkySales');

        if ($bulkyDocument->bulkySales->isEmpty()) {
            $bulkyDocument->delete();

            $resource = new ResponseResource(false, "Tidak ada data yang valid karena semua barcode tidak ditemukan.", [
                "total_barcode_found" => $import->getTotalFoundBarcode(),
                "total_barcode_not_found" => $import->getTotalNotFoundBarcode(),
                "data_barcode_not_found" => $import->getDataNotFoundBarcode(),
            ]);
            return $resource->response()->setStatusCode(404);
        }

        $bulkyDocument->update([
            'total_product_bulky' => $bulkyDocument->bulkySales->count(),
            'total_old_price_bulky' => $bulkyDocument->bulkySales->sum('old_price_bulky_sale'),
        ]);

        $resource = new ResponseResource(true, "Data berhasil ditambahkan!", [
            "total_barcode_found" => $import->getTotalFoundBarcode(),
            "total_barcode_not_found" => $import->getTotalNotFoundBarcode(),
            "data_barcode_not_found" => $import->getDataNotFoundBarcode(),
            "data_barcode_duplicate" => $import->getDataDuplicateBarcode(),
            "bulky_documents" => $bulkyDocument->makeHidden(['bulkySales']),
        ]);

        return $resource;
    }

    /**
     * Display the specified resource.
     */
    public function show(BulkySale $bulkySale)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(BulkySale $bulkySale)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BulkySale $bulkySale)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BulkySale $bulkySale)
    {
        //
    }
}
