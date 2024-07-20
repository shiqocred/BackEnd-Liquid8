<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Models\Document;
use App\Models\Product_old;
use App\Models\ProductApprove;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx\Rels;

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
    
        return new ResponseResource(true, "list document progress", $documents->get());
    }
    
}
