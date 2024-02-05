<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\SpecialTransaction;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\ResponseResource;

class SpecialTransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $transactions = SpecialTransaction::latest()->with('users')->pagiante(50);
        return new ResponseResource(true, "list task crew", $transactions);
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
    public function show(SpecialTransaction $specialTransaction)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SpecialTransaction $specialTransaction)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SpecialTransaction $specialTransaction)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SpecialTransaction $specialTransaction)
    {
        //
    }

    public function approveTransaction($userId, $transactionId)
    {
        // Cari user berdasarkan ID, ini opsional kecuali kamu memerlukan data user nantinya
        $user = User::find($userId);
    
        // Pastikan user tersebut ada
        if (!$user) {
            return response()->json(['error' => 'User tidak ditemukan'], 404);
        }

        $transaction = SpecialTransaction::where('id', $transactionId)->first();
        
        // Jika transaksi tidak ditemukan, kembalikan error
        if (!$transaction) {
            return response()->json(['error' => 'Transaksi tidak ditemukan'], 404);
        }
        if ($transaction->status == 'done') {
            return response()->json(['message' => 'Transaksi sudah disetujui sebelumnya'], 200);
        }
    
        $transaction->update(['status' => 'done']);
    
        // Kembalikan response sukses
        return response()->json(['message' => 'Transaksi berhasil diapprove'], 200);
    }
    
}
