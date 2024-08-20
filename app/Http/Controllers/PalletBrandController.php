<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Models\PalletBrand;
use App\Models\ProductBrand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PalletBrandController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $palletBrand = PalletBrand::all();
        $resource = new ResponseResource(true, "list brand pallet", $palletBrand);

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
        $validator = Validator::make($request->all(), [
            'pallet_id' => 'required|integer|exists:palets,id',
            'brands' => 'required|array',
            'brands.*' => 'required|integer|gt:0',
        ]);

        if ($validator->fails()) {
            $resource = new ResponseResource(false, "Input tidak valid!", $validator->errors());
            return $resource->response()->setStatusCode(422);
        }

        try {
            $palletId = $request->input('pallet_id');
            $brands = $request->input('brands');

            $createdBrands = [];
            foreach ($brands as $brandId) {
                $palletBrandName = ProductBrand::findOrFail($brandId)->brand_name;
                $palletBrand = PalletBrand::create([
                    'pallet_id' => $palletId,
                    'brand_id' => $brandId,
                    'pallet_brand_name' => $palletBrandName,
                ]);
                $createdBrands[] = $palletBrand;
            }
            $resource = new ResponseResource(true, "Data berhasil disimpan!", $createdBrands);
            return $resource->response();
        } catch (\Exception $e) {
            $resource = new ResponseResource(false, "Data gagal di simpan!", [$e->getMessage()]);
            return $resource->response()->setStatusCode(422);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(PalletBrand $palletBrand)
    {
        try {
            // Kirimkan data dalam response
            $resource = new ResponseResource(true, "Data brand pallet", $palletBrand);
            return $resource->response();
        } catch (\Exception $e) {
            $resource = new ResponseResource(false, "Data tidak ditemukan!", [$e->getMessage()]);
            return $resource->response()->setStatusCode(404);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PalletBrand $palletBrand)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $palletId)
    {
        $validator = Validator::make($request->all(), [
            'brands' => 'required|array',
            'brands.*' => 'required|integer|gt:0',
        ]);

        if ($validator->fails()) {
            $resource = new ResponseResource(false, "Input tidak valid!", $validator->errors());
            return $resource->response()->setStatusCode(422);
        }

        try {
            $brands = $request->input('brands');
            $updatedBrands = [];

            foreach ($brands as $brandId) {
                $palletBrandName = ProductBrand::findOrFail($brandId)->brand_name;

                // Gunakan updateOrCreate untuk memperbarui atau membuat data baru jika belum ada
                $palletBrand = PalletBrand::updateOrCreate(
                    ['pallet_id' => $palletId, 'brand_id' => $brandId],
                    ['pallet_brand_name' => $palletBrandName]
                );

                $updatedBrands[] = $palletBrand;
            }

            $resource = new ResponseResource(true, "Data berhasil diperbarui!", $updatedBrands);
            return $resource->response();
        } catch (\Exception $e) {
            $resource = new ResponseResource(false, "Data gagal diperbarui!", [$e->getMessage()]);
            return $resource->response()->setStatusCode(422);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PalletBrand $palletBrand)
    {
        try {
            $palletBrand->delete();

            $resource = new ResponseResource(true, "Data berhasil dihapus!", $palletBrand);
            return $resource->response();
        } catch (\Exception $e) {
            $resource = new ResponseResource(false, "Data gagal dihapus!", [$e->getMessage()]);
            return $resource->response()->setStatusCode(422);
        }
    }
}
