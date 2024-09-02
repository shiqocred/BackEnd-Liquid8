<?php

namespace App\Http\Controllers;

use App\Models\ProductScan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\ResponseResource;
use Illuminate\Support\Facades\Validator;

class ProductScanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = $request->input('q');
        if ($query) {
            $productScan = ProductScan::latest()
                ->where('product_name', 'LIKE', '%' . $query . '%')
                ->paginate(20);
        } else {
            $productScan = ProductScan::latest()->paginate(20);
        }
    
        return new ResponseResource(true, "list products scan", $productScan);
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
        // Validasi Input
        $validator = Validator::make($request->all(), [
            'product_name' => 'required|string',
            'product_price' => 'required|numeric',
        ]);
    
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
    
        DB::beginTransaction();
    
        try {
            $productScan = ProductScan::create([
                'user_id' => auth()->id(),
                'product_name' => $request['product_name'],
                'product_price' => $request['product_price'],
            ]);
    
            DB::commit(); // Commit setelah create berhasil
    
            return new ResponseResource(true, "berhasil menambah data scan", $productScan);
    
        } catch (\Exception $e) {
            DB::rollback(); // Rollback jika terjadi error
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Display the specified resource.
     */
    public function show(ProductScan $productScan)
    {
        return new ResponseResource(true, "detail data scan", $productScan);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProductScan $productScan)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProductScan $productScan)
    {
        $validator = Validator::make($request->all(), [
            'product_name' => 'required|string',
            'product_price' => 'required|numeric',
        ]);
    
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        DB::beginTransaction();
        try{
            $productScan->update([
                'product_name' => $request->input('product_name'),
                'product_price' => $request->input('product_price')
            ]);
            DB::commit();
            return new ResponseResource(true, "berhasil di update", $productScan);
        }catch(\Exception $e){
            DB::rollback();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProductScan $productScan)
    {
        try{
            if($productScan){
                $productScan->delete();
                return new ResponseResource(true, "berhasil di hapus", null);
            }
        }catch(\Exception $e){
            return response()->json(['error' => $e->getMessage()], 500);
        }
       
    }
}
