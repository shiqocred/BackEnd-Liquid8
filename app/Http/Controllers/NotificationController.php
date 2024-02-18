<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use App\Http\Resources\ResponseResource;
use App\Models\RiwayatCheck;
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
            return new ResponseResource(false, "id notification tidak terdaftar", null);
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

    public function approveTransaction($notificationId)
    {
        $user = User::with('role')->find(auth()->id());

        if ($user) {
            if ($user->role && $user->role->role_name == 'Spv') {
                $notification = Notification::where('id', $notificationId)->first();
        
                if (!$notification) {
                    return response()->json(['error' => 'Transaksi tidak ditemukan'], 404);
                }
                if ($notification->status == 'done') {
                    return response()->json(['message' => 'Transaksi sudah disetujui sebelumnya'], 200);
                }
            
                $notification->update([
                    'notification_name' => 'Approved',
                    'status' => 'done',
                ]);
        
                $riwayatCheck = RiwayatCheck::where('id', $notification->riwayat_check_id)->first();
        
                $riwayatCheck->update(['status_approve' => 'done']);
            
                return new ResponseResource(true,'Transaksi berhasil diapprove', $notification);
            } else {
                return new ResponseResource(false, "notification tidak di temukan", null);
            }
        }else {
            return (new ResponseResource(false, "User tidak dikenali", null))->response()->setStatusCode(404);
        }
    }

    public function getNotificationByRole(){

        $user = User::with('role')->find(auth()->id());

        if ($user) {
            if ($user->role && $user->role->role_name == 'Spv') {
                $notifSpv = Notification::where('spv_id', $user->id)->get();
                return new ResponseResource(true, "Supervisor Approval Notification", $notifSpv);
            } else if ($user->role && $user->role->role_name == 'Crew') {
                $notifCrew = Notification::where('user_id', $user->id)->get();
                return new ResponseResource(true, "Approval Notification from Supervisor", $notifCrew);
            }else {
                $notifReparasi = Notification::where('user_id', $user->id)->get();
                return new ResponseResource(true, "Approval Notification from Supervisor", $notifReparasi);
            }
        }else {
            return (new ResponseResource(false, "User tidak dikenali", null))->response()->setStatusCode(404);
        }

   }
   public function getNotificationByRole2(){

    $user = User::with('role')->find(auth()->id());

    if ($user) {
        if ($user->role && $user->role->role_name == 'Spv') {
            $notifSpv = Notification::where('spv_id', $user->id)->get();
            return new ResponseResource(true, "Supervisor Approval Notification", $notifSpv);
        } else if ($user->role && $user->role->role_name == 'Crew') {
            $notifCrew = Notification::where('user_id', $user->id)->get();
            return new ResponseResource(true, "Approval Notification from Supervisor", $notifCrew);
        }else {
            $notifReparasi = Notification::where('user_id', $user->id)->get();
            return new ResponseResource(true, "Approval Notification from Supervisor", $notifReparasi);
        }
    }else {
        return (new ResponseResource(false, "User tidak dikenali", null))->response()->setStatusCode(404);
    }

}

}
