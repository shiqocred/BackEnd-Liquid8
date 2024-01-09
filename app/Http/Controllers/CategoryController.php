<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Resources\ResponseResource;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function index()
    {
        $categories = Category::all();
        return new ResponseResource(true, "data category", $categories);
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
        $validation = Validator::make($request->all(), [
            'name_category' => 'required',
            'discount_category' => 'required',
            'max_price_category' => 'required',
        ]);

        if($validation->fails()){
            return response()->json(['error' => $validation->errors()], 422);
        }

        $category = Category::create([
            'name_category' => $request['name_category'],
            'discount_category' => $request['discount_category'],
            'max_price_category' => $request['max_price_category']
        ]);
        
        return new ResponseResource(true, "berhasil menambahkan category", $category);

        
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        return new ResponseResource(true, "data category", $category);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Category $category)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        $validation = Validator::make($request->all(), [
            'name_category' => 'required',
            'discount_category' => 'required',
            'max_price_category' => 'required',
        ]);

        if($validation->fails()){
            return response()->json(['error' => $validation->errors(), 422]);
        }
        $category->update($request->all());
        
        return new ResponseResource(true, "berhasil edit category", $category);

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        $category->delete();
        return new ResponseResource(true, "berhasil di hapus", $category);
    }
}
