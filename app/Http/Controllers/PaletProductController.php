<?php

namespace App\Http\Controllers;

use App\Models\PaletProduct;
use Illuminate\Http\Request;
use App\Http\Resources\ResponseResource;

class PaletProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $product_palets = PaletProduct::latest()->paginate(100);
        return new ResponseResource(true, "list product palet", $product_palets);
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
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(PaletProduct $paletProduct)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PaletProduct $paletProduct)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PaletProduct $paletProduct)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaletProduct $paletProduct)
    {
        //
    }
}
