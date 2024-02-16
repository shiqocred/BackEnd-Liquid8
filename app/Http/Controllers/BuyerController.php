<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Models\Buyer;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BuyerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (request()->has('q')) {
            $buyer = Buyer::when(request()->q, function ($query) {
                $query
                    ->where('name_buyer', 'like', '%' . request()->q . '%')
                    ->orWhere('phone_buyer', 'like', '%' . request()->q . '%')
                    ->orWhere('address_buyer', 'like', '%' . request()->q . '%');
            })->latest()->paginate(10);
        } else {
            $buyer = Buyer::latest()->paginate(10);
        }
        $resource = new ResponseResource(true, "List data buyer", $buyer);
        return $resource->response();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name_buyer' => 'required',
                'phone_buyer' => 'required|numeric',
                'address_buyer' => 'required',
            ]
        );

        if ($validator->fails()) {
            $resource = new ResponseResource(false, "Input tidak valid!", $validator->errors());
            return $resource->response()->setStatusCode(422);
        }
        try {
            $buyer = Buyer::create($request->all());
            $resource = new ResponseResource(true, "Data berhasil ditambahkan!", $buyer);
        } catch (Exception $e) {
            $resource = new ResponseResource(false, "Data gagal ditambahkan!", $e->getMessage());
        }

        return $resource->response();
    }

    /**
     * Display the specified resource.
     */
    public function show(Buyer $buyer)
    {
        $resource = new ResponseResource(true, "Data buyer", $buyer);
        return $resource->response();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Buyer $buyer)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name_buyer' => 'required',
                'phone_buyer' => 'required|numeric',
                'address_buyer' => 'required',
            ]
        );

        if ($validator->fails()) {
            $resource = new ResponseResource(false, "Input tidak valid!", $validator->errors());
            return $resource->response()->setStatusCode(422);
        }
        try {
            $buyer->update($request->all());
            $resource = new ResponseResource(true, "Data berhasil ditambahkan!", $buyer);
        } catch (Exception $e) {
            $resource = new ResponseResource(false, "Data gagal ditambahkan!", $e->getMessage());
        }

        return $resource->response();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Buyer $buyer)
    {
        try {
            $buyer->delete();
            $resource = new ResponseResource(true, "Data berhasil di hapus!", $buyer);
        } catch (\Exception $e) {
            $resource = new ResponseResource(false, "Data gagal di hapus!", $e->getMessage());
        }
        return $resource->response();
    }
}
