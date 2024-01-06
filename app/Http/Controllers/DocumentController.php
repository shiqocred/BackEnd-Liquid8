<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Models\Document;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;



class DocumentController extends Controller
{
    
    public function index()
    {
        $documents = Document::latest()->paginate(50);
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
        try{
            $document->delete();
            return new ResponseResource(true, "data berhasil dihapus", $document);
        }catch (\Exception $e){
            return new ResponseResource(false, "terjadi kesalahan saat menghapus data", null);
        }
    }
}
