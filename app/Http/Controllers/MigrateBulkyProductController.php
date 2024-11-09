<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Models\MigrateBulky;
use App\Models\MigrateBulkyProduct;
use App\Models\New_product;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MigrateBulkyProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(New_product $new_product)
    {
        $user = Auth::user();
        $migrateBulky = MigrateBulky::where('user_id', $user->id)->where('status_bulky', 'proses')->first();

        $newProduct = New_product::find($new_product->id);
        if (!$newProduct) {
            return response()->json(['errors' => ['new_product_id' => ['Produk tidak di temukan!']]], 422);
        }

        $migrateBulkyProduct = MigrateBulkyProduct::where('new_barcode_product', $new_product->new_barcode_product)->first();
        if ($migrateBulkyProduct) {
            return response()->json(['errors' => ['new_product_id' => ['Produk ini sudah di tambahkan!']]], 422);
        }

        if (!$migrateBulky) {
            // logic formater
            $lastCode = MigrateBulky::whereDate('created_at', today())
                ->max(DB::raw("CAST(SUBSTRING_INDEX(code_document, '/', 1) AS UNSIGNED)"));
            $newCode = str_pad(($lastCode + 1), 4, '0', STR_PAD_LEFT);
            $codeDocument = sprintf('%s/%s/%s', $newCode, date('m'), date('d'));
            // logic create
            $migrateBulky = MigrateBulky::create(
                [
                    'user_id' => $user->id,
                    'name_user' => $user->name,
                    'code_document' => $codeDocument,
                    'status_bulky' => 'proses',
                ]
            );
        }

        try {
            $newProduct = $newProduct->toArray();
            unset($newProduct['created_at'], $newProduct['updated_at']);

            $newProduct['migrate_bulky_id'] = $migrateBulky->id;
            $newProduct['new_product_id'] = $newProduct['id'];

            $migrateBulkyProduct = MigrateBulkyProduct::create($newProduct);

            return new ResponseResource(true, "Data berhasil disimpan!", $migrateBulky->load('migrateBulkyProducts'));
        } catch (\Exception $e) {
            return new ResponseResource(true, "Data gagal disimpan!", []);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(MigrateBulkyProduct $migrateBulkyProduct)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MigrateBulkyProduct $migrateBulkyProduct)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MigrateBulkyProduct $migrateBulkyProduct)
    {
        try {
            $user = Auth::user();
            $migrateBulky = MigrateBulky::where('user_id', $user->id)->where('status_bulky', 'proses')->first();

            if (!$migrateBulky) {
                return response()->json(['errors' => ['migrate_bulky' => ['tidak ada produk yang bisa dihapus!']]], 422);
            }

            $migrateBulkyProduct->delete();

            return new ResponseResource(true, "Data berhasil dihapus!", $migrateBulky->load('migrateBulkyProducts'));
        } catch (Exception $e) {
            return new ResponseResource(true, "Data gagal dihapus!", []);
        }
    }
}
