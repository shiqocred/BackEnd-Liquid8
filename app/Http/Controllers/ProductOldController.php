<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Models\Color_tag;
use App\Models\Product_old;
use Illuminate\Http\Request;

class ProductOldController extends Controller
{
    public function searchByBarcode(Request $request)
    {
        $codeDocument = $request->input('code_document');

        if (!$codeDocument) {
            return new ResponseResource(false, "Code document tidak boleh kosong.", null);
        }

        $barcode = $request->input('old_barcode_product');

        if (!$barcode) {
            return new ResponseResource(false, "Barcode tidak boleh kosong.", null);
        }

        $product = Product_old::where('code_document', $codeDocument)
            ->where('old_barcode_product', $barcode)
            ->first();

        $price_old_product = $product->old_price_product;

        if ($product) {

            if ($price_old_product < 100000) {
                $colorTags = Color_tag::all();

                //mendeteksi range harga
                $filterPriceColors = collect($colorTags)->filter(function ($color) use ($price_old_product) {
                    $minPrice = $color['min_price_color'];
                    $maxPrice = $color['max_price_color'];

                    return ($price_old_product >= $minPrice) && ($price_old_product <= $maxPrice);
                });
                
                return new ResponseResource(true, "Produk Ditemukan", [$product, $filterPriceColors->values()]);
            }else {

                return new ResponseResource(true, "Produk Ditemukan", $product);
            }

        } else {
            return new ResponseResource(false, "Produk tidak ditemukan", null);
        }
    }

    public function serachByDocument(Request $request)
    {
        $code_documents = Product_old::where('code_document', $request->input('search'))->get();
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
}
