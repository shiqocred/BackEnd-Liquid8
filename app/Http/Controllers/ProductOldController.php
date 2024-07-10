<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Models\Color_tag;
use App\Models\New_product;
use App\Models\Product_old;
use Illuminate\Http\Request;

class ProductOldController extends Controller
{

    public function searchByBarcode(Request $request)
    {
        $codeDocument = $request->input('code_document');
        $oldBarcode = $request->input('old_barcode_product');

        if (!$codeDocument) {
            return new ResponseResource(false, "Code document tidak boleh kosong.", null);
        }

        if (!$oldBarcode) {
            return new ResponseResource(false, "Barcode tidak boleh kosong.", null);
        }

        $checkBarcode = New_product::where('code_document', $codeDocument)
            ->where('old_barcode_product', $oldBarcode)
            ->exists();

        if ($checkBarcode) {
            return new ResponseResource(false, "tidak bisa scan product yang sudah ada.", null);
        }

        $product = Product_old::where('code_document', $codeDocument)
            ->where('old_barcode_product', $oldBarcode)
            ->first();

        if (!$product) {
            return new ResponseResource(false, "Produk tidak ditemukan.", null);
        }

        $newBarcode = $this->generateUniqueBarcode();
        $response = ['product' => $product];

        if ($product->old_price_product <= 99999) {
            $response['color_tags'] = Color_tag::where('min_price_color', '<=', $product->old_price_product)
                ->where('max_price_color', '>=', $product->old_price_product)
                ->get();
        }


        return new ResponseResource(true, "Produk ditemukan.", $response);
    }


    private function generateUniqueBarcode()
    {
        $prefix = 'LQD';
        do {
            $randomNumber = str_pad(mt_rand(0, 99999), 5, '0', STR_PAD_LEFT);
            $barcode = $prefix . $randomNumber;
        } while (New_product::where('new_barcode_product', $barcode)->exists());

        return $barcode;
    }


    public function searchByDocument(Request $request)
    {
        $code_documents = Product_old::where('code_document', $request->input('search'))->paginate(50);

        if ($code_documents->isNotEmpty()) {
            return new ResponseResource(true, "list product_old", $code_documents);
        } else {
            return new ResponseResource(false, "code document tidak ada", null);
        }
    }

    public function index()
    {
        $product_olds = Product_old::latest()->paginate(50);

        return new ResponseResource(true, "list all product_old", $product_olds);
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
    public function show(Product_old $product_old)
    {
        return new ResponseResource(true, "data product_old", $product_old);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product_old $product_old)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product_old $product_old)
    {
        $product_old->delete();
        return new ResponseResource(true, "berhasil di hapus", $product_old);
    }
    public function deleteAll()
    {
        try {
            Product_old::truncate();

            return new ResponseResource(true, "Semua data berhasil dihapus", null);
        } catch (\Exception $e) {
            return new ResponseResource(false, "Terjadi kesalahan saat menghapus data", null);
        }
    }
}
