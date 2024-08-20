<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Models\Palet;
use App\Models\PalletImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PalletImageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $palletImage = PalletImage::all();
        $resource = new ResponseResource(true, "list images pallet", $palletImage);

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
        // Validasi input
        $validator = Validator::make($request->all(), [
            'pallet_id' => 'required|integer|exists:palets,id',
            'images' => 'required|array',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            $resource = new ResponseResource(false, "Input tidak valid!", $validator->errors());
            return $resource->response()->setStatusCode(422);
        }

        try {
            $palletId = $request->input('pallet_id');
            $uploadedImages = [];

            // Proses setiap file gambar
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    // Simpan gambar ke disk
                    $image->store('product-images', 'public'); // Menyimpan di folder 'storage/app/public/images'
                    $filename = $image->hashName(); // Nama file gambar

                    // Simpan informasi gambar ke database
                    $uploadedImage = PalletImage::create([
                        'pallet_id' => $palletId,
                        'filename' => $filename,
                    ]);

                    $uploadedImages[] = $uploadedImage;
                }
            }

            $resource = new ResponseResource(true, "Gambar berhasil diunggah!", $uploadedImages);
            return $resource->response();
        } catch (\Exception $e) {
            $resource = new ResponseResource(false, "Gambar gagal diunggah!", [$e->getMessage()]);
            return $resource->response()->setStatusCode(422);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($palletId)
    {
        // Validasi ID pallet
        $validator = Validator::make(['pallet_id' => $palletId], [
            'pallet_id' => 'required|integer|exists:palets,id',
        ]);

        if ($validator->fails()) {
            $resource = new ResponseResource(false, "Pallet ID tidak valid!", $validator->errors());
            return $resource->response()->setStatusCode(422);
        }

        try {
            // Ambil semua gambar terkait dengan pallet_id
            $images = PalletImage::where('pallet_id', $palletId)->get();

            if ($images->isEmpty()) {
                $resource = new ResponseResource(false, "Tidak ada gambar ditemukan untuk pallet ID ini.", []);
                return $resource->response()->setStatusCode(404);
            }

            $resource = new ResponseResource(true, "Gambar berhasil ditemukan!", $images);
            return $resource->response();
        } catch (\Exception $e) {
            $resource = new ResponseResource(false, "Gambar gagal diambil!", [$e->getMessage()]);
            return $resource->response()->setStatusCode(422);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PalletImage $palletImage)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $palletId)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'images' => 'nullable|array',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            $resource = new ResponseResource(false, "Input tidak valid!", $validator->errors());
            return $resource->response()->setStatusCode(422);
        }

        try {
            $uploadedImages = [];

            // Hapus gambar lama yang tidak ada dalam input baru
            $existingImages = PalletImage::where('pallet_id', $palletId)->pluck('filename')->toArray();
            $newImages = $request->input('images', []);

            // Jika ada gambar baru yang diunggah
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    // Simpan gambar ke disk
                    $image->store('product-images', 'public'); // Menyimpan di folder 'storage/app/public/product-images'
                    $filename = $image->hashName(); // Nama file gambar

                    // Simpan informasi gambar ke database
                    $uploadedImage = PalletImage::updateOrCreate(
                        ['pallet_id' => $palletId, 'filename' => $filename],
                        ['pallet_id' => $palletId, 'filename' => $filename]
                    );

                    $uploadedImages[] = $uploadedImage;
                }
            }

            // Hapus gambar yang tidak ada dalam input baru
            $newImageFilenames = array_map(fn($file) => $file->hashName(), $request->file('images', []));
            $imagesToDelete = array_diff($existingImages, $newImageFilenames);

            foreach ($imagesToDelete as $filename) {
                // Hapus gambar dari disk
                Storage::disk('public')->delete('product-images/' . $filename);
                // Hapus data gambar dari database
                PalletImage::where('pallet_id', $palletId)->where('filename', $filename)->delete();
            }

            $resource = new ResponseResource(true, "Gambar berhasil diperbarui!", $uploadedImages);
            return $resource->response();
        } catch (\Exception $e) {
            $resource = new ResponseResource(false, "Gambar gagal diperbarui!", [$e->getMessage()]);
            return $resource->response()->setStatusCode(422);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PalletImage $palletImage)
    {
        try {
            $palletImage->delete();

            $resource = new ResponseResource(true, "Data berhasil dihapus!", $palletImage);
            return $resource->response();
        } catch (\Exception $e) {
            $resource = new ResponseResource(false, "Data gagal dihapus!", [$e->getMessage()]);
            return $resource->response()->setStatusCode(422);
        }
    }
}
