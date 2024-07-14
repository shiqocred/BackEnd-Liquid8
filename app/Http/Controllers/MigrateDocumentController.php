<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Models\Migrate;
use App\Models\MigrateDocument;
use App\Models\New_product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MigrateDocumentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (request()->has('q')) {
            $migrateDocument = MigrateDocument::when(request()->q, function ($query) {
                $query
                    ->where('code_document_migrate', 'like', '%' . request()->q . '%')
                    ->where('created_at', 'like', '%' . request()->q . '%');
            })
                ->where('status_document_migrate', 'selesai')
                ->latest()
                ->paginate(15);
        } else {
            $migrateDocument = MigrateDocument::where('status_document_migrate', 'selesai')->latest()->paginate(15);
        }
        $resource = new ResponseResource(true, "list dokumen migrate", $migrateDocument);
        return $resource->response();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'code_document_migrate' => 'required|unique:migrate_documents',
                'destiny_document_migrate' => 'required',
                'total_product_document_migrate' => 'required|numeric',
            ]
        );

        if ($validator->fails()) {
            $resource = new ResponseResource(false, "Input tidak valid!", $validator->errors());
            return $resource->response()->setStatusCode(422);
        }

        try {
            $migrateDocument = MigrateDocument::create($request->all());
            $resource = new ResponseResource(true, "Data berhasil ditambahkan!", $migrateDocument);
        } catch (\Exception $e) {
            $resource = new ResponseResource(false, "Data gagal ditambahkan!", $e->getMessage());
        }

        return $resource->response();
    }

    /**
     * Display the specified resource.
     */
    public function show(MigrateDocument $migrateDocument)
    {
        $resource = new ResponseResource(true, "Data document migrate", $migrateDocument->load('migrates'));
        return $resource->response();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MigrateDocument $migrateDocument)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MigrateDocument $migrateDocument)
    {
        try {
            $migrateDocument->delete();
            $resource = new ResponseResource(true, "Data berhasil di hapus!", $migrateDocument);
        } catch (\Exception $e) {
            $resource = new ResponseResource(false, "Data gagal di hapus!", [$e->getMessage()]);
        }
        return $resource->response();
    }

    public function MigrateDocumentFinish()
    {
        try {
            DB::beginTransaction();
            $total = 0;
            $userId = auth()->id();
            $migrateDocuments = MigrateDocument::with('migrates')->where([
                ['user_id', '=', $userId],
                ['status_document_migrate', '=', 'proses']
            ])->get();
    
            // Iterasi melalui setiap MigrateDocument dalam koleksi
            foreach ($migrateDocuments as $migrateDocument) {
                $relatedMigrates = $migrateDocument->migrates;
    
                foreach ($relatedMigrates as $m) {
                    $productTotal = $m->product_total;
    
                    // Mengambil sejumlah data tertentu dan mengupdate statusnya
                    $updatedCount = New_product::where('new_tag_product', $m->product_color)
                        ->where('new_status_product', 'display')
                        ->limit($productTotal)
                        ->update(['new_status_product' => 'migrate']);
                    
                    // Tambahkan jumlah produk yang berhasil diupdate ke total
                    $total += $updatedCount;
                }
    
                // Mengupdate status migrates dan migrate document setelah loop selesai
                Migrate::where('code_document_migrate', $migrateDocument->code_document_migrate)->update(['status_migrate' => 'selesai']);
                $migrateDocument->update([
                    'total_product_document_migrate' => $relatedMigrates->sum('product_total'),
                    'status_document_migrate' => 'selesai'
                ]);
            }
    
            DB::commit();
            $resource = new ResponseResource(true, 'Data berhasil di merge', $migrateDocuments);
        } catch (\Exception $e) {
            DB::rollBack();
            $resource = new ResponseResource(false, 'Data gagal di merge', [$e->getMessage()]);
        }
        return $resource->response();
    }

    public function exportMigrateDetail($id)
    {
        // Meningkatkan batas waktu eksekusi dan memori
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();


        $migrateHeaders = [
            'id', 'code_document_migrate', 'destiny_document_migrate', 'total_product_document_migrate',
            'status_document_migrate'
        ];

        $migrateProductHeaders = [
            'code_document_migrate', 'product_color', 'product_total',
            'status_migrate'
        ];

        $columnIndex = 1;
        foreach ($migrateHeaders as $header) {
            $sheet->setCellValueByColumnAndRow($columnIndex, 1, $header);
            $columnIndex++;
        }

        $rowIndex = 2;

        $migrate = MigrateDocument::with('migrates')->where('id', $id)->first();
        if ($migrate) {
            $columnIndex = 1;

            foreach ($migrateHeaders as $header) {
                $sheet->setCellValueByColumnAndRow($columnIndex, $rowIndex, $migrate->$header);
                $columnIndex++;
            }
            $rowIndex++;

            $rowIndex++;
            $productColumnIndex = 1;
            foreach ($migrateProductHeaders as $header) {
                $sheet->setCellValueByColumnAndRow($productColumnIndex, $rowIndex, $header);
                $productColumnIndex++;
            }
            $rowIndex++;

            if ($migrate->migrates->isNotEmpty()) {
                foreach ($migrate->migrates as $productMigrate) {
                    $productColumnIndex = 1; // Mulai dari kolom pertama
                    foreach ($migrateProductHeaders as $header) {
                        $sheet->setCellValueByColumnAndRow($productColumnIndex, $rowIndex, $productMigrate->$header);
                        $productColumnIndex++;
                    }
                    $rowIndex++;
                }
            }
            $rowIndex++; // Baris kosong 
        } else {
            $sheet->setCellValueByColumnAndRow(1, 1, 'No data found');
        }

        // Menyimpan file Excel
        $writer = new Xlsx($spreadsheet);
        $fileName = 'exportRepair_'.$migrate->repair_name.'.xlsx';
        $publicPath = 'exports';
        $filePath = public_path($publicPath) . '/' . $fileName;

        // Membuat direktori exports jika belum ada
        if (!file_exists(public_path($publicPath))) {
            mkdir(public_path($publicPath), 0777, true);
        }

        $writer->save($filePath);

        // Mengembalikan URL untuk mengunduh file
        $downloadUrl = url($publicPath . '/' . $fileName);

        return new ResponseResource(true, "unduh", $downloadUrl);
    }
    
}
