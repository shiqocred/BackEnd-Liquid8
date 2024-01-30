<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Document;
use App\Models\New_product;
use Illuminate\Http\Request;
use App\Models\ListProductBP;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Http\Resources\ResponseResource;
use App\Models\ExcelOld;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

class NewProductController extends Controller
{

    public function index()
    {
        $newProducts = New_product::latest()->paginate(50);

        return new ResponseResource(true, "list new product", $newProducts);
    }


    public function create()
    {
    }

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
            'new_status_product' => 'required|in:display,expired,promo,bundle,palet',
            'condition' => 'required|in:lolos,damaged,abnormal',
            'new_category_product' => 'nullable|exists:categories,name_category',
            'new_tag_product' => 'nullable|exists:color_tags,name_color'
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
            'new_tag_product'
        ]);

        // Set zona waktu ke Indonesia/Jakarta
        $indonesiaTime = Carbon::now('Asia/Jakarta');
        $inputData['new_date_in_product'] = $indonesiaTime->toDateString();

        if ($status !== 'lolos') {
            $inputData['new_category_product'] = null;
            $inputData['new_price_product'] = null;
        }

        $inputData['new_quality'] = json_encode($qualityData);

        $newProduct = New_product::create($inputData);

        //update status document
        $code_document = Document::where('code_document', $request['code_document'])->first();

        if ($code_document->status_document == 'pending') {
            $code_document->update(['status_document' => 'in progress']);
        }

        return new ResponseResource(true, "New Produk Berhasil ditambah", $newProduct);
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
            'code_document' => 'required',
            'old_barcode_product' => 'required',
            'new_barcode_product' => 'required',
            'new_name_product' => 'required',
            'new_quantity_product' => 'required|integer',
            'new_price_product' => 'required|numeric',
            'old_price_product' => 'required|numeric',
            'new_status_product' => 'required|in:display,expired,promo,bundle,palet',
            'condition' => 'required|in:lolos,damaged,abnormal',
            'new_category_product' => 'nullable|exists:categories,name_category',
            'new_tag_product' => 'nullable|exists:color_tags,name_color'
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
            'new_tag_product'
        ]);

        $indonesiaTime = Carbon::now('Asia/Jakarta');
        $inputData['new_date_in_product'] = $indonesiaTime->toDateString();

        if ($status !== 'lolos') {
            // Set nilai-nilai default jika status bukan 'lolos'
            $inputData['new_price_product'] = null;
            $inputData['new_category_product'] = null;
        }

        $inputData['new_quality'] = json_encode($qualityData);


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



    public function listProductExp()
    {
        try {
            $productExpired = New_product::where('new_status_product', 'expired')->paginate(50);
            return new ResponseResource(true, "list product expired", $productExpired);
        } catch (\Exception $e) {
            return response()->json(["error" => $e]);
        }
    }


    public function processExcelFiles(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls'
        ]);

        $file = $request->file('file');

        $filePath = $file->getPathname();
        $fileName = $file->getClientOriginalName();
        $file->storeAs('public/ekspedisis', $fileName);

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();

            // Mengambil header dari baris pertama
            $header = $sheet->rangeToArray('A1:' . $sheet->getHighestColumn() . '1', NULL, TRUE, FALSE, TRUE)[1];

            // Inisialisasi rowCount dan array untuk menyimpan data yang akan diinsert
            $rowCount = 0;
            $dataToInsert = [];

            // Mulai iterasi dari baris kedua
            foreach ($sheet->getRowIterator(2) as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(FALSE);

                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $rowData[] = $cell->getValue();
                }

                // Pastikan jumlah header sama dengan jumlah data di rowData
                if (count($header) === count($rowData)) {
                    $jsonRowData = json_encode(array_combine($header, $rowData));
                    ExcelOld::create(['data' => $jsonRowData]);
                    $rowCount++;
                }
            }

            // Buat dokumen baru
            $latestDocument = Document::latest()->first();
            $newId = $latestDocument ? $latestDocument->id + 1 : 1;
            $id_document = str_pad($newId, 4, '0', STR_PAD_LEFT);
            $month = date('m');
            $year = date('Y');
            $code_document = $id_document . '/' . $month . '/' . $year;

            Document::create([
                'code_document' => $code_document,
                'base_document' => $fileName,
                'total_column_document' => count($header),
                'total_column_in_document' => $rowCount,
            ]);


            $mergeResponse = $this->mapAndMergeHeaders();
            // Return response
            return new ResponseResource(true, "Data berhasil diproses dan disimpan", [
                'code_document' => $code_document,
                'file_name' => $fileName,
                'total_column_count' => count($header),
                'total_row_count' => $rowCount,
                'merged' => $mergeResponse
            ]);
        } catch (ReaderException $e) {
            return back()->with('error', 'Error processing file: ' . $e->getMessage());
        }
    }

    protected function mapAndMergeHeaders()
    {
        $headerMappings = [
            'old_barcode_product' => ['Barcode'],
            'new_barcode_product' => ['Barcode'],
            'new_name_product' => ['Description'],
            'new_category_product' => ['Category'],
            'new_quantity_product' => ['Qty'],
            'new_price_product' => ['Price After Discount'],
            'old_price_product' => ['Unit Price'],
            'new_date_in_product' => ['Date'], // Asumsikan 'Date' adalah nama kolom yang sesuai dalam Excel Anda
        ];

        // Mengambil kode dokumen terakhir
        $latestDocument = Document::latest()->first();
        if (!$latestDocument) {
            return response()->json(['error' => 'No documents found.'], 404);
        }
        $code_document = $latestDocument->code_document;

        // Mengambil semua data dari model ExcelOld dan mengubahnya menjadi array
        $ekspedisiData = ExcelOld::all()->map(function ($item) {
            return json_decode($item->data, true);
        });

        // Inisialisasi array untuk menyimpan data yang akan digabungkan
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
        ];

        // Memetakan dan menggabungkan data berdasarkan headerMappings
        foreach ($ekspedisiData as $dataItem) {
            foreach ($headerMappings as $templateHeader => $selectedHeaders) {
                foreach ($selectedHeaders as $userSelectedHeader) {
                    if (isset($dataItem[$userSelectedHeader])) {
                        $mergedData[$templateHeader][] = $dataItem[$userSelectedHeader];
                    }
                }
            }

            // Misalkan kita menambahkan 'new_quality' berdasarkan kondisi tertentu dari $dataItem
            $status = $dataItem['Status'] ?? 'unknown'; // Ganti 'Status' dengan nama kolom yang sesuai
            $description = $dataItem['Description'] ?? ''; // Gunakan deskripsi atau kolom lain yang relevan

            $qualityData = [
                'lolos' => $status === 'lolos' ? true : null,
                'damaged' => $status === 'damaged' ? $description : null,
                'abnormal' => $status === 'abnormal' ? $description : null,
            ];

            $mergedData['new_quality'][] = json_encode($qualityData);
        }

        // Menyimpan data yang digabungkan ke dalam model New_product
        foreach ($mergedData['old_barcode_product'] as $index => $barcode) {
            $newProductData = [
                'code_document' => $code_document,
                'old_barcode_product' => $barcode,
                'new_barcode_product' => $mergedData['new_barcode_product'][$index] ?? null,
                'new_name_product' => $mergedData['new_name_product'][$index] ?? null,
                'new_category_product' => $mergedData['new_category_product'][$index] ?? null,
                'new_quantity_product' => $mergedData['new_quantity_product'][$index] ?? null,
                'new_price_product' => $mergedData['new_price_product'][$index] ?? null,
                'old_price_product' => $mergedData['old_price_product'][$index] ?? null,
                'new_date_in_product' => $mergedData['new_date_in_product'][$index] ?? Carbon::now('Asia/Jakarta')->toDateString(),
                'new_quality' => $mergedData['new_quality'][$index] ?? null, // Pastikan ini adalah JSON yang valid
            ];

            New_product::create($newProductData);
        }

            ExcelOld::query()->delete();

        return new ResponseResource(true, "Data berhasil digabungkan dan disimpan.", null);
    }

    public function showRepair(Request $request)
    {
        try {
            $query = $request->get('q');
    
            $products = New_product::query()
                ->where(function ($queryBuilder) use ($query) {
                    $queryBuilder
                        ->whereRaw('json_extract(new_quality, "$.damaged") is not null and json_extract(new_quality, "$.damaged") != "null"')
                        ->orWhereRaw('json_extract(new_quality, "$.abnormal") is not null and json_extract(new_quality, "$.abnormal") != "null"');
    
                    if ($query) {
                        $queryBuilder
                            ->where('old_barcode_product', 'like', '%' . $query . '%')
                            ->orWhere('new_barcode_product', 'like', '%' . $query . '%')
                            ->orWhere('new_name_product', 'like', '%' . $query . '%');
                    }
                })
                ->paginate(50);
    
            if ($products->isEmpty()) {
                return new ResponseResource(false, "Tidak ada data", null);
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


            if (isset($quality['lolos']) ) {
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
}
