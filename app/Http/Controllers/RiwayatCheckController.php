<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Mail\TestEmail;
use App\Models\Document;
use App\Models\New_product;
use App\Models\Product_old;
use App\Models\Notification;
use App\Models\RiwayatCheck;
use Illuminate\Http\Request;
use App\Models\ProductApprove;
use App\Models\StagingProduct;
use App\Mail\AdminNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\ResponseResource;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RiwayatCheckController extends Controller
{

    public function index(Request $request)
    {
        $query = $request->input('q');

        $riwayats = RiwayatCheck::latest()->where(function ($search) use ($query) {
            $search->where('code_document', 'LIKE', '%' . $query . '%')
                ->orWhere('base_document', 'LIKE', '%' . $query . '%');
        })->paginate(50);
        return new ResponseResource(true, "list riwayat", $riwayats);
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        $user = User::find(auth()->id());

        if (!$user) {
            $resource = new ResponseResource(false, "User tidak dikenali", null);
            return $resource->response()->setStatusCode(422);
        }

        $validator = Validator::make($request->all(), [
            'code_document' => 'required|unique:riwayat_checks,code_document',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $document = Document::where('code_document', $request['code_document'])->firstOrFail();

        if ($document->total_column_in_document == 0) {
            return response()->json(['error' => 'Total data di document tidak boleh 0'], 422);
        }

        DB::beginTransaction();

        try {
            $totalLolos = $totalDamaged = $totalAbnormal = 0;
            $totalData = 0;

            // Proses data dengan chunking untuk menghindari penggunaan memori yang tinggi
            ProductApprove::where('code_document', $request['code_document'])
                ->chunk(100, function ($products) use (&$totalLolos, &$totalDamaged, &$totalAbnormal, &$totalData) {
                    foreach ($products as $product) {
                        $newQualityData = json_decode($product->new_quality, true);

                        if (is_array($newQualityData)) {
                            $totalLolos += !empty($newQualityData['lolos']) ? 1 : 0;
                            $totalDamaged += !empty($newQualityData['damaged']) ? 1 : 0;
                            $totalAbnormal += !empty($newQualityData['abnormal']) ? 1 : 0;
                        }
                    }
                    $totalData += count($products);
                });

            // Menghitung harga produk dengan chunking
            $priceProductOld = Product_old::where('code_document', $request['code_document'])
                ->sum('old_price_product');

            $priceProductApprove = ProductApprove::where('code_document', $request['code_document'])
                ->sum('old_price_product');

            $totalPrice = $priceProductOld + $priceProductApprove;

            $productDiscrepancy = Product_old::where('code_document', $request['code_document'])
                ->count();

            $riwayat_check = RiwayatCheck::create([
                'user_id' => $user->id,
                'code_document' => $request['code_document'],
                'base_document' => $document->base_document,
                'total_data' => $document->total_column_in_document,
                'total_data_in' => $totalData,
                'total_data_lolos' => $totalLolos,
                'total_data_damaged' => $totalDamaged,
                'total_data_abnormal' => $totalAbnormal,
                'total_discrepancy' => $document->total_column_in_document - $totalData,
                'status_approve' => 'pending',

                // persentase
                'precentage_total_data' => ($document->total_column_in_document / $document->total_column_in_document) * 100,
                'percentage_in' => ($totalData / $document->total_column_in_document) * 100,
                'percentage_lolos' => ($totalLolos / $document->total_column_in_document) * 100,
                'percentage_damaged' => ($totalDamaged / $document->total_column_in_document) * 100,
                'percentage_abnormal' => ($totalAbnormal / $document->total_column_in_document) * 100,
                'percentage_discrepancy' => ($productDiscrepancy / $document->total_column_in_document) * 100,
                'total_price' => $totalPrice
            ]);

            $code_document = Document::where('code_document', $request['code_document'])->first();
            $code_document->update(['status_document' => 'in progress']);

            $keterangan = Notification::create([
                'user_id' => $user->id,
                'notification_name' => 'Butuh approvement untuk List Product',
                'role' => 'Spv',
                'read_at' => Carbon::now('Asia/Jakarta'),
                'riwayat_check_id' => $riwayat_check->id,
                'repair_id' => null
            ]);

            logUserAction($request, $request->user(), "inbound/check_product/multi_check", "Done check all");

            DB::commit();

            return new ResponseResource(true, "Data berhasil ditambah", [
                $riwayat_check,
                $keterangan
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            $resource = new ResponseResource(false, "Data gagal ditambahkan, terjadi kesalahan pada server : " . $e->getMessage(), null);
            return $resource->response()->setStatusCode(500);
        }
    }


    public function show(RiwayatCheck $history)
    {
        // Gunakan cursor untuk mengambil produk satu per satu
        $getProduct = New_product::where('code_document', $history->code_document)->cursor();
        $productCategoryCount = $getProduct->filter(function ($product) {
            return $product->new_category_product !== null;
        })->count();

        $productColorCount = $getProduct->filter(function ($product) {
            return $product->new_tag_product !== null;
        })->count();

        // Proses produk yang rusak (damaged) menggunakan chunk
        $totalOldPriceDamaged = 0;
        $getProductDamaged = [];
        New_product::where('code_document', $history->code_document)
            ->where('new_quality->damaged', '!=', null)
            ->chunk(1000, function ($products) use (&$getProductDamaged, &$totalOldPriceDamaged) {
                foreach ($products as $product) {
                    $product->damaged_value = json_decode($product->new_quality)->damaged ?? null;
                    $getProductDamaged[] = $product;
                    $totalOldPriceDamaged += $product->old_price_product;
                }
            });

        $totalPercentageDamaged = ($totalOldPriceDamaged / $history->total_price) * 100;
        $totalPercentageDamaged = round($totalPercentageDamaged, 2);

        // Proses produk lolos (lolos) menggunakan chunk
        $totalOldPriceLolos = 0;
        $getProductLolos = [];
        New_product::where('code_document', $history->code_document)
            ->where('new_quality->lolos', '!=', null)
            ->chunk(1000, function ($products) use (&$getProductLolos, &$totalOldPriceLolos) {
                foreach ($products as $product) {
                    $product->lolos_value = json_decode($product->new_quality)->lolos ?? null;
                    $getProductLolos[] = $product;
                    $totalOldPriceLolos += $product->old_price_product;
                }
            });

        $totalPercentageLolos = ($totalOldPriceLolos / $history->total_price) * 100;
        $totalPercentageLolos = round($totalPercentageLolos, 2);

        // Proses produk abnormal (abnormal) menggunakan chunk
        $totalOldPriceAbnormal = 0;
        $getProductAbnormal = [];
        New_product::where('code_document', $history->code_document)
            ->where('new_quality->abnormal', '!=', null)
            ->chunk(1000, function ($products) use (&$getProductAbnormal, &$totalOldPriceAbnormal) {
                foreach ($products as $product) {
                    $product->abnormal_value = json_decode($product->new_quality)->abnormal ?? null;
                    $getProductAbnormal[] = $product;
                    $totalOldPriceAbnormal += $product->old_price_product;
                }
            });

        $totalPercentageAbnormal = ($totalOldPriceAbnormal / $history->total_price) * 100;
        $totalPercentageAbnormal = round($totalPercentageAbnormal, 2);

        // Proses staging products dengan cursor
        $stagingProducts = StagingProduct::where('code_document', $history->code_document)->cursor();
        $totalOldPricestaging = 0;
        foreach ($stagingProducts as $product) {
            $totalOldPricestaging += $product->old_price_product;
        }

        $totalPercentageStaging = ($totalOldPricestaging / $history->total_price) * 100;
        $totalPercentageStaging = round($totalPercentageStaging, 2);

        // Proses product discrepancy dengan cursor
        $getProductDiscrepancy = Product_old::where('code_document', $history->code_document)->cursor();
        $totalPriceDiscrepancy = 0;
        foreach ($getProductDiscrepancy as $product) {
            $totalPriceDiscrepancy += $product->old_price_product;
        }

        $totalPercentageDiscrepancy = ($totalPriceDiscrepancy / $history->total_price) * 100;
        $totalPercentageDiscrepancy = round($totalPercentageDiscrepancy, 2);

        // Response
        $response = new ResponseResource(true, "Riwayat Check", [
            'id' => $history->id,
            'user_id' => $history->user_id,
            'code_document' => $history->code_document,
            'base_document' => $history->base_document,
            'stagingProducts' => count($stagingProducts),
            'total_product_category' => $productCategoryCount,
            'total_product_color' => $productColorCount,
            'total_data' => $history->total_data,
            'total_data_in' => $history->total_data_in,
            'total_data_lolos' => $history->total_data_lolos,
            'total_data_damaged' => $history->total_data_damaged,
            'total_data_abnormal' => $history->total_data_abnormal,
            'total_discrepancy' => $history->total_discrepancy,
            'status_approve' => $history->status_approve,
            'precentage_total_data' => $history->precentage_total_data,
            'percentage_in' => $history->percentage_in,
            'percentage_lolos' => $history->percentage_lolos,
            'percentage_damaged' => $history->percentage_damaged,
            'percentage_abnormal' => $history->percentage_abnormal,
            'percentage_discrepancy' => $history->percentage_discrepancy,
            'total_price' => $history->total_price,
            'created_at' => $history->created_at,
            'updated_at' => $history->updated_at,
            'damaged' => [
                'products' => $getProductDamaged,
                'total_old_price' => $totalOldPriceDamaged,
                'price_percentage' => $totalPercentageDamaged,
            ],
            'lolos' => [
                'products' => $getProductLolos,
                'total_old_price' => $totalOldPriceLolos,
                'price_percentage' => $totalPercentageLolos,
            ],
            'abnormal' => [
                'products' => $getProductAbnormal,
                'total_old_price' => $totalOldPriceAbnormal,
                'price_percentage' => $totalPercentageAbnormal,
            ],
            'staging' => [
                'products' => $stagingProducts,
                'total_old_price' => $totalOldPricestaging,
                'price_percentage' => $totalPercentageStaging,
            ],
            'priceDiscrepancy' =>  $totalPriceDiscrepancy,
            'price_percentage' => $totalPercentageDiscrepancy,
        ]);

        return $response->response();
    }


    public function getByDocument(Request $request)
    {
        $codeDocument = RiwayatCheck::where('code_document', $request['code_document']);
        return new ResponseResource(true, "Riwayat Check", $codeDocument);
    }


    public function edit(RiwayatCheck $riwayatCheck)
    {
        //
    }


    public function update(Request $request, RiwayatCheck $riwayatCheck) {}

    public function destroy(RiwayatCheck $history)
    {
        DB::beginTransaction();
        try {
            Notification::where('riwayat_check_id', $history->id)->delete();
            $history->delete();
            DB::commit();
            return new ResponseResource(true, 'data berhasil di hapus', $history);
        } catch (\Exception $e) {
            DB::rollBack();
            return new ResponseResource(false, 'data gagal di hapus', $e->getMessage());
        }
    }

    public function exportToExcel(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');
        $code_document = $request->input('code_document');

        // Mengambil history secara efisien
        $getHistory = RiwayatCheck::where('code_document', $code_document)->first();

        // Menggunakan chunk untuk mengambil Product_old dalam batch
        $totalOldPriceDiscrepancy = 0;
        $getProductDiscrepancy = [];
        Product_old::where('code_document', $code_document)
            ->chunk(2000, function ($products) use (&$getProductDiscrepancy, &$totalOldPriceDiscrepancy) {
                foreach ($products as $product) {
                    $getProductDiscrepancy[] = $product;
                    $totalOldPriceDiscrepancy += $product->old_price_product;
                }
            });

        $price_persentage_dp = ($totalOldPriceDiscrepancy / $getHistory->total_price) * 100;
        $price_persentage_dp = round($price_persentage_dp, 2);

        // Menggunakan chunk untuk pengambilan data "damaged"
        $getProductDamaged = [];
        $totalOldPriceDamaged = 0;
        New_product::where('code_document', $code_document)
            ->where('new_quality->damaged', '!=', null)
            ->chunk(2000, function ($products) use (&$getProductDamaged, &$totalOldPriceDamaged) {
                foreach ($products as $product) {
                    $product->damaged_value = json_decode($product->new_quality)->damaged ?? null;
                    $getProductDamaged[] = $product;
                    $totalOldPriceDamaged += $product->old_price_product;
                }
            });

        $price_persentage_damaged = ($totalOldPriceDamaged / $getHistory->total_price) * 100;
        $price_persentage_damaged = round($price_persentage_damaged, 2);

        // Menggunakan chunk untuk pengambilan data "lolos"
        $getProductLolos = [];
        $totalOldPriceLolos = 0;
        New_product::where('code_document', $code_document)
            ->where('new_quality->lolos', '!=', null)
            ->chunk(2000, function ($products) use (&$getProductLolos, &$totalOldPriceLolos) {
                foreach ($products as $product) {
                    $product->lolos_value = json_decode($product->new_quality)->lolos ?? null;
                    $getProductLolos[] = $product;
                    $totalOldPriceLolos += $product->old_price_product;
                }
            });

        $price_persentage_lolos = ($totalOldPriceLolos / $getHistory->total_price) * 100;
        $price_persentage_lolos = round($price_persentage_lolos, 2);

        // Menggunakan chunk untuk pengambilan data "abnormal"
        $getProductAbnormal = [];
        $totalOldPriceAbnormal = 0;
        New_product::where('code_document', $code_document)
            ->where('new_quality->abnormal', '!=', null)
            ->chunk(2000, function ($products) use (&$getProductAbnormal, &$totalOldPriceAbnormal) {
                foreach ($products as $product) {
                    $product->abnormal_value = json_decode($product->new_quality)->abnormal ?? null;
                    $getProductAbnormal[] = $product;
                    $totalOldPriceAbnormal += $product->old_price_product;
                }
            });

        $price_persentage_abnormal = ($totalOldPriceAbnormal / $getHistory->total_price) * 100;
        $price_persentage_abnormal = round($price_persentage_abnormal, 2);

        // Menggunakan chunk untuk pengambilan data "staging"
        $getProductStagings = [];
        $totalOldPriceStaging = 0;
        StagingProduct::where('code_document', $code_document)
            ->chunk(2000, function ($products) use (&$getProductStagings, &$totalOldPriceStaging) {
                foreach ($products as $product) {
                    $product->lolos_value = json_decode($product->new_quality)->lolos ?? null;
                    $getProductStagings[] = $product;
                    $totalOldPriceStaging += $product->old_price_product;
                }
            });

        $price_persentage_staging = ($totalOldPriceStaging / $getHistory->total_price) * 100;
        $price_persentage_staging = round($price_persentage_staging, 2);

        // Validasi jika data kosong
        $checkHistory = RiwayatCheck::where('code_document', $code_document)->get();
        if ($checkHistory->isEmpty()) {
            return response()->json(['status' => false, 'message' => "Data kosong, tidak bisa di export"], 422);
        }

        // Proses pembuatan file Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set data ke lembar Excel
        $headers = [
            'ID',
            'User ID',
            'Code Document',
            'Base Document',
            'Total Data',
            'Total Data In',
            'Total Data Lolos',
            'Total Data Damaged',
            'Total Data Abnormal',
            'Total Discrepancy',
            'Status Approve',
            'Percentage Total Data',
            'Percentage In',
            'Percentage Lolos',
            'Percentage Damaged',
            'Percentage Abnormal',
            'Percentage Discrepancy',
            'Total Price'
        ];

        $currentRow = 1;
        foreach ($checkHistory as $riwayatCheck) {
            foreach ($headers as $index => $header) {
                $columnName = strtolower(str_replace(' ', '_', $header));
                $cellValue = $riwayatCheck->$columnName;
                // Set header
                $sheet->setCellValueByColumnAndRow(1, $currentRow, $header);
                // Set value
                $sheet->setCellValueByColumnAndRow(2, $currentRow, $cellValue);
                $currentRow++;
            }
            $currentRow++;
        }

        // Buat file Excel untuk setiap kategori produk
        $this->createExcelSheet($spreadsheet, 'Damaged', $getProductDamaged, $totalOldPriceDamaged, $price_persentage_damaged);
        $this->createExcelSheet($spreadsheet, 'Lolos', $getProductLolos, $totalOldPriceLolos, $price_persentage_lolos);
        $this->createExcelSheet($spreadsheet, 'Abnormal', $getProductAbnormal, $totalOldPriceAbnormal, $price_persentage_abnormal);
        $this->createExcelSheet($spreadsheet, 'Staging', $getProductStagings, $totalOldPriceStaging, $price_persentage_staging);
        $this->createExcelSheetDiscrepancy($spreadsheet, 'Discrepancy', $getProductDiscrepancy, $totalOldPriceDiscrepancy, $price_persentage_dp);

        $firstItem = $checkHistory->first();

        $writer = new Xlsx($spreadsheet);
        $fileName = $firstItem->base_document;
        $publicPath = 'exports';
        $filePath = public_path($publicPath) . '/' . $fileName;

        if (!file_exists(public_path($publicPath))) {
            mkdir(public_path($publicPath), 0777, true);
        }

        $writer->save($filePath);

        $downloadUrl = url($publicPath . '/' . $fileName);

        return new ResponseResource(true, "File siap diunduh.", $downloadUrl);
    }

    private function createExcelSheet($spreadsheet, $title, $data, $totalOldPrice, $pricePercentage)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($title);

        // Menetapkan header
        $headers = [
            'Code Document',
            'Old Barcode',
            'New Barcode',
            'Name Product',
            'Keterangan',
            'Qty',
            'Unit Price',
            'Category',
            'Diskon',
            'After Diskon',
            'Price Percentage'
        ];

        // Menulis header langsung ke lembar kerja
        $sheet->fromArray($headers, null, 'A1');

        // Memproses data dan menyiapkan array untuk dimasukkan ke Excel
        $dataArray = [];
        foreach ($data as $item) {
            $diskon = 0;
            if ($item->old_price_product != 0) {
                $diskon = (($item->old_price_product - $item->new_price_product) / $item->old_price_product) * 100;
            }

            $keterangan = $item->lolos_value ?? $item->damaged_value ?? $item->abnormal_value ?? 'null';

            // Menambahkan data ke array
            $dataArray[] = [
                $item->code_document ?? 'null',
                $item->old_barcode_product ?? 'null',
                $item->new_barcode_product ?? 'null',
                $item->new_name_product ?? 'null',
                $keterangan,
                $item->new_quantity_product ?? 'null',
                $item->old_price_product ?? 'null',
                $item->new_category_product ?? 'null',
                $diskon ?? 'null',
                $item->new_price_product ?? 'null',
                $pricePercentage
            ];
        }

        // Menulis data dalam bentuk array ke lembar Excel mulai dari baris ke-2
        $sheet->fromArray($dataArray, null, 'A2');

        // Menambahkan total dan persentase di bagian akhir
        $totalRow = count($dataArray) + 2; // Baris setelah data
        $sheet->setCellValue("A{$totalRow}", 'Total Price');
        $sheet->setCellValue("B{$totalRow}", $totalOldPrice);
        $sheet->setCellValue("C{$totalRow}", 'Price Percentage');
        $sheet->setCellValue("D{$totalRow}", $pricePercentage);
    }

    private function createExcelSheetDiscrepancy($spreadsheet, $title, $data, $totalOldPrice, $pricePercentage)
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($title);

        // Menetapkan header
        $headers = [
            'Code Document',
            'Old Barcode',
            'Name Product',
            'Qty',
            'Unit Price',
        ];

        // Menulis header langsung ke lembar kerja
        $sheet->fromArray($headers, null, 'A1');

        // Memproses data dan menyiapkan array untuk dimasukkan ke Excel
        $dataArray = [];
        foreach ($data as $item) {
            $diskon = 0;
            if ($item->old_price_product != 0) {
                $diskon = (($item->old_price_product - $item->new_price_product) / $item->old_price_product) * 100;
            }

            $keterangan = $item->lolos_value ?? $item->damaged_value ?? $item->abnormal_value ?? 'null';

            // Menambahkan data ke array
            $dataArray[] = [
                $item->code_document ?? 'null',
                $item->old_barcode_product ?? 'null',
                $item->old_name_product ?? 'null',
                $item->old_quantity_product ?? 'null',
                $item->old_price_product ?? 'null',
 
            ];
        }

        // Menulis data dalam bentuk array ke lembar Excel mulai dari baris ke-2
        $sheet->fromArray($dataArray, null, 'A2');

        // Menambahkan total dan persentase di bagian akhir
        $totalRow = count($dataArray) + 2; // Baris setelah data
        $sheet->setCellValue("A{$totalRow}", 'Total Price');
        $sheet->setCellValue("B{$totalRow}", $totalOldPrice);
   
    }

  
}
