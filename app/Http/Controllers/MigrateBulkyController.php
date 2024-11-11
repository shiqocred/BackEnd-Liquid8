<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Models\MigrateBulky;
use App\Models\New_product;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MigrateBulkyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        $migrateBulky = MigrateBulky::latest()->paginate(50);

        return new ResponseResource(true, "list migrate bulky", $migrateBulky);
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
    public function show(MigrateBulky $migrateBulky)
    {
        return new ResponseResource(true, "list migrate bulky", $migrateBulky->load('migrateBulkyProducts'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MigrateBulky $migrateBulky)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MigrateBulky $migrateBulky)
    {
        //
    }

    public function finishMigrateBulky()
    {
        $user = Auth::user();
        $migrateBulky = MigrateBulky::with('migrateBulkyProducts')->where('user_id', $user->id)->where('status_bulky', 'proses')->first();

        if (!$migrateBulky) {
            return response()->json(['errors' => ['migrate_bulky' => ['pastikan anda menambahkan produk untuk migrate!']]], 422);
        }

        try {
            $newProductIds = $migrateBulky->migrateBulkyProducts()->pluck('new_product_id');
            New_product::whereIn('id', $newProductIds)->delete();

            $migrateBulky->update(['status_bulky' => 'added']);

            return new ResponseResource(true, "Data berhasil di migrate!", $migrateBulky->load('migrateBulkyProducts'));
        } catch (Exception $e) {
            return response()->json(['errors' => ['migrate_bulky' => ['Data gagal di migrate!']]], 500);
        }
    }
}
