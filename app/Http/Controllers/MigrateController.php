<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Models\Migrate;
use App\Models\MigrateDocument;
use App\Models\New_product;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        $newProduct = New_product::where('new_status_product', 'display')
            ->orWhere('new_status_product', 'bundle')
            ->orWhere('new_status_product', 'promo')
            ->get();
        $newProduct[] = ['code_document_migrate' => codeDocumentMigrate()];
        $resource = new ResponseResource(true, "list migrate", $newProduct);
        return $resource->response();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->all();
        array_shift($data);

        $collection = new Collection($data);

        $requestDocumentMigrate['code_document_migrate'] = codeDocumentMigrate();
        $requestDocumentMigrate['total_product_document_migrate'] = count($data);
        $requestDocumentMigrate['total_price_document_migrate'] = $collection->sum('new_price_product');
        $requestDocumentMigrate['destiny_document_migrate'] = $request[0]['destiny_document_migrate'];

        $validator = Validator::make(
            $data,
            [
                '*.new_barcode_product' => 'required|unique:migrates',
                '*.new_name_product' => 'required',
                '*.new_qty_product' => 'required|numeric',
                '*.new_price_product' => 'required|numeric',
                '*.new_tag_product' => 'required',
            ]
        );

        if ($validator->fails()) {
            $resource = new ResponseResource(false, "Input tidak valid!", $validator->errors());
            return $resource->response()->setStatusCode(422);
        }

        try {
            $migrateDocument = (new MigrateDocumentController)->store(new Request([
                'code_document_migrate' => $requestDocumentMigrate['code_document_migrate'],
                'destiny_document_migrate' => $requestDocumentMigrate['destiny_document_migrate'],
                'total_product_document_migrate' => $requestDocumentMigrate['total_product_document_migrate'],
                'total_price_document_migrate' => $requestDocumentMigrate['total_price_document_migrate']
            ]));

            if ($migrateDocument->getStatusCode() != 201) {
                return $migrateDocument;
            }

            //automatic include craeted_at & updated_at for bacth insert
            foreach ($data as &$val) {
                $newProduct = New_product::where('new_barcode_product', $val['new_barcode_product'])->first();
                $newProduct->update(['new_status_product' => 'migrate']);
                $val['code_document_migrate'] = $requestDocumentMigrate['code_document_migrate'];
                $val['created_at'] = now();
                $val['updated_at'] = now();
            }

            $migrate = Migrate::insert($data);
        } catch (\Exception $e) {
            $resource = new ResponseResource(false, "Data gagal di simpan!", [$e->getMessage()]);
            return $resource->response()->setStatusCode(422);
        }

        $resource = new ResponseResource(true, "data berhasil disimpan!", $migrate);
        return $resource->response();
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
            $migrateDocument = MigrateDocument::where('code_document_migrate', $migrate->code_document_migrate)->first();
            $totalPriceDocumentMigrate = $migrateDocument->total_price_document_migrate - $migrate->new_price_product;
            $totalProductDocumentMigrate = $migrateDocument->total_product_document_migrate - 1;
            if ($totalPriceDocumentMigrate == 0 && $totalProductDocumentMigrate == 0) {
                $migrateDocument->delete();
            } else {
                $migrateDocument->update(
                    [
                        'total_product_document_migrate' => $totalProductDocumentMigrate,
                        'total_price_document_migrate' => $totalPriceDocumentMigrate,
                    ]
                );
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
}
