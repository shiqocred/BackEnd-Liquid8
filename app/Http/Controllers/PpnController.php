<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Models\Ppn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PpnController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $ppns = Ppn::latest()->get();

            return new ResponseResource(
                true,
                "Berhasil mengambil list PPN",
                $ppns
            );
        } catch (\Exception $e) {
            return (new ResponseResource(
                false,
                "Gagal mengambil data PPN",
                null
            ))->response()->setStatusCode(500);
        }
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
        try {
            $validator = Validator::make($request->all(), [
                'ppn' => 'required|numeric|unique:ppns,ppn'
            ]);

            if ($validator->fails()) {
                return (new ResponseResource(
                    false,
                    "Validasi gagal",
                    $validator->errors()
                ))->response()->setStatusCode(422);
            }

            $ppn = Ppn::create([
                'ppn' => $request->ppn
            ]);

            return new ResponseResource(
                true,
                "Berhasil menambah data PPN",
                $ppn
            );
        } catch (\Exception $e) {
            return (new ResponseResource(
                false,
                "Gagal menambah data PPN",
                null
            ))->response()->setStatusCode(500);
        }
    }
    /**
     * Display the specified resource.
     */
    public function show(Ppn $ppn)
    {
        return new ResponseResource(true, "data ppn", $ppn);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Ppn $ppn)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $ppn = Ppn::find($id);

            if (!$ppn) {
                return (new ResponseResource(
                    false,
                    "Data PPN tidak ditemukan",
                    null
                ))->response()->setStatusCode(404);
            }

            $validator = Validator::make($request->all(), [
                'ppn' => 'required|numeric|unique:ppns,ppn,' . $id
            ]);

            if ($validator->fails()) {
                return (new ResponseResource(
                    false,
                    "Validasi gagal",
                    $validator->errors()
                ))->response()->setStatusCode(422);
            }

            $ppn->update([
                'ppn' => $request->ppn
            ]);

            return new ResponseResource(
                true,
                "Berhasil mengupdate data PPN",
                $ppn
            );
        } catch (\Exception $e) {
            return (new ResponseResource(
                false,
                "Gagal mengupdate data PPN",
                null
            ))->response()->setStatusCode(500);
        }
    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $ppn = Ppn::find($id);

            if (!$ppn) {
                return (new ResponseResource(
                    false,
                    "Data PPN tidak ditemukan",
                    null
                ))->response()->setStatusCode(404);
            }

            $ppn->delete();

            return new ResponseResource(
                true,
                "Berhasil menghapus data PPN",
                null
            );
        } catch (\Exception $e) {
            return (new ResponseResource(
                false,
                "Gagal menghapus data PPN",
                null
            ))->response()->setStatusCode(500);
        }
    }
}
