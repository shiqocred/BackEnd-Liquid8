<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Models\Migrate;
use App\Models\MigrateDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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
            })->latest()->paginate(10);
        } else {
            $migrateDocument = MigrateDocument::latest()->paginate(10);
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
                'total_price_document_migrate' => 'required|numeric',
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

    public function MigrateDocumentFinish(Request $request)
    {
        $validator = Validator::make($request->all(), ['destiny_document_migrate' => 'required']);
        if ($validator->fails()) {
            $resource = new ResponseResource(false, "Input tidak valid!", $validator->errors());
            return $resource->response()->setStatusCode(422);
        }
        try {
            DB::beginTransaction();
            $migrateDocument = MigrateDocument::where('status_document_migrate', 'proses')->first();
            if ($migrateDocument == null) {
                $resource = new ResponseResource(false, "Data migrate tidak ditemukan!", []);
                return $resource->response()->setStatusCode(404);
            }
            $migrate = Migrate::where('code_document_migrate', $migrateDocument->code_document_migrate)->get();
            Migrate::where('code_document_migrate', $migrateDocument->code_document_migrate)->update(['status_migrate' => 'selesai']);
            $migrateDocument->update([
                'destiny_document_migrate' => $request['destiny_document_migrate'],
                'total_product_document_migrate' => count($migrate),
                'total_price_document_migrate' => $migrate->sum('new_price_product'),
                'status_document_migrate' => 'selesai'
            ]);
            DB::commit();
            $resource = new ResponseResource(true, 'Data berhasil di merge', $migrateDocument);
        } catch (\Exception $e) {
            DB::rollBack();
            $resource = new ResponseResource(false, 'Data gagal di merge', [$e->getMessage()]);
        }
        return $resource->response();
    }
}
