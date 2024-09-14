<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Product_old;
use Illuminate\Http\Request;
use App\Models\ProductApprove;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Http\Resources\ResponseResource;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx\Rels;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

class DocumentController extends Controller
{

    public function index(Request $request)
    {
        $query = $request->input('q');
        $documents = Document::latest()->where(function ($queryBuilder) use ($query) {
            $queryBuilder->where('code_document', 'LIKE', '%' . $query . '%')
                ->orWhere('base_document', 'LIKE', '%' . $query . '%');
        })->paginate(50);
        return new ResponseResource(true, "List Documents", $documents);
    }


    public function create()
    {
        //
    }


    public function store(Request $request)
    {
        //
    }


    public function show(Document $document)
    {
        return new ResponseResource(true, "detail document", $document);
    }

    public function edit(Document $document)
    {
        //
    }


    public function update(Request $request, Document $document)
    {
        //
    }


    public function destroy(Document $document)
    {
        try {
            $product_old = Product_old::where('code_document', $document->code_document)->delete();
            $approve = ProductApprove::where('code_document', $document->code_document)->delete();
            $document->delete();

            return new ResponseResource(true, "data berhasil dihapus", $document);
        } catch (\Exception $e) {
            return new ResponseResource(false, "terjadi kesalahan saat menghapus data", null);
        }
    }
    public function deleteAll()
    {
        try {
            Document::truncate();
            return new ResponseResource(true, "data berhasil dihapus", null);
        } catch (\Exception $e) {
            return new ResponseResource(false, "terjadi kesalahan saat menghapus data", null);
        }
    }

    public function documentInProgress(Request $request)
    {
        $query = $request->input('q');
        $documents = Document::latest();

        if (!empty($query)) {
            $documents = $documents->where(function ($search) use ($query) {
                $search->where('status_document', '!=', 'pending')
                    ->where(function ($baseCode) use ($query) {
                        $baseCode->where('base_document', 'LIKE', '%' . $query . '%')
                            ->orWhere('code_document', 'LIKE', '%' . $query . '%');
                    });
            });
        } else {
            $documents = $documents->where('status_document', '!=', 'pending');
        }

        return new ResponseResource(true, "list document progress", $documents->paginate(30));
    }

    public function documentDone(Request $request) // halaman list product staging by doc
    {
        $query = $request->input('q');

        $documents = Document::latest()->where('status_document', 'done');

        // Jika query pencarian tidak kosong, tambahkan kondisi pencarian
        if (!empty($query)) {
            $documents = $documents->where(function ($search) use ($query) {
                $search->where(function ($baseCode) use ($query) {
                    $baseCode->where('base_document', 'LIKE', '%' . $query . '%')
                        ->orWhere('code_document', 'LIKE', '%' . $query . '%');
                });
            });
        }

        // Mengembalikan hasil dalam bentuk paginasi
        return new ResponseResource(true, "list document progress", $documents->paginate(50));
    }


    private function changeBarcodeByDocument($code_document, $init_barcode)
    {
        DB::beginTransaction();
        try {
            $document = Document::where('code_document', $code_document)->first();
            $document->custom_barcode = $init_barcode;
            $document->save();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating barcodes: ' . $e->getMessage());
            return false;
        }
    }

    public function changeBarcodeDocument(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code_document' => 'required',
            'init_barcode' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        $generate = $this->changeBarcodeByDocument($request->code_document, $request->init_barcode);

        if ($generate) {
            return new ResponseResource(true, "berhasil mengganti barcode", $request->init_barcode);
        } else {
            return "gagal";
        }
    }

    public function deleteCustomBarcode(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), ['code_document' => 'required']);
            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }
            $document = Document::where('code_document', $request->input('code_document'))->first();
            $document->update(['custom_barcode' => null]);
            return new ResponseResource(true, "custom barcode dihapus", null);
        } catch (\Exception $e) {
            return new ResponseResource(false, "gagal di hapus", $e->getMessage());
        }
    }
}
