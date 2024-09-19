<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use ProductsExport;
use App\Models\User;
use App\Models\Document;
use App\Models\ExcelOld;
use App\Models\New_product;
use App\Models\Notification;
use App\Models\RiwayatCheck;
use Illuminate\Http\Request;
use App\Models\FilterStaging;
use App\Models\ProductApprove;
use App\Models\StagingApprove;
use App\Models\StagingProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ProductStagingsExport;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Http\Resources\ResponseResource;
use Illuminate\Support\Facades\Validator;


class StagingProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $searchQuery = $request->input('q');
        $newProducts = StagingProduct::latest()
            ->where(function ($queryBuilder) use ($searchQuery) {
                $queryBuilder->where('old_barcode_product', 'LIKE', '%' . $searchQuery . '%')
                    ->orWhere('new_barcode_product', 'LIKE', '%' . $searchQuery . '%')
                    ->orWhere('new_category_product', 'LIKE', '%' . $searchQuery . '%')
                    ->orWhere('new_name_product', 'LIKE', '%' . $searchQuery . '%');
            })
            ->whereNotIn('new_status_product', ['dump', 'expired', 'sale', 'migrate', 'repair'])
            ->whereNull('new_tag_product')
            ->paginate(20);

        return new ResponseResource(true, "list new product", $newProducts);
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
        DB::beginTransaction();
        $userId = auth()->id();
        try {
            $product_filters = FilterStaging::where('user_id', $userId)->get();
            if ($product_filters->isEmpty()) {
                return new ResponseResource(false, "Tidak ada produk filter yang tersedia saat ini", $product_filters);
            }

            $insertData = $product_filters->map(function ($product) use ($userId) {
                return [
                    'code_document' => $product->code_document,
                    'old_barcode_product' => $product->old_barcode_product,
                    'new_barcode_product' => $product->new_barcode_product,
                    'new_name_product' => $product->new_name_product,
                    'new_quantity_product' => $product->new_quantity_product,
                    'new_price_product' => $product->new_price_product,
                    'old_price_product' => $product->old_price_product,
                    'new_date_in_product' => $product->new_date_in_product,
                    'new_status_product' =>  $product->new_status_product,
                    'new_quality' => $product->new_quality,
                    'new_category_product' => $product->new_category_product,
                    'new_tag_product' => $product->new_tag_product,
                    'new_discount' => $product->new_discount,
                    'display_price' => $product->display_price,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->toArray();

            Notification::create([
                'user_id' => $userId,
                'notification_name' => 'butuh approvemend untuk product staging',
                'role' => 'Spv',
                'read_at' => Carbon::now('Asia/Jakarta'),
                'riwayat_check_id' => null,
                'repair_id' => null,
                'status' => 'done'
            ]);

            FilterStaging::where('user_id', $userId)->delete();
            StagingApprove::insert($insertData);

            logUserAction($request, $request->user(), "storage/moving_product/create_bundle", "Create bundle");

            DB::commit();
            return new ResponseResource(true, "staging approve berhasil dibuat", null);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Gagal membuat bundle: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal memindahkan product ke approve', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(StagingProduct $stagingProduct)
    {
        return new ResponseResource(true, "data new product", $stagingProduct);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(StagingProduct $stagingProduct)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, StagingProduct $stagingProduct)
    {
        $validator = Validator::make($request->all(), [
            'code_document' => 'required',
            'old_barcode_product' => 'required',
            'new_barcode_product' => 'required',
            'new_name_product' => 'required',
            'new_quantity_product' => 'required|integer',
            'new_price_product' => 'required|numeric',
            'old_price_product' => 'required|numeric',
            'new_status_product' => 'required|in:display,expired,promo,bundle,palet,dump,sale,migrate',
            'condition' => 'required|in:lolos,damaged,abnormal',
            'new_category_product' => 'nullable',
            'new_tag_product' => 'nullable|exists:color_tags,name_color',
            'new_discount',
            'display_price'
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

        if ($status !== 'lolos') {
            // Set nilai-nilai default jika status bukan 'lolos'
            $inputData['new_price_product'] = null;
            $inputData['new_category_product'] = null;
        }

        $inputData['new_quality'] = json_encode($qualityData);

        $stagingProduct->update($inputData);

        return new ResponseResource(true, "New Produk Berhasil di Update", $stagingProduct);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(StagingProduct $stagingProduct)
    {
        //
    }

    public function addStagingToSpv(Request $request)
    {
        DB::beginTransaction();
        $user = auth()->user();
        try {
            $riwayat_check = RiwayatCheck::where('code_document', $request['code_document'])->first();
            if ($riwayat_check->status_approve == 'done') {
                $notif_count = Notification::where('riwayat_check_id', $riwayat_check->id)
                    ->where('status', 'staging')
                    ->count();
                if ($notif_count >= 1) {
                    $response = new ResponseResource(false, "Data sudah ada", null);
                    return $response->response()->setStatusCode(422);
                } else {
                    //keterangan transaksi
                    $keterangan = Notification::create([
                        'user_id' => $user->id,
                        'notification_name' => 'butuh approvemend untuk product staging',
                        'role' => 'Spv',
                        'read_at' => Carbon::now('Asia/Jakarta'),
                        'riwayat_check_id' => $riwayat_check->id,
                        'repair_id' => null,
                        'status' => 'staging'
                    ]);
                    $riwayat_check->update(['status_approve', 'staging']);
                    DB::commit();
                }
            }
            return new ResponseResource(true, "Data berhasil ditambah", [
                $riwayat_check,
                $keterangan
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            $resource = new ResponseResource(false, "Data gagal ditambahkan, terjadi kesalahan pada server : " . $e->getMessage(), null);
            $resource->response()->setStatusCode(500);
        }
    }

    public function documentsApproveStaging(Request $request)
    {
        $query = $request->input('q');

        // Mengambil data dari tabel notifications yang berkaitan dengan riwayat_check
        $notifQuery = Notification::with('riwayat_check')->where('status', 'staging')
            ->whereHas('riwayat_check', function ($q) use ($query) {
                if (!empty($query)) {
                    $q->where('status_approve', $query);
                } else {
                    $q->where('status_approve', 'done');
                }
            })
            ->latest();

        // Eksekusi query dan kembalikan hasilnya
        $notifications = $notifQuery->get();

        return new ResponseResource(true, "List of documents in staging", $notifications);
    }

    public function productStagingByDoc(Request $request, $code_document)
    {
        $query = $request->input('q');
        $user = User::with('role')->find(auth()->id());

        if ($user) {
            $productsQuery = StagingProduct::where('code_document', $code_document);

            if (!empty($query)) {
                $productsQuery->where('new_name_product', 'LIKE', '%' . $query . '%');
            }

            $products = $productsQuery->paginate(50);

            return new ResponseResource(true, 'products', $products);
        } else {
            return (new ResponseResource(false, "User tidak dikenali", null))->response()->setStatusCode(404);
        }
    }


    public function documentStagings(Request $request)
    {
        $query = $request->input('q');

        // Mengambil data notifikasi yang statusnya 'staging' dan menyertakan relasi 'riwayat_check'
        $notifQuery = Notification::with('riwayat_check')->where('status', 'staging')->latest();

        // Jika query tidak kosong, lakukan pencarian berdasarkan 'base_document' atau 'code_document'
        if (!empty($query)) {
            $notifQuery->whereHas('riwayat_check', function ($q) use ($query) {
                $q->where('base_document', $query)
                    ->orWhere('code_document', $query); // Memperbaiki typo dari 'cpde_document' menjadi 'code_document'
            });
        } else {
            // Jika tidak ada query, lakukan pencarian berdasarkan 'status_approve' dengan nilai 'pending' atau 'done'
            $notifQuery->whereHas('riwayat_check', function ($q) {
                $q->where('status_approve', 'pending')
                    ->orWhere('status_approve', 'done');
            });
        }

        // Ambil semua data yang sesuai
        $documents = $notifQuery->get();

        return new ResponseResource(true, "Document Approves", $documents);
    }


    //inject category staging -> staging
    public function processExcelFilesCategoryStaging(Request $request)
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
            $docs = Document::create([
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

            $history = RiwayatCheck::create([
                'user_id' => $user_id,
                'code_document' => $docs->code_document,
                'base_document' => $fileName,
                'total_data' => $docs->total_column_in_document,
                'total_data_in' => $docs->total_column_in_document,
                'total_data_lolos' => $docs->total_column_in_document,
                'total_data_damaged' => 0,
                'total_data_abnormal' => 0,
                'total_discrepancy' => 0,
                'status_approve' => 'staging',

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
                'status' => 'staging'
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

        $responseBarcode = collect();
        foreach ($mergedData['old_barcode_product'] as $index => $barcode) {
            $sources = [];

            if (StagingProduct::where('new_barcode_product', $barcode)->exists()) {
                $sources[] = 'Product-Staging';
            }

            if (New_product::where('new_barcode_product', $barcode)->exists()) {
                $sources[] = 'Product-Inventory';
            }

            if (StagingApprove::where('new_barcode_product', $barcode)->exists()) {
                $sources[] = 'Staging-Approve';
            }

            if (FilterStaging::where('new_barcode_product', $barcode)->exists()) {
                $sources[] = 'Filter-Staging';
            }

            if (!empty($sources)) {
                $responseBarcode->push($barcode . ' - ' . implode(', ', $sources));
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
                'new_price_product' => isset($mergedData['new_price_product'][$index]) && $mergedData['new_price_product'][$index] !== '' ? str_replace(',', '.', $mergedData['new_price_product'][$index]) : 0.00,
                'old_price_product' => isset($mergedData['old_price_product'][$index]) && $mergedData['old_price_product'][$index] !== '' ? str_replace(',', '.', $mergedData['old_price_product'][$index]) : 0.00,
                'new_date_in_product' => $mergedData['new_date_in_product'][$index] ?? Carbon::now('Asia/Jakarta')->toDateString(),
                'new_quality' => $mergedData['new_quality'][$index],
                'new_discount' => 0,
                'display_price' => isset($mergedData['display_price'][$index]) && $mergedData['display_price'][$index] !== '' ? str_replace(',', '.', $mergedData['display_price'][$index]) : 0.00,

            ];

            StagingProduct::create($newProductData);
        }

        ExcelOld::query()->delete();

        Log::info('Merged data prepared for response', ['mergedData' => $mergedData]);

        return new ResponseResource(true, "Data berhasil digabungkan dan disimpan.", null);
    }

    public function partial($code_document)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        try {
            $document = Document::where('code_document', $code_document)->first();
            if ($document) {

                $productApprovesTags = ProductApprove::where('code_document', $code_document)
                    ->whereNotNull('new_tag_product')
                    ->get();

                $productApprovesCategories = ProductApprove::where('code_document', $code_document)
                    ->whereNull('new_tag_product')
                    ->get();

                DB::beginTransaction();

                $this->processProductApproves($productApprovesTags, New_product::class, 100);
                $this->processProductApproves($productApprovesCategories, StagingProduct::class, 200);

                $total = count($productApprovesTags) + count($productApprovesCategories);

                DB::commit();
                return new ResponseResource(true, "Berhasil ke staging", $total);
            } else {
                return new ResponseResource(false, "Code document tidak ada", $code_document);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return new ResponseResource(false, "Gagal mengapprove transaksi", $e->getMessage());
        }
    }

    private function processProductApproves($productApproves, $modelClass, $chunkSize)
    {
        $productApproves->chunk($chunkSize)->each(function ($chunk) use ($modelClass) {
            $dataToInsert = [];

            foreach ($chunk as $productApprove) {
                $dataToInsert[] = [
                    'code_document' => $productApprove->code_document,
                    'old_barcode_product' => $productApprove->old_barcode_product,
                    'new_barcode_product' => $productApprove->new_barcode_product,
                    'new_name_product' => $productApprove->new_name_product,
                    'new_quantity_product' => $productApprove->new_quantity_product,
                    'new_price_product' => $productApprove->new_price_product,
                    'old_price_product' => $productApprove->old_price_product,
                    'new_date_in_product' => Carbon::now('Asia/Jakarta')->toDateString(),
                    'new_status_product' => $productApprove->new_status_product,
                    'new_quality' => $productApprove->new_quality,
                    'new_category_product' => $productApprove->new_category_product,
                    'new_tag_product' => $productApprove->new_tag_product,
                    'new_discount' => $productApprove->new_discount,
                    'display_price' => $productApprove->display_price,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $modelClass::insert($dataToInsert);

            ProductApprove::destroy($chunk->pluck('id'));
        });
    }

    public function export()
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');
    
        try {
            $fileName = 'product-staging.xlsx';
            $publicPath = 'exports';
            $filePath = storage_path('app/public/' . $publicPath . '/' . $fileName);
    
            // Buat direktori jika belum ada
            if (!file_exists(dirname($filePath))) {
                mkdir(dirname($filePath), 0777, true);
            }
    
            Excel::store(new ProductStagingsExport, $publicPath . '/' . $fileName, 'public');
    
            // URL download menggunakan public_path
            $downloadUrl = asset('storage/' . $publicPath . '/' . $fileName);
    
            return new ResponseResource(true, "File berhasil diunduh", $downloadUrl);
        } catch (\Exception $e) {
            return new ResponseResource(false, "Gagal mengunduh file: " . $e->getMessage(), []);
        }
    }
    
}
