<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Models\Destination;
use Illuminate\Http\Request;

class DestinationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = $request->input('q');
        $destinations = Destination::latest();
        if($query){
            $destinations = $destinations->where('shop_name', 'LIKE', '%' . $query . 'LIKE');
        }
        $destinations = $destinations->paginate(50);
        return new ResponseResource(true, "list Destinasi Toko", $destinations );
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
        $request->validate([
            'shop_name' => 'required|string|max:255|unique:destinations,shop_name',
            'phone_number' => 'required|string|max:15',
            'alamat' => 'required|string',
        ]);

        $destination = Destination::create($request->all());
        return new ResponseResource(true, "Destination added successfully", $destination);
    }

    /**
     * Display the specified resource.
     */
    public function show(Destination $destination)
    {
        return new ResponseResource(true, "Detail Destination", $destination); 
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Destination $destination)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Destination $destination)
    {
        $request->validate([
            'shop_name' => 'required|string|max:255|unique:destinations,shop_name,' . $destination->id,
            'phone_number' => 'required|string|max:15',
            'alamat' => 'required|string',
        ]);

        $destination->update($request->all());
        return new ResponseResource(true, "updated destionation successfully", $destination);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Destination $destination)
    {
        $destination->delete();
        return new ResponseResource(true, "Destination deleted successfully", $destination);
    }
}
