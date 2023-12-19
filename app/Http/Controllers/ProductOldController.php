<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Models\Product_old;
use Illuminate\Http\Request;

class ProductOldController extends Controller
{

    public function productOld_byDoc(Request $request)
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
