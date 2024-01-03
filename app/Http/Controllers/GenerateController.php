<?php

namespace App\Http\Controllers;

use App\Models\Generate;
use App\Models\ResultMerge;
use App\Models\ResultFilter;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Http\Resources\ResponseResource;
use App\Models\Document;
use App\Models\Product_old;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

class GenerateController extends Controller
{
    public function processExcelFiles(Request $request)
    { 

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls'
        ]);

        $file = $request->file('file');
        
        $headers = [];
        $templateHeaders = ["no_resi", "nama", "qty", "harga"];
        $fileDetails = [];

        $filePath = $file->getPathname();
        $fileName = $file->getClientOriginalName();
        $file->storeAs('public/ekspedisis', $file->hashName());

        $header = [];

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();

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
            $rowCount = 0;
            $columnCount = count($header);

            foreach ($sheet->getRowIterator(2) as $row) {
                $rowData = [];
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                foreach ($cellIterator as $cell) {
                    $value = $cell->getValue();
                    if (!is_null($value)) {
                        $rowData[] = $value;
                    }
                }

                if (count($header) == count($rowData)) {
                    $jsonRowData = json_encode(array_combine($header, $rowData));
                    Generate::create(['data' => $jsonRowData]);
                }
                $rowCount++;
            }

            $fileDetails = [
                'total_column_count' => $columnCount,
                'total_row_count' => $rowCount
            ];
        } catch (ReaderException $e) {
            return back()->with('error', 'Error processing file: ' . $e->getMessage());
        }

       
        $latestDocument = Document::latest()->first();
        $newId = $latestDocument ? $latestDocument->id + 1 : 1;

        $id_document = str_pad($newId, 4, '0', STR_PAD_LEFT);

        $month = date('m'); 
        $year = date('Y');

        $code_cocument = $id_document . '/' . $month . '/' . $year;
        

        Document::create([
            'code_document' => $code_cocument,
            'base_document' => $fileName,
            'total_column_document' => $columnCount,
            'total_column_in_document' => $rowCount,
        ]);

        // return response()->view('excelData', [
        //     'headers' => $headers,
        //     'templateHeaders' => $templateHeaders,
        //     'fileDetails' => $fileDetails
        // ]);

        return new ResponseResource(true, "berhasil", [
            'code_document' => $code_cocument,
            'headers' => $headers,
            'file_name' => $fileName,
            'templateHeaders' => $templateHeaders,
            'fileDetails' => $fileDetails
        ]);
    }

    public function mapAndMergeHeaders(Request $request)
    {
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



        foreach ($mergedData['old_barcode_product'] as $index => $noResi) {
            $nama = $mergedData['old_name_product'][$index] ?? null;
            $qty = $mergedData['old_quantity_product'][$index] ?? null;
            $harga = $mergedData['old_price_product'][$index] ?? null;

            $resultEntry = new Product_old([
                'code_document' => $request['code_document'],
                'old_barcode_product' => $noResi,
                'old_name_product' => $nama,
                'old_quantity_product' => $qty,
                'old_price_product' => $harga
            ]);
            $resultEntry->save();
        }

        //update status document
        $code_document = Document::where('code_document', $request['code_document'])->first();
        $code_document->update(['status_document' => 'in progress']);

        //view
        // return response()->json(['message' => 'Data has been merged and saved successfully.']);

        //api
        return new ResponseResource(true, "berhasil menggabungkan data", $resultEntry);
    }
}
