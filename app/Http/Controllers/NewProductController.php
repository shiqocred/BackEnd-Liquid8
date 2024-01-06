<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Models\Category;
use App\Models\Color_tag;
use App\Models\New_product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NewProductController extends Controller
{

    public function index()
    {
        $newProducts = New_product::latest()->paginate(50);

        return new ResponseResource(true, "list new product", $newProducts);
    }


    public function create()
    {
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code_document' => 'required',
            'old_barcode_product' => 'required',
            'new_barcode_product' => 'required|unique:new_products,new_barcode_product',
            'new_name_product' => 'required',
            'new_quantity_product' => 'required|integer',
            'new_price_product' => 'required|numeric',
            'new_date_in_product' => 'required|date',
            'new_status_product' => 'required|in:display,expired,promo,bundle,palet',
            'condition' => 'required|in:lolos,damaged,abnormal',
            'new_category_product' => 'nullable|exists:categories,name_category',
            'new_tag_product' => 'nullable|exists:color_tags,name_color'
        ]);
    
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
    
        $status = $request->input('condition');
        $description = $request->input('deskripsi', '');
    
        $qualityData = [
            'lolos' => $status === 'lolos' ? 'lolos' : null,
            'damaged' => $status === 'damaged' ? $description : null,
            'abnormal' => $status === 'abnormal' ? $description : null,
        ];
    
        $inputData = $request->only([
            'code_document',
            'old_barcode_product',
            'new_barcode_product',
            'new_name_product',
            'new_quantity_product',
            'new_price_product',
            'new_date_in_product',
            'new_status_product',
            'new_category_product',
            'new_tag_product'
        ]);
    
        if ($status !== 'lolos') {
            $inputData['new_status_product'] = 'display';
            $inputData['new_quantity_product'] = 0;
            $inputData['new_price_product'] = 0;
            $inputData['new_category_product'] = '';
            $inputData['new_tag_product'] = '';
            $inputData['new_name_product'] = '';
            $inputData['new_barcode_product'] = '';
        }
    
        $inputData['new_quality'] = json_encode($qualityData);
    
        $newProduct = New_product::create($inputData);
    
        return new ResponseResource(true, "New Produk Berhasil ditambah", $newProduct);
    }
    

    /**
     * Display the specified resource.
     */
    public function show(New_product $new_product)
    {
        return new ResponseResource(true, "data new product", $new_product);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(New_product $new_product)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, New_product $new_product)
    {
        $validator = Validator::make($request->all(), [
            'code_document' => 'required',
            'old_barcode_product' => 'required',
            'new_barcode_product' => 'required',
            'new_name_product' => 'required',
            'new_quantity_product' => 'required|integer',
            'new_price_product' => 'required|numeric',
            'new_date_in_product' => 'required|date',
            'new_status_product' => 'required|in:display,expired,promo,bundle,palet',
            'condition' => 'required|in:lolos,damaged,abnormal',
            'new_category_product' => 'nullable|exists:categories,name_category',
            'new_tag_product' => 'nullable|exists:color_tags,name_color'
        ]);
    
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
    
        $status = $request->input('condition');
        $description = $request->input('deskripsi', '');
    
        $qualityData = [
            'lolos' => $status === 'lolos' ? 'lolos' : null,
            'damaged' => $status === 'damaged' ? $description : null,
            'abnormal' => $status === 'abnormal' ? $description : null,
        ];
    
        $inputData = $request->only([
            'code_document',
            'old_barcode_product',
            'new_barcode_product',
            'new_name_product',
            'new_quantity_product',
            'new_price_product',
            'new_date_in_product',
            'new_status_product',
            'new_category_product',
            'new_tag_product'
        ]);
    
        if ($status !== 'lolos') {
            // Set nilai-nilai default jika status bukan 'lolos'
            $inputData['new_status_product'] = 'display';
            $inputData['new_quantity_product'] = 0;
            $inputData['new_price_product'] = 0;
            $inputData['new_category_product'] = null;
            $inputData['new_tag_product'] = null;
            $inputData['new_name_product'] = null;
            $inputData['new_barcode_product'] = null;
        }
    
        $inputData['new_quality'] = json_encode($qualityData);

    
        $new_product->update($inputData);
    
        return new ResponseResource(true, "New Produk Berhasil di Update", $new_product);
    }
    

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(New_product $new_product)
    {
        $new_product->delete();
        return new ResponseResource(true, "data berhasil di hapus", $new_product);
    }

    public function deleteAll(){
        try {
            New_product::truncate();
            return new ResponseResource(true, "data berhasil dihapus", null);
        }catch (\Exception $e){
            return new ResponseResource(false, "terjadi kesalahan saat menghapus data", null);
        }
    }
}
