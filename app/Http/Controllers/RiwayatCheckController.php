<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Document;
use App\Models\New_product;
use App\Models\RiwayatCheck;
use Illuminate\Http\Request;
use App\Mail\AdminNotification;
use App\Models\SpecialTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Http\Resources\ResponseResource;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RiwayatCheckController extends Controller
{

    public function index()
    {
        $riwayats = RiwayatCheck::latest()->paginate(50);
        return new ResponseResource(true, "list riwayat", $riwayats);
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');
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

            $newProducts = New_product::where('code_document', $request['code_document'])->get();

            $totalData = $newProducts->count();
            $totalLolos = $totalDamaged = $totalAbnormal = 0;


            foreach ($newProducts as $product) {
                $newQualityData = json_decode($product->new_quality, true);

                if (is_array($newQualityData)) {
                    $totalLolos += !empty($newQualityData['lolos']) ? 1 : 0;
                    $totalDamaged += !empty($newQualityData['damaged']) ? 1 : 0;
                    $totalAbnormal += !empty($newQualityData['abnormal']) ? 1 : 0;
                }
            }



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
                'percentage_discrepancy' => (($document->total_column_in_document - $totalData) / $document->total_column_in_document) * 100,
            ]);


            $code_document = Document::where('code_document', $request['code_document'])->first();
            $code_document->update(['status_document' => 'done']);

            //keterangan transaksi
            $keterangan = SpecialTransaction::create([
                'user_id' => $user->id,
                'transaction_name' => 'list product document sudah di check',
                'status' => 'pending'
            ]);

            $adminUser = User::where('email', 'isagagah3@gmail.com')->first();

            if ($adminUser) {
                Mail::to($adminUser->email)->send(new AdminNotification($adminUser, $keterangan->id));
            } else {
                $resource = new ResponseResource(false, "email atau transaksi tidak ditemukan", null);
                return $resource->response()->setStatusCode(403);
            }

            DB::commit();

            return new ResponseResource(true, "Data berhasil ditambah", [$riwayat_check, $keterangan]);
        } catch (\Exception $e) {
            DB::rollBack();
            $resource = new ResponseResource(false, "Data gagal ditambahkan, terjadi kesalahan pada server : " . $e->getMessage(), null);
            $resource->response()->setStatusCode(500);
        }
    }

    public function show(RiwayatCheck $history)
    {
        return new ResponseResource(true, "Riwayat Check", $history);
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


    public function update(Request $request, RiwayatCheck $riwayatCheck)
    {
        //
    }


    public function destroy(RiwayatCheck $history)
    {
        try {
            $history->delete();
            return new ResponseResource(true, 'data berhasil di hapus', $history);
        } catch (\Exception $e) {
            return new ResponseResource(false, 'data gagal di hapus', null);
        }
    }

    public function exportToExcel(Request $request)
    {
        $code_document = $request->input('code_document');
        // $code_document = '0001/02/2024';
        $checkHistory = RiwayatCheck::where('code_document', $code_document)->get();

        if ($checkHistory->isEmpty()) {
            return response()->json(['status' => false, 'message' => "Data kosong, tidak bisa di export"], 422);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header dan data disimpan secara vertikal
        $headers = [
            'ID', 'User ID', 'Code Document', 'Base Document', 'Total Data', 'Total Data In', 'Total Data Lolos', 'Total Data Damaged', 'Total Data Abnormal', 'Total Discrepancy', 'Status Approve', 'Percentage Total Data', 'Percentage In', 'Percentage Lolos', 'Percentage Damaged', 'Percentage Abnormal', 'Percentage Discrepancy'
        ];
        // Set header dan data
        $currentRow = 1; // Mulai dari baris pertama
        foreach ($checkHistory as $riwayatCheck) {
            foreach ($headers as $index => $header) {
                $columnName = strtolower(str_replace(' ', '_', $header));
                $cellValue = $riwayatCheck->$columnName;
                // Set header
                $sheet->setCellValueByColumnAndRow(1, $currentRow, $header);
                // Set value
                $sheet->setCellValueByColumnAndRow(2, $currentRow, $cellValue);
                $currentRow++; // Pindah ke baris berikutnya
            }
            // Menambahkan baris kosong setelah setiap data checkHistory
            $currentRow++;
        }

        $firstItem = $checkHistory->first();

        $writer = new Xlsx($spreadsheet);
        $fileName = $firstItem->base_document;
        $publicPath = 'exports';
        $filePath = public_path($publicPath) . '/' . $fileName;

        // Create exports directory if not exist
        if (!file_exists(public_path($publicPath))) {
            mkdir(public_path($publicPath), 0777, true);
        }

        $writer->save($filePath);

        $downloadUrl = url($publicPath . '/' . $fileName);

        return new ResponseResource(true, "File siap diunduh.", $downloadUrl);
        // response()->json(['status' => true, 'message' => "", 'downloadUrl' => $downloadUrl]);
    }
}
