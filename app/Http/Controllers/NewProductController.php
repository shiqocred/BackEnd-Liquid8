<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Sale;
use App\Models\Bundle;
use App\Models\Category;
use App\Models\Document;
use App\Models\ExcelOld;
use App\Models\BundleQcd;
use App\Models\Color_tag;
use App\Models\New_product;
use App\Models\Product_old;
use App\Models\Notification;
use App\Models\RiwayatCheck;
use Illuminate\Http\Request;
use App\Models\ExcelOldColor;
use App\Models\ListProductBP;
use App\Models\RepairProduct;
use App\Models\ProductApprove;
use App\Models\StagingProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Http\Resources\ResponseResource;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

class NewProductController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->input('q');
        $newProducts = New_product::latest()->where(function ($queryBuilder) use ($query) {
            $queryBuilder->where('old_barcode_product', 'LIKE', '%' . $query . '%')
                ->orWhere('new_barcode_product', 'LIKE', '%' . $query . '%')
                ->orWhere('new_tag_product', 'LIKE', '%' . $query . '%')
                ->orWhere('new_category_product', 'LIKE', '%' . $query . '%')
                ->orWhere('new_name_product', 'LIKE', '%' . $query . '%');
        })->where('new_status_product', '!=', 'dump')
            ->where('new_status_product', '!=', 'expired')
            ->where('new_status_product', '!=', 'sale')
            ->where('new_status_product', '!=', 'migrate')
            ->where('new_status_product', '!=', 'repair')
            ->paginate(100);

        // $startNumber = ($newProducts->currentPage() - 1) * $newProducts->perPage() + 1 ;

        // $newProducts->getCollection()->transform(function($product) use (&$startNumber){
        //     $product->number = $startNumber++;
        //     return $product;
        // });

        return new ResponseResource(true, "list new product", $newProducts);
    }

    public function byDocument(Request $request)
    {
        $query = $request->input('code_document');

        $newProducts = New_product::where('code_document', $query)->paginate(100);

        if ($newProducts->isEmpty()) {
            return new ResponseResource(false, "No data found", null);
        }

        return new ResponseResource(true, "List new products", $newProducts);
    }



    public function create() {}

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code_document' => 'required',
            'old_barcode_product' => 'required',
            'new_barcode_product' => 'required|unique:new_products,new_barcode_product',
            'new_name_product' => 'required',
            'new_quantity_product' => 'required|integer',
            'new_price_product' => 'required|numeric',
            'old_price_product' => 'required|numeric',
            // 'new_date_in_product' => 'required|date',
            'new_status_product' => 'required|in:display,expired,promo,bundle,palet,dump',
            'condition' => 'required|in:lolos,damaged,abnormal',
            'new_category_product' => 'nullable|exists:categories,name_category',
            'new_tag_product' => 'nullable|exists:color_tags,name_color'
        ],  [
            'new_barcode_product.unique' => 'barcode sudah ada',

        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        DB::beginTransaction();

        try {
            // Logika untuk memproses data
            $status = $request->input('condition');
            $description = $request->input('deskripsi', '');

            $qualityData = $this->prepareQualityData($status, $description);

            $inputData = $this->prepareInputData($request, $status, $qualityData);


            $newProduct = New_product::create($inputData);


            $this->updateDocumentStatus($request->input('code_document'));

            $this->deleteOldProduct($request->input('old_barcode_product'));

            DB::commit();

            $this->updateDocumentStatus($request->input('code_document'));

            $this->deleteOldProduct($request->input('old_barcode_product'));

            DB::commit();

            return new ResponseResource(true, "New Produk Berhasil ditambah", $newProduct);
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
            'abnormal' => $status === 'abnormal' ? $description : null
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
            'new_tag_product'
        ]);

        $inputData['new_date_in_product'] = Carbon::now('Asia/Jakarta')->toDateString();
        $inputData['new_quality'] = json_encode($qualityData);

        if ($status !== 'lolos') {
            $inputData['new_category_product'] = null;
            $inputData['new_price_product'] = null;
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

    private function deleteOldProduct($old_barcode_product)
    {

        $oldProduct = Product_old::where('old_barcode_product', $old_barcode_product)->first();

        if ($oldProduct) {
            $oldProduct->delete();
        } else {

            return new ResponseResource(false, "Produk lama dengan barcode tidak ditemukan.", $oldProduct);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(New_product $new_product)
    {
        return new ResponseResource(true, "data new product", $new_product);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(New_product $new_product)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, New_product $new_product)
    {
        $validator = Validator::make($request->all(), [
            'code_document' => 'nullable',
            'old_barcode_product' => 'nullable',
            'new_barcode_product' => 'required',
            'new_name_product' => 'required',
            'new_quantity_product' => 'required|integer',
            'new_price_product' => 'required|numeric',
            'old_price_product' => 'required|numeric',
            'new_status_product' => 'required|in:display,expired,promo,bundle,palet,dump,sale,migrate',
            'condition' => 'nullable',
            'new_category_product' => 'nullable',
            'new_tag_product' => 'nullable|exists:color_tags,name_color',
            'new_discount' => 'nullable|numeric',
            'display_price' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $status = $request->input('condition');
        $description = $request->input('deskripsi', '');

        $qualityData = [
            'lolos' => $status === 'lolos' ? 'lolos' : null,
            'damaged' => $status === 'damaged' ? $description : null,
            'abnormal' => $status === 'abnormal' ? $description : null,
        ];


        $inputData = $request->only([
            'code_document',
            'old_barcode_product',
            'new_barcode_product',
            'new_name_product',
            'new_quantity_product',
            'new_price_product',
            'old_price_product',
            'new_date_in_product',
            'new_status_product',
            'new_category_product',
            'new_tag_product',
            'new_discount',
            'display_price'
        ]);

        $indonesiaTime = Carbon::now('Asia/Jakarta');
        $inputData['new_date_in_product'] = $indonesiaTime->toDateString();


        if ($inputData['old_price_product'] > 100000) {
            $inputData['new_tag_product'] = null;
        }

        if ($request->input('old_price_product') <= 100000) {
            $tagwarna = Color_tag::where('min_price_color', '<=', $request->input('old_price_product'))
                ->where('max_price_color', '>=', $request->input('old_price_product'))
                ->select('fixed_price_color', 'name_color')->first();
            $inputData['new_tag_product'] = $tagwarna['name_color'];
            $inputData['new_price_product'] = $tagwarna['fixed_price_color'];
            $inputData['new_category_product'] = null;
        }

        if ($status !== 'lolos') {
            // Set nilai-nilai default jika status bukan 'lolos'
            $inputData['new_price_product'] = null;
            $inputData['new_category_product'] = null;
        }

        $inputData['new_quality'] = json_encode($qualityData);

        if ($new_product->new_category_product != null) {
            $inputData['new_barcode_product'] = $new_product->new_barcode_product;
        }

        $new_product->update($inputData);

        return new ResponseResource(true, "New Produk Berhasil di Update", $new_product);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(New_product $new_product)
    {
        $new_product->delete();
        return new ResponseResource(true, "data berhasil di hapus", $new_product);
    }

    public function deleteAll()
    {
        try {
            // ListProductBP::query()->delete();
            New_product::query()->delete();
            return new ResponseResource(true, "data berhasil dihapus", null);
        } catch (\Exception $e) {
            return response()->json(["error" => $e], 402);
        }
    }

    public function expireProducts()
    {
        $fourWeeksAgo = now()->subWeeks(4)->toDateString();

        $products = New_product::where('new_date_in_product', '<=', $fourWeeksAgo)
            ->where('new_status_product', 'display')
            ->get();

        foreach ($products as $product) {
            $product->update(['new_status_product' => 'expired']);
        }

        return new ResponseResource(true, "Products expired successfully", $products);
    }


    public function listProductExp(Request $request)
    {
        try {
            $query = $request->input('q');
            $productExpired = New_product::where(function ($queryBuilder) use ($query) {
                $queryBuilder->where('new_status_product', 'expired')
                    ->where('new_name_product', 'LIKE', '%' . $query  . '%');
            })->paginate(50);

            return new ResponseResource(true, "list product expired", $productExpired);
        } catch (\Exception $e) {
            return response()->json(["error" => $e]);
        }
    }

    public function listProductExpDisplay(Request $request)
    {
        try {
            $query = $request->input('q');
            $productExpDisplay = New_product::where(function ($queryBuilder) use ($query) {
                $queryBuilder->where('new_status_product', 'expired')
                    ->orWhere('new_status_product', 'display');
            })->where(function ($subBuilder) use ($query) {
                $subBuilder->where('new_name_product', 'LIKE', '%' . $query  . '%')
                    ->orWhere('new_barcode_product', 'LIKE', '%' . $query  . '%')
                    ->orWhere('old_barcode_product', 'LIKE', '%' . $query  . '%')
                    ->orWhere('code_document', 'LIKE', '%' . $query  . '%');
            })->whereRaw("JSON_EXTRACT(new_quality, '$.lolos') IS NOT NULL")
                ->paginate(50);

            foreach ($productExpDisplay as &$product) {
                if ($product['new_tag_product'] !== null) {
                    $fixedPrice = Color_tag::where('name_color', $product['new_tag_product'])->first();

                    if (!$fixedPrice) {
                        return new ResponseResource(false, "Data kosong", null);
                    }
                    $product['fixed_price'] = $fixedPrice->fixed_price_color;
                }
            }

            return new ResponseResource(true, "List product expired", $productExpDisplay);
        } catch (\Exception $e) {
            return response()->json(["error" => $e]);
        }
    }

    //inject category display -> inventory
    public function processExcelFilesCategory(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');
        $user_id = auth()->id();

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
            'file.unique' => 'Nama file sudah ada di database.',
        ]);

        $file = $request->file('file');
        $filePath = $file->getPathname();
        $fileName = $file->getClientOriginalName();
        $file->storeAs('public/ekspedisis', $fileName);

        DB::beginTransaction();

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $header = $sheet->rangeToArray('A1:' . $sheet->getHighestColumn() . '1', NULL, TRUE, FALSE, TRUE)[1];
            $dataToInsert = [];
            $rowCount = 0;

            foreach ($sheet->getRowIterator(2) as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(FALSE);

                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $rowData[] = $cell->getValue() ?? '';
                }

                if (count($header) === count($rowData)) {
                    $dataToInsert[] = ['data' => json_encode(array_combine($header, $rowData))];
                    $rowCount++;
                }
            }

            $chunks = array_chunk($dataToInsert, 500);
            foreach ($chunks as $chunk) {
                ExcelOld::insert($chunk);
            }

            // Create a new document with the rowCount
           $docs= Document::create([
                'code_document' => $this->generateDocumentCode(),
                'base_document' => $fileName,
                'total_column_document' => count($header),
                'total_column_in_document' => $rowCount,
                'status_document' => 'done',
                'date_document' => Carbon::now('Asia/Jakarta')->toDateString()
            ]);

            // Call mapAndMergeHeaders function here
            $mergeResponse = $this->mapAndMergeHeadersCategory();

            // Decode the response if it is in JSON format
            $mergeResponseArray = json_decode(json_encode($mergeResponse), true);

            if ($mergeResponseArray['status'] === false) {
                DB::rollback();
                return response()->json($mergeResponseArray, 422);
            }
            
           $history= RiwayatCheck::create([
                'user_id' => $user_id,
                'code_document' => $docs->code_document,
                'base_document' => $fileName,
                'total_data' => $docs->total_column_in_document,
                'total_data_in' => $docs->total_column_in_document,
                'total_data_lolos' => $docs->total_column_in_document,
                'total_data_damaged' => 0,
                'total_data_abnormal' => 0,
                'total_discrepancy' => 0,
                'status_approve' => 'done',
    
                // persentase
                'precentage_total_data' => 0,
                'percentage_in' => 0,
                'percentage_lolos' => 0,
                'percentage_damaged' => 0,
                'percentage_abnormal' => 0,
                'percentage_discrepancy' => 0,
                'total_price' => 0
            ]);

            Notification::create([
                'user_id' => $user_id,
                'notification_name' => 'bulking category',
                'role' => 'Spv',
                'read_at' => Carbon::now('Asia/Jakarta'),
                'riwayat_check_id' =>  $history->id,
                'repair_id' => null,
                'status'=> 'staging'
            ]);


            DB::commit();

            return new ResponseResource(true, "Data berhasil diproses dan disimpan", [
                'code_document' => Document::latest()->first(),
                'file_name' => $fileName,
                'total_column_count' => count($header),
                'total_row_count' => $rowCount,
                'merged' => $mergeResponse
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    protected function generateDocumentCode()
    {
        $latestDocument = Document::latest()->first();
        $newId = $latestDocument ? $latestDocument->id + 1 : 1;
        $id_document = str_pad($newId, 4, '0', STR_PAD_LEFT);
        $month = date('m');
        $year = date('Y');
        return $id_document . '/' . $month . '/' . $year;
    }

    protected function mapAndMergeHeadersCategory()
    {
        set_time_limit(300);
        $headerMappings = [
            'old_barcode_product' => ['Barcode'],
            'new_barcode_product' => ['Barcode'],
            'new_name_product' => ['Description'],
            'new_category_product' => ['Category'],
            'new_quantity_product' => ['Qty'],
            'new_price_product' => ['Price After Discount'],
            'old_price_product' => ['Unit Price'],
            'new_date_in_product' => ['Date'],
            'display_price' => ['Price After Discount'],
        ];

        $latestDocument = Document::latest()->first();
        if (!$latestDocument) {
            return response()->json(['error' => 'No documents found.'], 404);
        }
        $code_document = $latestDocument->code_document;

        $ekspedisiData = ExcelOld::all()->map(function ($item) {
            return json_decode($item->data, true);
        });

        $mergedData = [
            'old_barcode_product' => [],
            'new_barcode_product' => [],
            'new_name_product' => [],
            'new_category_product' => [],
            'new_quantity_product' => [],
            'new_price_product' => [],
            'old_price_product' => [],
            'new_date_in_product' => [],
            'new_quality' => [],
            'new_discount' => [],
            'display_price' => [],
        ];

        foreach ($ekspedisiData as $dataItem) {
            foreach ($headerMappings as $templateHeader => $selectedHeaders) {
                foreach ($selectedHeaders as $userSelectedHeader) {
                    if (isset($dataItem[$userSelectedHeader])) {
                        $mergedData[$templateHeader][] = $dataItem[$userSelectedHeader];
                    }
                }
            }

            $status = $dataItem['Status'] ?? 'unknown';
            $description = $dataItem['Description'] ?? '';

            $qualityData = [
                'lolos' => $status === 'lolos' ? true : null,
                'damaged' => $status === 'damaged' ? $description : null,
                'abnormal' => $status === 'abnormal' ? $description : null,
            ];

            $mergedData['new_quality'][] = json_encode(['lolos' => 'lolos']);
        }

        // Mengecek data yang ada di tabel excel_olds apakah ada barcode double
        // Variabel penampung barcode double ini adalah $responseBarcode
        $responseBarcode = collect();
        foreach ($mergedData['old_barcode_product'] as $index => $barcode) {
            $new_product = StagingProduct::where('new_barcode_product', $barcode)->first();
            if ($new_product) {
                $responseBarcode->push($barcode);
            }
        }

        if ($responseBarcode->isNotEmpty()) {
            ExcelOld::query()->delete();
            return new ResponseResource(false, "List data barcode yang duplikat", $responseBarcode);
        }

        // Menyimpan data yang digabungkan ke dalam model New_product
        foreach ($mergedData['old_barcode_product'] as $index => $barcode) {
            $quantity = isset($mergedData['new_quantity_product'][$index]) && $mergedData['new_quantity_product'][$index] !== '' ? $mergedData['new_quantity_product'][$index] : 0; // Set default to 0 if empty
            $newProductData = [
                'code_document' => $code_document,
                'old_barcode_product' => $barcode,
                'new_barcode_product' => $mergedData['new_barcode_product'][$index] ?? null,
                'new_name_product' => $mergedData['new_name_product'][$index] ?? null,
                'new_category_product' => $mergedData['new_category_product'][$index] ?? null,
                'new_quantity_product' => $quantity,
                'new_price_product' => $mergedData['new_price_product'][$index] ?? null,
                'old_price_product' => $mergedData['old_price_product'][$index] ?? null,
                'new_date_in_product' => $mergedData['new_date_in_product'][$index] ?? Carbon::now('Asia/Jakarta')->toDateString(),
                'new_quality' => $mergedData['new_quality'][$index],
                'new_discount' => 0,
                'display_price' => $mergedData['display_price'][$index] ?? null,
            ];

            StagingProduct::create($newProductData);
        }

        ExcelOld::query()->delete();

        Log::info('Merged data prepared for response', ['mergedData' => $mergedData]);

        return new ResponseResource(true, "Data berhasil digabungkan dan disimpan.", null);
    }

    //end enject display

    //inject tag warna
    public function processExcelFilesTagColor(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
            'file.unique' => 'Nama file sudah ada di database.',
        ]);

        $file = $request->file('file');
        $filePath = $file->getPathname();
        $fileName = $file->getClientOriginalName();
        $file->storeAs('public/ekspedisis', $fileName);

        DB::beginTransaction();

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $header = $sheet->rangeToArray('A1:' . $sheet->getHighestColumn() . '1', NULL, TRUE, FALSE, TRUE)[1];
            $dataToInsert = [];
            $rowCount = 0;

            foreach ($sheet->getRowIterator(2) as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(FALSE);

                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $rowData[] = $cell->getValue() ?? '';
                }

                if (count($header) === count($rowData)) {
                    $dataToInsert[] = ['data' => json_encode(array_combine($header, $rowData))];
                    $rowCount++;
                }
            }

            $chunks = array_chunk($dataToInsert, 500);
            foreach ($chunks as $chunk) {
                ExcelOldColor::insert($chunk);
            }


            // Create a new document with the rowCount
            Document::create([
                'code_document' => $this->generateDocumentCode(),
                'base_document' => $fileName,
                'total_column_document' => count($header),
                'total_column_in_document' => $rowCount,
                'date_document' => Carbon::now('Asia/Jakarta')->toDateString()
            ]);


            // Call mapAndMergeHeaders function here
            $mergeResponse = $this->mapAndMergeHeadersTagColor();
            // Decode the response if it is in JSON format
            $mergeResponseArray = json_decode(json_encode($mergeResponse), true);

            if ($mergeResponseArray['status'] === false) {
                DB::rollback();
                return response()->json($mergeResponseArray, 422);
            }
            DB::commit();
            return new ResponseResource(true, "Data berhasil diproses dan disimpan", [
                'code_document' => Document::latest()->first(),
                'file_name' => $fileName,
                'total_column_count' => count($header),
                'total_row_count' => $rowCount,
                'merged' => $mergeResponse
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    protected function mapAndMergeHeadersTagColor()
    {
        set_time_limit(300);
        $headerMappings = [
            'old_barcode_product' => ['Waybill'],
            'new_barcode_product' => ['Waybill'],
            'new_name_product' => ['Isi Barang'],
            'new_quantity_product' => ['Qty'],
            'old_price_product' => ['Nilai Barang Satuan'],
            'new_date_in_product' => ['Date'],
        ];

        $latestDocument = Document::latest()->first();
        if (!$latestDocument) {
            return response()->json(['error' => 'No documents found.'], 404);
        }
        $code_document = $latestDocument->code_document;

        $ekspedisiData = ExcelOldColor::all()->map(function ($item) {
            return json_decode($item->data, true);
        });



        $mergedData = [
            'old_barcode_product' => [],
            'new_barcode_product' => [],
            'new_name_product' => [],
            'new_category_product' => [],
            'new_tag_product' => [],
            'new_quantity_product' => [],
            'new_price_product' => [],
            'old_price_product' => [],
            'new_date_in_product' => [],
            'new_quality' => [],
            'new_discount' => [],
            'display_price' => [],
        ];

        foreach ($ekspedisiData as $dataItem) {
            foreach ($headerMappings as $templateHeader => $selectedHeaders) {
                foreach ($selectedHeaders as $userSelectedHeader) {
                    if (isset($dataItem[$userSelectedHeader])) {
                        $mergedData[$templateHeader][] = $dataItem[$userSelectedHeader];
                    }
                }
            }

            $status = $dataItem['Status'] ?? 'unknown';
            $description = $dataItem['Description'] ?? '';

            $qualityData = [
                'lolos' => $status === 'lolos' ? true : null,
                'damaged' => $status === 'damaged' ? $description : null,
                'abnormal' => $status === 'abnormal' ? $description : null,
            ];

            $mergedData['new_quality'][] = json_encode(['lolos' => 'lolos']);
        }

        // Mengecek data yang ada di tabel excel_olds apakah ada barcode double
        // Variabel penampung barcode double ini adalah $responseBarcode
        $responseBarcode = collect();
        foreach ($mergedData['old_barcode_product'] as $index => $barcode) {
            $new_product = New_product::where('new_barcode_product', $barcode)->first();
            if ($new_product) {
                $responseBarcode->push($barcode);
            }
        }

        if ($responseBarcode->isNotEmpty()) {
            ExcelOldColor::query()->delete();
            return new ResponseResource(false, "List data barcode yang duplikat", $responseBarcode);
        }


        // Menyimpan data yang digabungkan ke dalam model New_product
        foreach ($mergedData['old_barcode_product'] as $index => $barcode) {
            if ($mergedData['old_price_product'][$index] <= 99999) {
                $colors = Color_tag::where('min_price_color', '<=', $mergedData['old_price_product'][$index])
                    ->where('max_price_color', '>=', $mergedData['old_price_product'][$index])
                    ->first();

                if ($colors) {
                    $mergedData['new_tag_product'][$index] = $colors->name_color;
                    $mergedData['display_price'][$index] = $colors->fixed_price_color;
                    $mergedData['new_price_product'][$index] = $colors->fixed_price_color;
                }
            }
            $quantity = isset($mergedData['new_quantity_product'][$index]) && $mergedData['new_quantity_product'][$index] !== '' ? $mergedData['new_quantity_product'][$index] : 0; // Set default to 0 if empty
            $newProductData = [
                'code_document' => $code_document,
                'old_barcode_product' => $barcode,
                'new_barcode_product' => $mergedData['new_barcode_product'][$index] ?? null,
                'new_name_product' => $mergedData['new_name_product'][$index] ?? null,
                'new_category_product' => null,
                'new_tag_product' => $mergedData['new_tag_product'][$index] ?? null,
                'new_quantity_product' => $quantity,
                'new_price_product' => $mergedData['new_price_product'][$index] ?? null,
                'old_price_product' => $mergedData['old_price_product'][$index] ?? null,
                'new_date_in_product' => $mergedData['new_date_in_product'][$index] ?? Carbon::now('Asia/Jakarta')->toDateString(),
                'new_quality' => $mergedData['new_quality'][$index],
                'new_discount' => 0,
                'display_price' => $mergedData['display_price'][$index] ?? null,
            ];

            New_product::create($newProductData);
        }

        ExcelOldColor::query()->delete();


        return new ResponseResource(true, "Data berhasil digabungkan dan disimpan.", null);
    }
    //end inject tag warna



    public function showRepair(Request $request)
    {
        try {
            $query = $request->get('q');
            $products = New_product::where(function ($queryBuilder) use ($query) {
                $queryBuilder->where('new_status_product', '!=', 'dump')
                    ->where(function ($q) {
                        $q->where('new_quality->damaged', '!=', null)
                            ->orWhere('new_quality->abnormal', '!=', null);
                    });

                if ($query) {
                    $queryBuilder->where(function ($q) use ($query) {
                        $q->where('old_barcode_product', 'like', '%' . $query . '%')
                            ->orWhere('new_barcode_product', 'like', '%' . $query . '%')
                            ->orWhere('new_tag_product', 'like', '%' . $query . '%')
                            ->orWhere('new_name_product', 'like', '%' . $query . '%')
                            ->orWhere('new_category_product', 'LIKE', '%' . $query . '%');
                    });
                }
            })
                ->paginate(50);


            if ($products->isEmpty()) {
                return new ResponseResource(false, "Tidak ada data", $products);
            }

            return new ResponseResource(true, "List damaged dan abnormal", $products);
        } catch (\Exception $e) {
            return new ResponseResource(false, "Terjadi kesalahan: " . $e->getMessage(), null);
        }
    }


    public function updateRepair(Request $request, $id)
    {
        try {
            $product = New_product::find($id);

            if (!$product) {
                return new ResponseResource(false, "Produk tidak ditemukan", null);
            }

            $quality = json_decode($product->new_quality, true);

            if (isset($quality['lolos'])) {
                return new ResponseResource(false, "Hanya produk yang damaged atau abnormal yang bisa di repair", null);
            }

            if ($quality['damaged']) {
                $quality['damaged'] = null;
            }

            if ($quality['abnormal']) {
                $quality['abnormal'] = null;
            }

            $validator = Validator::make($request->all(), [
                'old_barcode_product' => 'required',
                'new_barcode_product' => 'required',
                'new_name_product' => 'required',
                'new_quantity_product' => 'required|integer',
                'new_price_product' => 'required|numeric',
                'old_price_product' => 'required|numeric',
                'new_status_product' => 'required|in:display,expired,promo,bundle,palet',
                'new_category_product' => 'nullable|exists:categories,name_category',
                'new_tag_product' => 'nullable|exists:color_tags,name_color'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $inputData = $request->only([
                'old_barcode_product',
                'new_barcode_product',
                'new_name_product',
                'new_quantity_product',
                'new_price_product',
                'old_price_product',
                'new_date_in_product',
                'new_status_product',
                'new_category_product',
                'new_tag_product'
            ]);

            $indonesiaTime = Carbon::now('Asia/Jakarta');
            $inputData['new_date_in_product'] = $indonesiaTime;

            $quality['lolos'] = 'lolos';
            $inputData['new_quality'] = json_encode($quality);

            if ($inputData['new_price_product'] > 100000) {
                $inputData['new_category_product'] = null;
            }

            if ($inputData['old_price_product'] < 100000) {

                $inputData['new_category_product'] = null;

                $colortag = Color_tag::where('min_price_color', '<=', $inputData['old_price_product'])
                    ->where('max_price_color', '>=', $inputData['old_price_product'])
                    ->select('fixed_price_color', 'name_color')
                    ->first();

                $inputData['new_price_product'] = $colortag['fixed_price_color'];
                $inputData['new_tag_product'] = $colortag['name_color'];
            }





            $product->update($inputData);

            return new ResponseResource(true, "Berhasil di repair", $inputData);
        } catch (\Exception $e) {
            return new ResponseResource(false, "Terjadi kesalahan: " . $e->getMessage(), null);
        }
    }


    public function MultipleUpdateRepair(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:new_products,id'
        ]);

        $ids = $request->input('ids');
        $updatedProducts = [];

        foreach ($ids as $id) {
            $product = New_product::find($id);

            if (!$product) {
                continue;
            }

            $quality = json_decode($product->new_quality, true);

            if (isset($quality['lolos']) && $quality['lolos'] === 'lolos') {
                continue;
            }

            $quality = array_merge($quality, ['damaged' => null, 'abnormal' => null, 'lolos' => 'lolos']); // Reset 'damaged' dan 'abnormal', set 'lolos'

            $product->new_quality = json_encode($quality);
            $product->save();

            $updatedProducts[] = $product;
        }

        if (empty($updatedProducts)) {
            return response()->json(['message' => "Tidak ada produk yang berhasil di-update"], 404);
        }

        return response()->json(['message' => "Produk berhasil di-update", 'data' => $updatedProducts]);
    }

    public function updateAllDamagedOrAbnormal()
    {
        $products = New_product::all()->filter(function ($product) {
            $quality = json_decode($product->new_quality, true);
            return isset($quality['damaged']) || isset($quality['abnormal']);
        });

        foreach ($products as $product) {
            $quality = json_decode($product->new_quality, true);

            unset($quality['damaged'], $quality['abnormal']);
            $quality['lolos'] = 'lolos';

            $product->new_quality = json_encode($quality);
            $product->save();
        }

        return new ResponseResource(true, "Semua produk damaged dan abnormal sudah berhasil di update menjadi lolos", $products);
    }

    public function excelolds()
    {
        $datas = ExcelOld::latest()->paginate(100);
        return new ResponseResource(true, "list product olds", $datas);
    }

    public function updateDump($id)
    {
        $product = New_product::find($id);

        if ($product->new_status_product == 'dump') {
            return new ResponseResource(false, "status product sudah dump", $product);
        }

        if (!$product) {
            return new ResponseResource(false, "Produk tidak ditemukan", null);
        }

        $quality = json_decode($product->new_quality, true);


        if (isset($quality['lolos'])) {
            return new ResponseResource(false, "Hanya produk yang damaged atau abnormal yang bisa di repair", null);
        }

        $product->update(['new_status_product' => 'dump']);

        return new ResponseResource(true, "data product sudah di update", $product);
    }

    public function listDump(Request $request)
    {
        $query = $request->get('q');

        $products = New_product::where('new_status_product', 'dump')
            ->where(function ($queryBuilder) use ($query) {
                $queryBuilder->where('old_barcode_product', 'like', '%' . $query . '%')
                    ->orWhere('new_barcode_product', 'like', '%' . $query . '%')
                    ->orWhere('new_tag_product', 'like', '%' . $query . '%')
                    ->orWhere('new_category_product', 'like', '%' . $query . '%')
                    ->orWhere('new_name_product', 'like', '%' . $query . '%');
            })
            ->paginate(100);

        // $products2 = RepairProduct::where('new_status_product', 'dump')
        //     ->where(function ($queryBuilder) use ($query) {
        //         $queryBuilder->where('old_barcode_product', 'like', '%' . $query . '%')
        //             ->orWhere('new_barcode_product', 'like', '%' . $query . '%')
        //             ->orWhere('new_tag_product', 'like', '%' . $query . '%')
        //             ->orWhere('new_category_product', 'like', '%' . $query . '%')
        //             ->orWhere('new_name_product', 'like', '%' . $query . '%');
        //     })
        //     ->paginate(50);

        // // Menggabungkan data dari kedua respons menjadi satu array
        // $products = array_merge($products1->items(), $products2->items());

        return new ResponseResource(true, "List dump", $products);
    }


    public function getTagColor(Request $request)
    {
        $query = $request->input('q');
        try {
            $productByTagColor = New_product::latest()
                ->whereNotNull('new_tag_product')
                ->when($query, function ($queryBuilder) use ($query) {
                    $queryBuilder->where('new_tag_product', 'LIKE', '%' . $query . '%')
                        ->orWhere('new_barcode_product', 'LIKE', '%' . $query . '%')
                        ->orWhere('old_barcode_product', 'LIKE', '%' . $query . '%')
                        ->orWhere('new_category_product', 'LIKE', '%' . $query . '%')
                        ->orWhere('new_tag_product', 'LIKE', '%' . $query . '%')
                        ->orWhere('new_name_product', 'LIKE', '%' . $query . '%');
                })
                ->paginate(50);
        } catch (\Exception $e) {
            return (new ResponseResource(false, "data tidak ada", $e->getMessage()))->response()->setStatusCode(500);
        }

        return new ResponseResource(true, "list product by tag color", $productByTagColor);
    }

    public function getByCategory(Request $request)
    {
        $query = $request->input('q');
        try {
            $productQuery = New_product::select(
                'id',
                'new_barcode_product',
                'new_name_product',
                'new_category_product',
                'new_price_product',
                'created_at',
                'new_status_product',
                'display_price',
                'new_date_in_product'
            )
                ->whereNotNull('new_category_product')
                ->whereNotIn('new_status_product', ['repair', 'sale', 'migrate'])
                ->when($query, function ($queryBuilder) use ($query) {
                    $queryBuilder->where(function ($subQuery) use ($query) {
                        $subQuery->whereNotNull('new_category_product')
                            ->where('new_category_product', 'LIKE', '%' . $query . '%')
                            ->orWhere('new_barcode_product', 'LIKE', '%' . $query . '%')
                            ->orWhere('old_barcode_product', 'LIKE', '%' . $query . '%')
                            ->orWhere('new_name_product', 'LIKE', '%' . $query . '%')
                            ->orWhere('new_status_product', 'LIKE', '%' . $query . '%');
                    });
                });

            $bundleQuery = Bundle::select(
                'id',
                'barcode_bundle as new_barcode_product',
                'name_bundle as new_name_product',
                'category as new_category_product',
                'total_price_custom_bundle as new_price_product',
                'created_at',
                'product_status as new_status_product',
                'total_price_custom_bundle as display_price',
                'created_at as new_date_in_product'
            )->when($query, function ($dataBundle) use ($query) {
                $dataBundle->where('name_bundle', 'LIKE', '%' . $query . '%')
                    ->orWhere('barcode_bundle', 'LIKE', '%' . $query . '%')
                    ->orWhere('category', 'LIKE', '%' . $query . '%')
                    ->orWhere('product_status', 'LIKE', '%' . $query . '%');
            });

            $mergedQuery = $productQuery->union($bundleQuery)
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        } catch (\Exception $e) {
            return (new ResponseResource(false, "data tidak ada", $e->getMessage()))->response()->setStatusCode(500);
        }

        return new ResponseResource(true, "list product by tag color", $mergedQuery);
    }




    public function updatePriceDump(Request $request, $id)
    {
        $product = New_product::find($id);

        if (!$product) {
            return new ResponseResource(false, "id product tidak ditemukan", $product);
        }

        $validator = Validator::make($request->all(), [
            'new_price_product' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $inputData = $request->only([
            'new_price_product',
        ]);

        $indonesiaTime = Carbon::now('Asia/Jakarta');
        $inputData['new_date_in_product'] = $indonesiaTime->toDateString();

        $updateDump = $product->update($inputData);

        return new ResponseResource(true, "New Produk Berhasil di Update", $updateDump);
    }

    public function exportDumpToExcel(Request $request, $id)
    {
        set_time_limit(300);

        $bundleQcds = BundleQcd::find($id)->load(['product_qcds']);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Name bundle',
            'total_price_bundle',
            'total price custom bundle',
            'total product bundle',
            'barcode_bundle',
        ];

        $headers2 = [
            'Name',
            'New Price',
            'Old Price',
            'Qty',
            'Category',
            'Harga Tag Warna',
            'New Barcode'
        ];

        $columnIndex = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($columnIndex++, 1, $header);
        }

        $currentRow = 2;
        $sheet->setCellValueByColumnAndRow(1, $currentRow, $bundleQcds->name_bundle);
        $sheet->setCellValueByColumnAndRow(2, $currentRow, $bundleQcds->total_price_bundle);
        $sheet->setCellValueByColumnAndRow(3, $currentRow, $bundleQcds->total_price_custom_bundle);
        $sheet->setCellValueByColumnAndRow(4, $currentRow, $bundleQcds->total_product_bundle);
        $sheet->setCellValueByColumnAndRow(5, $currentRow, $bundleQcds->barcode_bundle);

        $currentRow++;

        // Menambahkan baris kosong antara data headers dan headers2
        $currentRow++;

        $columnIndex = 1;
        foreach ($headers2 as $header) {
            $sheet->setCellValueByColumnAndRow($columnIndex++, $currentRow, $header);
        }
        foreach ($bundleQcds->product_qcds as $product) {
            $currentRow++;
            $sheet->setCellValueByColumnAndRow(1, $currentRow, $product->new_name_product);
            $sheet->setCellValueByColumnAndRow(2, $currentRow, $product->new_price_product);
            $sheet->setCellValueByColumnAndRow(3, $currentRow, $product->old_price_product);
            $sheet->setCellValueByColumnAndRow(4, $currentRow, $product->new_quantity_product);
            $sheet->setCellValueByColumnAndRow(5, $currentRow, $product->new_category_product);
            $sheet->setCellValueByColumnAndRow(6, $currentRow, $product->new_tag_product);
            $sheet->setCellValueByColumnAndRow(7, $currentRow, $product->new_barcode_product);
        }

        $fileName = "bundleQcd.xlsx";

        $publicPath = 'exports';
        $filePath = public_path($publicPath) . '/' . $fileName;

        if (!file_exists(public_path($publicPath))) {
            mkdir(public_path($publicPath), 0777, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $downloadUrl = url($publicPath . '/' . $fileName);

        return new ResponseResource(true, "File siap diunduh.", $downloadUrl);
    }


    public function getLatestPrice(Request $request)
    {
        $category = null;
        $tagwarna = null;
        if ($request['old_price_product'] > 99999) {
            $category = Category::all();
        } else {
            $tagwarna = Color_tag::where('min_price_color', '<=', $request->input('old_price_product'))
                ->where('max_price_color', '>=', $request->input('old_price_product'))
                ->select('fixed_price_color', 'name_color')->first();
        }

        return new ResponseResource(true, 'list category', ["category" => $category, "warna" => $tagwarna]);
    }

    //khusus super admin
    public function addProductByAdmin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'new_barcode_product' => 'required|unique:new_products,new_barcode_product',
            'new_name_product' => 'required',
            'new_quantity_product' => 'required|integer',
            'new_price_product' => 'required|numeric',
            'new_status_product' => 'nullable|in:display,expired,promo,bundle,palet,dump',
            'condition' => 'nullable|in:lolos,damaged,abnormal',
            'new_category_product' => 'nullable|exists:categories,name_category',
            'new_tag_product' => 'nullable|exists:color_tags,name_color'
        ],  [
            'new_barcode_product.unique' => 'barcode sudah ada'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        DB::beginTransaction();

        try {
            // Logika untuk memproses data
            $status = $request->input('condition');
            $description = $request->input('deskripsi', '');

            $qualityData = [
                'lolos' => $status === 'lolos' ? 'lolos' : null,
                'damaged' => $status === 'damaged' ? $description : null,
                'abnormal' => $status === 'abnormal' ? $description : null,
            ];


            $inputData = $request->only([
                'old_price_product',
                'new_barcode_product',
                'new_name_product',
                'new_quantity_product',
                'new_price_product',
                'new_status_product',
                'new_category_product',
                'new_tag_product',
                'price_discount',
            ]);

            $inputData['new_status_product'] = 'display';

            $inputData['new_date_in_product'] = Carbon::now('Asia/Jakarta')->toDateString();
            $inputData['new_quality'] = json_encode($qualityData);

            if ($status !== 'lolos') {
                $inputData['new_category_product'] = null;
            }
            $inputData['new_discount'] = 0;
            $inputData['display_price'] = $inputData['new_price_product'];

            $inputData['new_barcode_product'] = generateNewBarcode($inputData['new_category_product']);

            $newProduct = New_product::create($inputData);

            // $this->deleteOldProduct($request->input('old_barcode_product')); 

            DB::commit();

            return new ResponseResource(true, "berhasil menambah data", $newProduct);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function checkPrice(Request $request)
    {
        $totalNewPrice = $request['new_price_product'];

        if ($totalNewPrice < 100000) {
            $tagwarna = Color_tag::where('min_price_color', '<=', $totalNewPrice)
                ->where('max_price_color', '>=', $totalNewPrice)
                ->select('fixed_price_color', 'name_color')->first();

            return new ResponseResource(true, "tag warna", $tagwarna);
        }
    }

    public function totalPerColor(Request $request)
    {

        $new_product = New_product::whereNotNull('new_tag_product')->where('new_status_product', 'display')->pluck('new_tag_product');
        $countByColor = $new_product->countBy(function ($item) {
            return $item;
        });

        if (count($countByColor) < 1) {
            return new ResponseResource(false, "tidak ada data data color", null);
        }
        return new ResponseResource(true, "list data product by color", $countByColor);
    }

    public function exportNewProducts()
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'ID',
            'Code Document',
            'Old Barcode Product',
            'New Barcode Product',
            'New Name Product',
            'New Quantity Product',
            'New Price Product',
            'Old Price Product',
            'New Date In Product',
            'New Status Product',
            'New Quality',
            'New Category Product',
            'New Tag Product',
            'Created At',
            'Updated At'
        ];

        $columnIndex = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($columnIndex, 1, $header);
            $columnIndex++;
        }

        // Variabel untuk melacak baris
        $rowIndex = 2;

        // Mengambil data dalam batch
        New_product::where('new_status_product', '!=', 'sale')
            ->where('new_status_product', '!=', 'migrate')
            ->whereNull('new_tag_product')
            ->chunk(1000, function ($products) use ($sheet, &$rowIndex) {
                foreach ($products as $product) {
                    $sheet->setCellValueByColumnAndRow(1, $rowIndex, $product->id);
                    $sheet->setCellValueByColumnAndRow(2, $rowIndex, $product->code_document);
                    $sheet->setCellValueByColumnAndRow(3, $rowIndex, $product->old_barcode_product);
                    $sheet->setCellValueByColumnAndRow(4, $rowIndex, $product->new_barcode_product);
                    $sheet->setCellValueByColumnAndRow(5, $rowIndex, $product->new_name_product);
                    $sheet->setCellValueByColumnAndRow(6, $rowIndex, $product->new_quantity_product);
                    $sheet->setCellValueByColumnAndRow(7, $rowIndex, $product->new_price_product);
                    $sheet->setCellValueByColumnAndRow(8, $rowIndex, $product->old_price_product);
                    $sheet->setCellValueByColumnAndRow(9, $rowIndex, $product->new_date_in_product);
                    $sheet->setCellValueByColumnAndRow(10, $rowIndex, $product->new_status_product);
                    $sheet->setCellValueByColumnAndRow(11, $rowIndex, $product->new_quality);
                    $sheet->setCellValueByColumnAndRow(12, $rowIndex, $product->new_category_product);
                    $sheet->setCellValueByColumnAndRow(13, $rowIndex, $product->new_tag_product);
                    $sheet->setCellValueByColumnAndRow(14, $rowIndex, $product->created_at);
                    $sheet->setCellValueByColumnAndRow(15, $rowIndex, $product->updated_at);
                    $rowIndex++;
                }
            });

        // Menyimpan file Excel
        $writer = new Xlsx($spreadsheet);
        $fileName = 'new_products_export.xlsx';
        $publicPath = 'exports';
        $filePath = public_path($publicPath) . '/' . $fileName;

        // Membuat direktori exports jika belum ada
        if (!file_exists(public_path($publicPath))) {
            mkdir(public_path($publicPath), 0777, true);
        }

        $writer->save($filePath);

        // Mengembalikan URL untuk mengunduh file
        $downloadUrl = url($publicPath . '/' . $fileName);

        return new ResponseResource(true, "file diunduh", $downloadUrl);
    }

    public function export_product_byCategory(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'ID',
            'Code Document',
            'Old Barcode Product',
            'New Barcode Product',
            'New Name Product',
            'New Quantity Product',
            'New Price Product',
            'Old Price Product',
            'New Date In Product',
            'New Status Product',
            'New Quality',
            'New Category Product',
            'New Tag Product',
            'Created At',
            'Updated At'
        ];

        // Menuliskan headers ke sheet
        $columnIndex = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($columnIndex, 1, $header);
            $columnIndex++;
        }

        // Variabel untuk melacak baris
        $rowIndex = 2;

        // Mengambil data dalam batch
        New_product::whereNotNull('new_category_product')
            ->whereNotIn('new_status_product', ['repair', 'sale', 'migrate'])
            ->chunk(1000, function ($products) use ($sheet, &$rowIndex) {
                foreach ($products as $product) {
                    $sheet->setCellValueByColumnAndRow(1, $rowIndex, $product->id);
                    $sheet->setCellValueByColumnAndRow(2, $rowIndex, $product->code_document);
                    $sheet->setCellValueByColumnAndRow(3, $rowIndex, $product->old_barcode_product);
                    $sheet->setCellValueByColumnAndRow(4, $rowIndex, $product->new_barcode_product);
                    $sheet->setCellValueByColumnAndRow(5, $rowIndex, $product->new_name_product);
                    $sheet->setCellValueByColumnAndRow(6, $rowIndex, $product->new_quantity_product);
                    $sheet->setCellValueByColumnAndRow(7, $rowIndex, $product->new_price_product);
                    $sheet->setCellValueByColumnAndRow(8, $rowIndex, $product->old_price_product);
                    $sheet->setCellValueByColumnAndRow(9, $rowIndex, $product->new_date_in_product);
                    $sheet->setCellValueByColumnAndRow(10, $rowIndex, $product->new_status_product);
                    $sheet->setCellValueByColumnAndRow(11, $rowIndex, $product->new_quality);
                    $sheet->setCellValueByColumnAndRow(12, $rowIndex, $product->new_category_product);
                    $sheet->setCellValueByColumnAndRow(13, $rowIndex, $product->new_tag_product);
                    $sheet->setCellValueByColumnAndRow(14, $rowIndex, $product->created_at);
                    $sheet->setCellValueByColumnAndRow(15, $rowIndex, $product->updated_at);
                    $rowIndex++;
                }
            });

        // Menyimpan file Excel
        $writer = new Xlsx($spreadsheet);
        $fileName = 'new_products_export.xlsx';
        $publicPath = 'exports';
        $filePath = public_path($publicPath) . '/' . $fileName;

        // Membuat direktori exports jika belum ada
        if (!file_exists(public_path($publicPath))) {
            mkdir(public_path($publicPath), 0777, true);
        }

        $writer->save($filePath);

        // Mengembalikan URL untuk mengunduh file
        $downloadUrl = url($publicPath . '/' . $fileName);

        return new ResponseResource(true, "file diunduh", $downloadUrl);
    }

    public function export_product_expired(Request $request)
    {
        // Meningkatkan batas waktu eksekusi dan memori
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        // Membuat spreadsheet baru
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Menentukan headers berdasarkan nama kolom di tabel new_products
        $headers = [
            'ID',
            'Code Document',
            'Old Barcode Product',
            'New Barcode Product',
            'New Name Product',
            'New Quantity Product',
            'New Price Product',
            'Old Price Product',
            'New Date In Product',
            'New Status Product',
            'New Quality',
            'New Category Product',
            'New Tag Product',
            'Created At',
            'Updated At'
        ];

        // Menuliskan headers ke sheet
        $columnIndex = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($columnIndex, 1, $header);
            $columnIndex++;
        }

        // Variabel untuk melacak baris
        $rowIndex = 2;

        // Mengambil data dalam batch
        New_product::where('new_status_product', 'expired')
            ->chunk(1000, function ($products) use ($sheet, &$rowIndex) {
                foreach ($products as $product) {
                    $sheet->setCellValueByColumnAndRow(1, $rowIndex, $product->id);
                    $sheet->setCellValueByColumnAndRow(2, $rowIndex, $product->code_document);
                    $sheet->setCellValueByColumnAndRow(3, $rowIndex, $product->old_barcode_product);
                    $sheet->setCellValueByColumnAndRow(4, $rowIndex, $product->new_barcode_product);
                    $sheet->setCellValueByColumnAndRow(5, $rowIndex, $product->new_name_product);
                    $sheet->setCellValueByColumnAndRow(6, $rowIndex, $product->new_quantity_product);
                    $sheet->setCellValueByColumnAndRow(7, $rowIndex, $product->new_price_product);
                    $sheet->setCellValueByColumnAndRow(8, $rowIndex, $product->old_price_product);
                    $sheet->setCellValueByColumnAndRow(9, $rowIndex, $product->new_date_in_product);
                    $sheet->setCellValueByColumnAndRow(10, $rowIndex, $product->new_status_product);
                    $sheet->setCellValueByColumnAndRow(11, $rowIndex, $product->new_quality);
                    $sheet->setCellValueByColumnAndRow(12, $rowIndex, $product->new_category_product);
                    $sheet->setCellValueByColumnAndRow(13, $rowIndex, $product->new_tag_product);
                    $sheet->setCellValueByColumnAndRow(14, $rowIndex, $product->created_at);
                    $sheet->setCellValueByColumnAndRow(15, $rowIndex, $product->updated_at);
                    $rowIndex++;
                }
            });

        // Menyimpan file Excel
        $writer = new Xlsx($spreadsheet);
        $fileName = 'products_exp_export.xlsx';
        $publicPath = 'exports';
        $filePath = public_path($publicPath) . '/' . $fileName;

        // Membuat direktori exports jika belum ada
        if (!file_exists(public_path($publicPath))) {
            mkdir(public_path($publicPath), 0777, true);
        }

        $writer->save($filePath);

        // Mengembalikan URL untuk mengunduh file
        $downloadUrl = url($publicPath . '/' . $fileName);

        return new ResponseResource(true, "file diunduh", $downloadUrl);
    }

    public function addProductThirdParty(Request $request)
    {
        // Validasi Input
        $validator = Validator::make($request->all(), [
            'product_name' => 'required',
            'qty' => 'required',
            'product_price' => 'required|numeric',
            'category_id' => 'required|integer|exists:categories,id'
        ]);
    
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
    
        DB::beginTransaction();
    
        try {
            // Mengambil kategori berdasarkan ID
            $category = Category::findOrFail($request->input('category_id'));
    
            // Menghitung harga setelah diskon
            $discountPercentage = $category->discount_category;
            $productPrice = $request->input('product_price');
            $discountedPrice = $productPrice - ($productPrice * $discountPercentage / 100);
    
            // Persiapan data input untuk disimpan
            $inputData = [
                'old_price_product' => $productPrice,
                'new_name_product' => $request->input('product_name'),
                'new_quantity_product' => $request['qty'],
                'new_price_product' => $discountedPrice,
                'new_discount' => 0,
                'display_price' => $discountedPrice,
                'new_status_product' => 'display',
                'new_date_in_product' => Carbon::now('Asia/Jakarta')->toDateString(),
                'new_quality' => json_encode('lolos'),
                'new_category_product' => $category->name_category,
                'new_barcode_product' => generateNewBarcode($category->name_category),
                'new_tag_product' => null,
            ];
    
            // Membuat produk baru
            $newProduct = New_product::create($inputData);
    
            DB::commit();
    
            return new ResponseResource(true, "berhasil menambah data", $newProduct);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function addProductById($id)
    {
        DB::beginTransaction();
        try {
            $product = New_product::findOrFail($id);
            $product->new_barcode_product = generateNewBarcode($product->new_category_product);
            $productFilter = New_product::create($product->toArray());
            DB::commit();
            return new ResponseResource(true, "berhasil menambah product", $productFilter);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    
}
