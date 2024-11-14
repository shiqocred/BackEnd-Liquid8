<?php

namespace App\Http\Controllers;

use App\Models\New_product;
use App\Models\Product_old;
use App\Models\RepairFilter;
use Illuminate\Http\Request;
use App\Models\FilterStaging;
use App\Models\RepairProduct;
use App\Models\Product_Bundle;
use App\Models\ProductApprove;
use App\Models\StagingApprove;
use App\Models\StagingProduct;
use App\Models\BarcodeAbnormal;
use App\Http\Resources\ResponseResource;

class BarcodeAbnormalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
    public function show(BarcodeAbnormal $barcodeAbnormal)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(BarcodeAbnormal $barcodeAbnormal)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BarcodeAbnormal $barcodeAbnormal)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BarcodeAbnormal $barcodeAbnormal)
    {
        //
    }
    public function dataSelection(Request $request)
    {
        set_time_limit(600);
        ini_set('memory_limit', '1024M');
        
        $oldBarcode = barcodeAbnormal::where('code_document', $request['code_document'])
            ->pluck('old_barcode_product');
        
        $tables = [
            'New_product' => New_product::class,
            'ProductApprove' => ProductApprove::class,
            'StagingProduct' => StagingProduct::class,
            'StagingApprove' => StagingApprove::class,
            'FilterStaging' => FilterStaging::class,
            'Product_Bundle' => Product_Bundle::class,
            'RepairFilter' => RepairFilter::class,
            'RepairProduct' => RepairProduct::class,
        ];
    
        $barcodeLocations = [];
    
        foreach ($tables as $tableName => $table) {
            // Periksa jika ada barcode yang cocok di tabel ini
            $matchingBarcodes = $table::whereIn('old_barcode_product', $oldBarcode)->pluck('old_barcode_product');
            
            // Jika ada barcode yang ditemukan, tambahkan tabel dan barcode ke dalam array
            if ($matchingBarcodes->isNotEmpty()) {
                $barcodeLocations[$tableName] = $matchingBarcodes;
            }
        }
    
        // Jika ada barcode yang ditemukan di tabel manapun
        if (!empty($barcodeLocations)) {
            return new ResponseResource(false, "The old barcode already exists in the following tables", $barcodeLocations);
        }
    
        // Jika tidak ada barcode ditemukan di tabel apapun
        return new ResponseResource(true, "The old barcode does not exist in any tables", []);
    }
    
}
