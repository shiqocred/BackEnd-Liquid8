<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Models\BulkyDocument;
use Illuminate\Http\Request;

class BulkyDocumentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = $request->input('q');
        $bulkyDocument = BulkyDocument::latest();
        if ($query) {
            $bulkyDocument = $bulkyDocument->where(function ($data) use ($query) {
                $data->where('code_document_bulky', 'LIKE', '%' . $query . '%');
            });
        }
        $bulkyDocument = $bulkyDocument->paginate(10);
        $resource = new ResponseResource(true, "list document bulky", $bulkyDocument);
        return $resource->response();
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
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(BulkyDocument $bulkyDocument)
    {
        $resource = new ResponseResource(true, "data document bulky", $bulkyDocument->load('bulkySales'));
        return $resource->response();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BulkyDocument $bulkyDocument)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BulkyDocument $bulkyDocument)
    {
        try {
            $bulkyDocument->delete();
            $resource = new ResponseResource(true, "data berhasil di hapus!", $bulkyDocument);
        } catch (\Exception $e) {
            $resource = new ResponseResource(false, "data gagal di hapus!", $e->getMessage());
        }
        return $resource->response();
    }
}
