<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Models\Migrate;
use App\Models\MigrateDocument;
use App\Models\New_product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MigrateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data['migrate'] = Migrate::where('status_migrate', 'proses')->latest()->paginate(20, ['*'], 'migrate_page');
        $data['code_document_migrate'] = $data['migrate']->isEmpty() ? codeDocumentMigrate() : $data['migrate'][0]['code_document_migrate'];

        $resource = new ResponseResource(true, "list migrate", $data);

        return $resource->response();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $userId = auth()->id();
        $codeDocumentMigrate = codeDocumentMigrate();

        $validator = Validator::make($request->all(), [
            'product_color' => 'nullable',
            'product_total' => 'required|numeric',
            'destiny_document_migrate' => 'nullable',
        ]);

        if ($validator->fails()) {
            $resource = new ResponseResource(false, "Input tidak valid!", $validator->errors());
            return $resource->response()->setStatusCode(422);
        }

        $productByTagColor = New_product::where('new_tag_product', $request->product_color)->get();
        if ($productByTagColor->isEmpty()) {
            $resource = new ResponseResource(false, "Data tidak di temukan!", ["product_color" => "product not found!"]);
            return $resource->response()->setStatusCode(404);
        }

        if ($productByTagColor->count() < $request->product_total) {
            $resource = new ResponseResource(false, "Input tidak valid!", ["product_total" => "the product is less than the quantity requested!"]);
            return $resource->response()->setStatusCode(422);
        }

        try {
            $migrateDocument = MigrateDocument::where('status_document_migrate', 'proses')->first();
            if ($migrateDocument == null) {
                $migrateDocumentStore = (new MigrateDocumentController)->store(new Request([
                    'code_document_migrate' => $codeDocumentMigrate,
                    'destiny_document_migrate' => $request->destiny_document_migrate,
                    'total_product_document_migrate' => 0,
                    'status_document_migrate' => 'proses',
                    'user_id' => $userId,
                ]));

                if ($migrateDocumentStore->getStatusCode() != 201) {
                    return $migrateDocumentStore;
                }

                $migrateDocument = $migrateDocumentStore->getData()->data->resource;
            }

            $migrate = Migrate::create([
                'code_document_migrate' => $migrateDocument->code_document_migrate,
                'product_color' => $request->product_color,
                'product_total' => $request->product_total,
                'status_migrate' => 'proses',
                'user_id' => $userId
            ]);

            $resource = new ResponseResource(true, "data berhasil disimpan!", $migrate);
            return $resource->response();
        } catch (\Exception $e) {
            $resource = new ResponseResource(false, "Data gagal di simpan!", [$e->getMessage()]);
            return $resource->response()->setStatusCode(422);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Migrate $migrate)
    {
        $resource = new ResponseResource(true, "Data migrate", $migrate);
        return $resource->response();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Migrate $migrate)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Migrate $migrate)
    {
        try {
            DB::beginTransaction();
            $migreteRecordCheck = Migrate::where('code_document_migrate', $migrate->code_document_migrate)->count();
            if ($migreteRecordCheck <= 1) {
                MigrateDocument::where('code_document_migrate', $migrate->code_document_migrate)->delete();
            }
            $migrate->delete();
            $resource = new ResponseResource(true, "Data berhasil di hapus!", $migrate);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $resource = new ResponseResource(false, "Data gagal di hapus!", $e->getMessage());
        }
        return $resource->response();
    }

    public function addMigrate(New_product $newProduct)
    {
        $codeDocumentMigrate = codeDocumentMigrate();
        $statusProduct = $newProduct->new_status_product;
        if ($statusProduct == 'display' || $statusProduct == 'promo' || $statusProduct == 'bundle') {
            try {
                $migrateDocument = MigrateDocument::where('status_document_migrate', 'proses')->first();
                if ($migrateDocument == null) {
                    $migrateDocumentStore = (new MigrateDocumentController)->store(new Request([
                        'code_document_migrate' => $codeDocumentMigrate,
                        'destiny_document_migrate' => '-',
                        'total_product_document_migrate' => 0,
                        'total_price_document_migrate' => 0,
                        'status_document_migrate' => 'proses'
                    ]));

                    if ($migrateDocumentStore->getStatusCode() != 201) {
                        return $migrateDocumentStore;
                    }

                    $migrateDocument = $migrateDocumentStore->getData()->data->resource;
                }

                $migrate = Migrate::create([
                    'code_document_migrate' => $migrateDocument->code_document_migrate,
                    'old_barcode_product' => $newProduct['old_barcode_product'],
                    'new_barcode_product' => $newProduct['new_barcode_product'],
                    'new_name_product' => $newProduct['new_name_product'],
                    'new_qty_product' => $newProduct['new_quantity_product'],
                    'new_price_product' => $newProduct['new_price_product'],
                    'new_tag_product' => $newProduct['new_tag_product'],
                    'status_migrate' => 'proses',
                    'status_product_before' => $newProduct['new_status_product'],
                ]);

                $newProduct->update(['new_status_product' => 'sale']);
            } catch (\Exception $e) {
                $resource = new ResponseResource(false, "Data gagal di simpan!", [$e->getMessage()]);
                return $resource->response()->setStatusCode(422);
            }

            $resource = new ResponseResource(true, "data berhasil disimpan!", $migrate);
            return $resource->response();
        } else {
            $resource = new ResponseResource(false, "Product tidak di temukan!", []);
            return $resource->response()->setStatusCode(404);
        }
    }

    public function codeDocumentMigrate(Request $request){
        $query = $request->input('q');
        $migrate = Migrate::where('code_document_migrate', $query)->get();
        return new ResponseResource(true, "list migrate by document", $migrate);
    }


    public function backupStoreMigrate(Request $request)
    {
        $userId = auth()->id();
        $codeDocumentMigrate = codeDocumentMigrate();

        $validator = Validator::make($request->all(), [
            'product_color' => 'nullable',
            'product_total' => 'required|numeric',
            'destiny_document_migrate' => 'nullable',
        ]);

        if ($validator->fails()) {
            $resource = new ResponseResource(false, "Input tidak valid!", $validator->errors());
            return $resource->response()->setStatusCode(422);
        }

        $productByTagColor = New_product::where('new_tag_product', $request->product_color)->get();
        if ($productByTagColor->isEmpty()) {
            $resource = new ResponseResource(false, "Data tidak di temukan!", ["product_color" => "product not found!"]);
            return $resource->response()->setStatusCode(404);
        }

        if ($productByTagColor->count() < $request->product_total) {
            $resource = new ResponseResource(false, "Input tidak valid!", ["product_total" => "the product is less than the quantity requested!"]);
            return $resource->response()->setStatusCode(422);
        }

        try {
            $migrateDocument = MigrateDocument::where('status_document_migrate', 'proses')->first();
            if ($migrateDocument == null) {
                $migrateDocumentStore = (new MigrateDocumentController)->store(new Request([
                    'code_document_migrate' => $codeDocumentMigrate,
                    'destiny_document_migrate' => $request->destiny_document_migrate,
                    'total_product_document_migrate' => 0,
                    'status_document_migrate' => 'proses',
                    'user_id' => $userId,
                ]));

                if ($migrateDocumentStore->getStatusCode() != 201) {
                    return $migrateDocumentStore;
                }

                $migrateDocument = $migrateDocumentStore->getData()->data->resource;
            }

            $migrate = Migrate::create([
                'code_document_migrate' => $migrateDocument->code_document_migrate,
                'product_color' => $request->product_color,
                'product_total' => $request->product_total,
                'status_migrate' => 'proses',
                'user_id' => $userId
            ]);

            $resource = new ResponseResource(true, "data berhasil disimpan!", $migrate);
            return $resource->response();
        } catch (\Exception $e) {
            $resource = new ResponseResource(false, "Data gagal di simpan!", [$e->getMessage()]);
            return $resource->response()->setStatusCode(422);
        }
    }
}
