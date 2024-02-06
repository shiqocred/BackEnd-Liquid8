<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Document;
use App\Models\Generate;
use App\Models\Product_old;
use App\Models\ResultMerge;
use App\Models\ResultFilter;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Http\Resources\ResponseResource;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateController extends Controller
{
    public function processExcelFiles(Request $request)
    {
        set_time_limit(300); // Extend max execution time
        ini_set('memory_limit', '512M'); // Increase memory limit
        // Mulai transaksi
        DB::beginTransaction();

        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls'
            ], [
                'file.unique' => 'Nama file sudah ada di database.',
            ]);

            $file = $request->file('file');
            $filePath = $file->getPathname();
            $fileName = $file->getClientOriginalName();

            // Simpan file yang diunggah
            $file->storeAs('public/ekspedisis', $file->hashName());

            // Mempersiapkan variabel
            $headers = [];
            $fileDetails = [];
            $templateHeaders = ["no_resi", "nama", "qty", "harga"];


            try {
                // Membuka file Excel
                $spreadsheet = IOFactory::load($filePath);
                $sheet = $spreadsheet->getActiveSheet();
                $header = [];
                $rowCount = 0;

                // Membaca header
                foreach ($sheet->getRowIterator(1, 1) as $row) {
                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(false);

                    foreach ($cellIterator as $cell) {
                        $value = $cell->getValue();
                        if (!is_null($value) && $value !== '') {
                            $header[] = $value;
                        }
                    }
                }

                $headers[$fileName] = $header;
                $columnCount = count($header);

                // Membaca baris data
                foreach ($sheet->getRowIterator(2) as $row) {
                    $rowData = [];
                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(false); // Pastikan untuk mengatur ini

                    foreach ($cellIterator as $cell) {
                        $value = $cell->getValue() ?? ''; // Gunakan nilai default jika sel kosong
                        $rowData[] = $value;
                    }

                    if (count($header) == count($rowData)) {
                        $jsonRowData = json_encode(array_combine($header, $rowData));
                        Generate::create(['data' => $jsonRowData]);
                        $rowCount++; // Pindahkan increment rowCount ke sini
                    } else {
                        Log::warning('Row data does not match header count', [
                            'HeaderCount' => count($header),
                            'RowDataCount' => count($rowData),
                            'RowData' => $rowData
                        ]);
                    }
                }

                // Memperbarui detail file
                $fileDetails = [
                    'total_column_count' => $columnCount,
                    'total_row_count' => $rowCount
                ];

                // Membuat dokumen baru
                $latestDocument = Document::latest()->first();
                $newId = $latestDocument ? $latestDocument->id + 1 : 1;
                $id_document = str_pad($newId, 4, '0', STR_PAD_LEFT);
                $month = date('m');
                $year = date('Y');
                $code_document = $id_document . '/' . $month . '/' . $year;

                Document::create([
                    'code_document' => $code_document,
                    'base_document' => $fileName,
                    'total_column_document' => $columnCount,
                    'total_column_in_document' => $rowCount,
                    'date_document' => Carbon::now('Asia/Jakarta')->toDateString()
                ]);

                // Commit transaksi
                DB::commit();

                // Mengembalikan response berhasil
                return new ResponseResource(true, "berhasil", [
                    'code_document' => $code_document,
                    'headers' => $headers,
                    'file_name' => $fileName,
                    'templateHeaders' => $templateHeaders,
                    'fileDetails' => $fileDetails
                ]);
            } catch (ReaderException $e) {
                // Melakukan rollback dan mencatat kesalahan
                DB::rollback();
                Log::error('Error processing file: ' . $e->getMessage());
                return response()->json(['error' => 'Error processing file: ' . $e->getMessage()], 500);
            }
        } catch (\Exception $e) {
            // Melakukan rollback dan mencatat kesalahan yang tidak terduga
            DB::rollback();
            Log::error('Unexpected error: ' . $e->getMessage());
            return response()->json(['error' => 'Unexpected error occurred.'], 500);
        }
    }

    public function mapAndMergeHeaders(Request $request)
    {
        set_time_limit(300); // Extend max execution time
        ini_set('memory_limit', '512M'); // Increase memory limit

        try {

            // Validasi input request
            $validator = Validator::make($request->all(), [
                'headerMappings' => 'required|array',
                'code_document' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $headerMappings = $request->input('headerMappings');

            $mergedData = [
                'old_barcode_product' => [],
                'old_name_product' => [],
                'old_quantity_product' => [],
                'old_price_product' => []
            ];

            $ekspedisiData = Generate::all()->map(function ($item) {
                return json_decode($item->data, true);
            });

            foreach ($headerMappings as $templateHeader => $selectedHeaders) {
                foreach ($selectedHeaders as $userSelectedHeader) {
                    $ekspedisiData->each(function ($dataItem) use ($userSelectedHeader, &$mergedData, $templateHeader) {
                        if (isset($dataItem[$userSelectedHeader])) {
                            array_push($mergedData[$templateHeader], $dataItem[$userSelectedHeader]);
                        }
                    });
                }
            }

            $dataToInsert = [];
            foreach ($mergedData['old_barcode_product'] as $index => $noResi) {
                $nama = $mergedData['old_name_product'][$index] ?? null;
                $qty = $mergedData['old_quantity_product'][$index] === '' ? null : (int)$mergedData['old_quantity_product'][$index];
                $harga = $mergedData['old_price_product'][$index] ?? null;

                $dataToInsert[] = [
                    'code_document' => $request['code_document'],
                    'old_barcode_product' => $noResi,
                    'old_name_product' => $nama,
                    'old_quantity_product' => $qty,
                    'old_price_product' => $harga,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            $chunkSize = 500; // Adjust based on your server capacity
            foreach (array_chunk($dataToInsert, $chunkSize) as $chunkIndex => $chunk) {
                Product_old::insert($chunk);
                Log::info("Inserted chunk {$chunkIndex} into product_olds", ['rows' => count($chunk)]);
            }

            Generate::query()->delete();
            Log::info('Deleted all records from generates table after merge.');

            // Return success response
            return new ResponseResource(true, "Berhasil menggabungkan data", ['inserted_rows' => count($dataToInsert)]);
        } catch (\Exception $e) {
            Log::error('Error in mapAndMergeHeaders: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
}
