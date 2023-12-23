<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Models\Document;
use App\Models\RiwayatCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RiwayatCheckController extends Controller
{
   
    public function index()
    {
        $riwayats = RiwayatCheck::latest()->paginate(50);
        return new ResponseResource(true, "list riwayat", $riwayats);
    }


    public function create()
    {
        //
    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code_document' => 'required|unique:riwayat_checks,code_document',
            'total_data' => 'required|integer',
            'total_data_in' => 'required|integer',
            'total_data_lolos' => 'required|integer',
            'total_data_damaged' => 'required|integer', 
            'total_data_abnormal' => 'required|integer',
            'total_discrepancy' => 'required|integer'
        ]);
    
        if($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
    
        $document = Document::where('code_document', $request['code_document'])->first();
        
        if (!$document) {
            return response()->json(['error' => 'Document not found'], 404);
        }
    
        if ($document->total_column_in_document == 0) {
            return response()->json(['error' => 'Total column in document cannot be zero'], 422);
        }
        
    
        $riwayat_check = RiwayatCheck::create([
            'code_document' => $request['code_document'],
            'total_data' => $request['total_data'],
            'total_data_in' => $request['total_data_in'],
            'total_data_lolos' => $request['total_data_lolos'],
            'total_data_damaged' => $request['total_data_damaged'],
            'total_data_abnormal' => $request['total_data_abnormal'],
            'total_discrepancy' => $request['total_discrepancy'],

            // persentase
            'precentage_total_data' => ($request['total_data'] / $document->total_column_in_document) * 100,
            'percentage_in' => ($request['total_data_in'] / $document->total_column_in_document) * 100,
            'percentage_lolos' => ($request['total_data_lolos'] / $document->total_column_in_document) * 100,
            'percentage_damaged' => ($request['total_data_damaged'] / $document->total_column_in_document) * 100,
            'percentage_abnormal' => ($request['total_data_abnormal'] / $document->total_column_in_document) * 100,
            'percentage_discrepancy' => ($request['total_discrepancy'] / $document->total_column_in_document) * 100,
        ]);
    
        return new ResponseResource(true, "Data berhasil ditambah", $riwayat_check);
    }
    

    public function show(RiwayatCheck $riwayatCheck)
    {
        return new ResponseResource(true, "Riwayat Check", $riwayatCheck);
    }

    public function getByDocument(Request $request)
    {
        $codeDocument = RiwayatCheck::where('code_document', $request['code_document']);
        return new ResponseResource(true, "Riwayat Check", $codeDocument);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(RiwayatCheck $riwayatCheck)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, RiwayatCheck $riwayatCheck)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RiwayatCheck $riwayatCheck)
    {
    
    }
}
