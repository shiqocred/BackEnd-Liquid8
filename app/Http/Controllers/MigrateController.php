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
        // $migrate = Migrate::latest()->paginate(10);
        if (request()->has('q')) {
            $data['new_product'] = New_product::when(request()->q, function ($query) {
                $query
                    ->where('new_barcode_product', 'like', '%' . request()->q . '%')
                    ->orWhere('new_name_product', 'like', '%' . request()->q . '%');
            })
                ->where('new_status_product', 'display')
                ->orWhere('new_status_product', 'bundle')
                ->orWhere('new_status_product', 'promo')
                ->latest()
                ->paginate(20);
        } else {
            $data['new_product'] = New_product::where('new_status_product', 'display')
                ->orWhere('new_status_product', 'bundle')
                ->orWhere('new_status_product', 'promo')
                ->latest()
                ->paginate(20);
        }
        $data['migrate'] = Migrate::where('status_migrate', 'proses')->latest()->paginate(20);;
        $data['code_document_migrate'] = $data['migrate']->isEmpty() ? codeDocumentMigrate() : $data['migrate'][0]['code_document_migrate'];

        $resource = new ResponseResource(true, "list migrate", $data);
        return $resource->response();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(New_product $new_product)
    {
        //
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
            $new_product = New_product::where('new_barcode_product', $migrate->new_barcode_product)->first();
            $new_product->update(['new_status_product' => $migrate->status_product_before]);
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
}
