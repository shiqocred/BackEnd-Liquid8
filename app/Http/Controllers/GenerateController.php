<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Document;
use App\Models\Generate;
use App\Models\Product_old;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Http\Resources\ResponseResource;
use App\Models\Bundle;
use App\Models\New_product;
use App\Models\Palet;
use App\Models\PaletProduct;
use App\Models\Product_Bundle;
use App\Models\Promo;
use App\Models\Repair;
use App\Models\RepairProduct;
use App\Models\RiwayatCheck;
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
    
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ], [
            'file.unique' => 'Nama file sudah ada di database.',
        ]);
    
        $file = $request->file('file');
        $filePath = $file->getPathname();
        $fileName = $file->getClientOriginalName();
        $file->storeAs('public/ekspedisis', $file->hashName());
    
        DB::beginTransaction(); // Start transaction
    
        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $header = $this->getHeadersFromSheet($sheet);
            $rowCount = $this->processRowsFromSheet($sheet, $header);
    
            $code_document = $this->createDocumentEntry($fileName, count($header), $rowCount);
    
            DB::commit(); // Commit the transaction
    
            return new ResponseResource(true, "Berhasil mengimpor data", [
                'code_document' => $code_document,
                'headers' => $header,
                'file_name' => $fileName,
                'fileDetails' => [
                    'total_column_count' => count($header),
                    'total_row_count' => $rowCount,
                ]
            ]);
        } catch (ReaderException $e) {
            DB::rollback();
            return response()->json(['error' => 'Error processing file: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => 'Unexpected error occurred.'], 500);
        }
    }
    
    private function getHeadersFromSheet($sheet)
    {
        $header = [];
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
        return $header;
    }
    
    private function processRowsFromSheet($sheet, $header)
    {
        $rowCount = 0;
        $dataToInsert = [];
    
        foreach ($sheet->getRowIterator(2) as $row) {
            $rowData = [];
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
    
            foreach ($cellIterator as $cell) {
                $rowData[] = $cell->getValue() ?? '';
            }
    
            $rowData = array_slice(array_pad($rowData, count($header), ''), 0, count($header));
            $dataToInsert[] = ['data' => json_encode(array_combine($header, $rowData))];
        }
    
        $chunkSize = 500;
        foreach (array_chunk($dataToInsert, $chunkSize) as $chunk) {
            Generate::insert($chunk);
            $rowCount += count($chunk);
        }
    
        return $rowCount;
    }
    
    private function createDocumentEntry($fileName, $columnCount, $rowCount)
    {
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
            'date_document' => Carbon::now('Asia/Jakarta')->toDateString(),
        ]);
    
        return $code_document;
    }

    public function mapAndMergeHeaders(Request $request)
    {

        set_time_limit(300);
        ini_set('memory_limit', '512M');
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
                $qty = is_numeric($mergedData['old_quantity_product'][$index]) ? (int)$mergedData['old_quantity_product'][$index] : 0;

                // Memastikan bahwa harga adalah desimal yang valid atau diatur ke nol
                $harga = isset($mergedData['old_price_product'][$index]) && is_numeric($mergedData['old_price_product'][$index])
                    ? (float)$mergedData['old_price_product'][$index]
                    : 0.0;

                $dataToInsert[] = [
                    'code_document' => $request['code_document'],
                    'old_barcode_product' => $noResi,
                    'old_name_product' => $nama,
                    'old_quantity_product' => $qty,
                    'old_price_product' => $harga,
                ];
            }

            $chunkSize = 500;
            $totalInsertedRows = 0;

            foreach (array_chunk($dataToInsert, $chunkSize) as $chunkIndex => $chunk) {

                $insertResult = Product_old::insert($chunk);
                if ($insertResult) {
                    $insertedRows = count($chunk);
                    $totalInsertedRows += $insertedRows;
                } else {
                    Log::error("Failed to insert chunk {$chunkIndex} into product_olds");
                }
            }
            Generate::query()->delete();

            return new ResponseResource(true, "Berhasil menggabungkan data", ['inserted_rows' => $totalInsertedRows]);
        } catch (\Illuminate\Database\QueryException $qe) {
            DB::rollBack();

            return response()->json(['error' => 'Database query error: ' . $qe->getMessage()], 500);
        } catch (\Exception $e) {

            Log::error('Exception in mapAndMergeHeaders: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function deleteAll()
    {
        try {
            Generate::query()->delete();
            return new ResponseResource(true, "data berhasil dihapus", null);
        } catch (\Exception $e) {
            return new ResponseResource(false, "terjadi kesalahan saat menghapus data", null);
        }
    }

    public function deleteAllData()
    {
        try {
            Generate::query()->delete();
            Document::query()->delete();
            Product_old::query()->delete();
            Promo::query()->delete();
            Product_Bundle::query()->delete();
            PaletProduct::query()->delete();
            Bundle::query()->delete();
            Palet::query()->delete();
            RiwayatCheck::query()->delete();
            Repair::query()->delete();
            New_product::query()->delete();

            return new ResponseResource(true, "data berhasil dihapus", null);
        } catch (\Exception $e) {
            return new ResponseResource(false, "terjadi kesalahan saat menghapus data", null);
        }
    }
}
