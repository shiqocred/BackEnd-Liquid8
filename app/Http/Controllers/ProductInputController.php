<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Document;
use App\Models\New_product;
use App\Models\Product_old;
use App\Models\ProductInput;
use App\Models\RiwayatCheck;
use Illuminate\Http\Request;
use App\Models\FilterStaging;
use App\Models\ProductApprove;
use App\Models\StagingApprove;
use App\Models\StagingProduct;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\ResponseResource;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\ProductapproveResource;

class ProductInputController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $searchQuery = $request->input('q');
        $newProducts = ProductInput::latest()
            ->where(function ($queryBuilder) use ($searchQuery) {
                $queryBuilder->where('old_barcode_product', 'LIKE', '%' . $searchQuery . '%')
                    ->orWhere('new_barcode_product', 'LIKE', '%' . $searchQuery . '%')
                    ->orWhere('new_category_product', 'LIKE', '%' . $searchQuery . '%')
                    ->orWhere('new_name_product', 'LIKE', '%' . $searchQuery . '%');
            });
        $totalPrice = $newProducts->sum('new_price_product');
        $newProducts = $newProducts->paginate(50);
        return new ResponseResource(true, "list product bkl", ['tota_price' => $totalPrice, 'products' => $newProducts]);
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
        $userId = auth()->id();
        $validator = Validator::make($request->all(), [
            'code_document' => 'required',
            'old_barcode_product' => 'required',
            // 'new_barcode_product' => 'unique:new_products,new_barcode_product',
            'new_name_product' => 'required',
            'new_quantity_product' => 'required|integer',
            'new_price_product' => 'required|numeric',
            'old_price_product' => 'required|numeric',
            // 'new_date_in_product' => 'required|date',
            'new_status_product' => 'required|in:display,expired,promo,bundle,palet,dump',
            'condition' => 'required|in:lolos,damaged,abnormal',
            'new_category_product' => 'nullable|exists:categories,name_category',
            'new_tag_product' => 'nullable|exists:color_tags,name_color',

        ], [
            'new_barcode_product.unique' => 'barcode sudah ada',
            'old_barcode_product.exists' => 'barcode tidak ada'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $status = $request->input('condition');
        $description = $request->input('deskripsi', '');

        $qualityData = $this->prepareQualityData($status, $description);

        $inputData = $this->prepareInputData($request, $status, $qualityData);

        $oldBarcode = $request->input('old_barcode_product');
        $newBarcode = $request->input('new_barcode_product');

        $tables = [
            New_product::class,
            ProductApprove::class,
            StagingProduct::class,
            StagingApprove::class,
            FilterStaging::class,
        ];

        $oldBarcodeExists = false;
        $newBarcodeExists = false;

        foreach ($tables as $table) {
            if ($table::where('old_barcode_product', $oldBarcode)->exists()) {
                $oldBarcodeExists = true;
            }
            if ($table::where('new_barcode_product', $newBarcode)->exists()) {
                $newBarcodeExists = true;
            }
        }

        if ($oldBarcodeExists) {
            return new ProductapproveResource(false, false, "The old barcode already exists", $inputData);
        }

        if ($newBarcodeExists) {
            return new ResponseResource(false, "The new barcode already exists", $inputData);
        }

        DB::beginTransaction();
        try {

            $document = Document::where('code_document',  $request->input('code_document'))->first();
            $maxRetry = 5;
            for ($i = 0; $i < $maxRetry; $i++) {
                if ($document->custom_barcode) {
                    $generate = newBarcodeCustom($document->custom_barcode, $userId);
                } else {
                    $generate = generateNewBarcode($inputData['new_category_product']);
                }

                if (!ProductApprove::where('new_barcode_product', $generate)->exists()) {
                    break;
                }

                if ($i === $maxRetry - 1) {
                    throw new \Exception("Failed to generate unique barcode after multiple attempts.");
                }
            }

            $inputData['new_barcode_product'] = $generate;

            $this->deleteOldProduct($inputData['code_document'], $request->input('old_barcode_product'));
            
            $riwayatCheck = RiwayatCheck::where('code_document', $request->input('code_document'))->first();
            
            if ($qualityData['lolos'] != null) {
                ProductApprove::create($inputData);
                $riwayatCheck->total_data_lolos += 1;
            } else if ($qualityData['damaged'] != null) {
                New_product::create($inputData);
                $riwayatCheck->total_data_damaged += 1;
            } else if ($qualityData['abnormal'] != null) {
                New_product::create($inputData);
                $riwayatCheck->total_data_abnormal += 1;
            }
    
            // $totalDataIn = $totalLolos = $totalDamaged = $totalAbnormal = 0;
            $totalDataIn = 1 + $riwayatCheck->total_data_in;
    
           
            $totalDiscrepancy = Product_old::where('code_document', $request->input('code_document'))->pluck('code_document');
    
            $riwayatCheck->update([
                'total_data_in' => $totalDataIn,
                'total_data_lolos' => $riwayatCheck->total_data_lolos,
                'total_data_damaged' => $riwayatCheck->total_data_damaged,
                'total_data_abnormal' => $riwayatCheck->total_data_abnormal,
                'total_discrepancy' => count($totalDiscrepancy),
                'status_approve' => 'pending',
    
                // persentase
                'percentage_total_data' => ($document->total_column_in_document / $document->total_column_in_document) * 100,
                'percentage_in' => ($totalDataIn / $document->total_column_in_document) * 100,
                'percentage_lolos' => ($riwayatCheck->total_data_lolos / $document->total_column_in_document) * 100,
                'percentage_damaged' => ($riwayatCheck->total_data_damaged / $document->total_column_in_document) * 100,
                'percentage_abnormal' => ($riwayatCheck->total_data_abnormal / $document->total_column_in_document) * 100,
                'percentage_discrepancy' => (count($totalDiscrepancy) / $document->total_column_in_document) * 100,
            ]);
    
            $this->updateDocumentStatus($request->input('code_document'));
    
            DB::commit();

            return new ProductapproveResource(true, true, "New Produk Berhasil ditambah", $inputData);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function prepareQualityData($status, $description)
    {
        return [
            'lolos' => $status === 'lolos' ? 'lolos' : null,
            'damaged' => $status === 'damaged' ? $description : null,
            'abnormal' => $status === 'abnormal' ? $description : null,
        ];
    }

    private function prepareInputData($request, $status, $qualityData)
    {

        $inputData = $request->only([
            'code_document',
            'old_barcode_product',
            'new_barcode_product',
            'new_name_product',
            'new_quantity_product',
            'new_price_product',
            'old_price_product',
            'new_status_product',
            'new_category_product',
            'new_tag_product',
            'condition',
            'deskripsi',

        ]);

        if ($inputData['old_price_product'] < 100000) {
            $inputData['new_barcode_product'] = $inputData['old_barcode_product'];
        }

        $inputData['new_date_in_product'] = Carbon::now('Asia/Jakarta')->toDateString();
        $inputData['new_quality'] = json_encode($qualityData);

        $inputData['new_discount'] = 0;
        $inputData['display_price'] = $inputData['new_price_product'];

        if ($status !== 'lolos') {
            $inputData['new_category_product'] = null;
            $inputData['new_price_product'] = null;
        }

        if ($inputData['new_price_product'] == null) {
            $inputData['display_price'] = 0;
        }

        return $inputData;
    }

    private function updateDocumentStatus($codeDocument)
    {
        $document = Document::where('code_document', $codeDocument)->firstOrFail();
        if ($document->status_document === 'pending') {
            $document->update(['status_document' => 'in progress']);
        }
    }

    private function deleteOldProduct($code_document, $old_barcode_product)
    {
        $affectedRows = DB::table('product_olds')->where('code_document', $code_document)
            ->where('old_barcode_product', $old_barcode_product)->delete();

        if ($affectedRows > 0) {
            return true;
        } else {
            return new ResponseResource(false, "Produk lama dengan barcode tidak ditemukan.", null);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(ProductInput $productInput)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProductInput $productInput)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProductInput $productInput)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProductInput $productInput)
    {
        //
    }
}
