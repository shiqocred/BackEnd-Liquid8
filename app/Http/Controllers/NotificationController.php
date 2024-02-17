<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use App\Http\Resources\ResponseResource;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $notifications = Notification::latest()->paginate(100);

        return new ResponseResource(true, "list notification", $notifications);
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
    public function show(Notification $notification)
    {
        if(!$notification){
            return new ResponseResource(false, "id notification tidak terdaftar", $notification);
        }
        return new ResponseResource(true, "detail notification", $notification);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Notification $notification)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Notification $notification)
    {
        $user = User::find(auth()->id());

        if (!$user) {
            $resource = new ResponseResource(false, "User tidak dikenali", null);
            return $resource->response()->setStatusCode(422);
        }

        $validator = Validator::make($request->all(), [
            'notification_name' => 'required',
            'status' => 'required|in:pending,done'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }


        $notification->update([
            'notification_name' => $request->notification_name,
            'status' => $request->status 
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Notification $notification)
    {
        try{
            $notification->delete();
            return new ResponseResource(true, "berhasil di hapus", null);
        }catch(\Exception $e){
            return response()->json(["error" => $e], 402);
        }
    }

    public function approveTransaction($userId, $notificationId)
    {
        // Cari user berdasarkan ID, ini opsional kecuali kamu memerlukan data user nantinya
        $user = User::find($userId);
    
        // Pastikan user tersebut ada
        if (!$user) {
            return response()->json(['error' => 'User tidak ditemukan'], 404);
        }

        $notification = Notification::where('id', $notificationId)->first();
        
        if (!$notification) {
            return response()->json(['error' => 'Transaksi tidak ditemukan'], 404);
        }
        if ($notification->status == 'done') {
            return response()->json(['message' => 'Transaksi sudah disetujui sebelumnya'], 200);
        }
    
        $notification->update(['status' => 'done']);
    
        return new ResponseResource(true,'Transaksi berhasil diapprove', $notification);
    }

    public function notificationByRole(){

        $userId = User::find(auth()->id());

        if (!$userId) {
            $resource = new ResponseResource(false, "User tidak dikenali", null);
            return $resource->response()->setStatusCode(422);
        }

        $notifByRole = Notification::where('user_id', $userId)->get();

        if ($notifByRole->isEmpty()) {
            return new ResponseResource(false, "Tidak ada data", null);
        }

        return new ResponseResource(true, "list notif user", $notifByRole);
    }
    
}
