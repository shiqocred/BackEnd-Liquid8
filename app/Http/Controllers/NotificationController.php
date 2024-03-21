<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Repair;
use App\Models\New_product;
use App\Models\Notification;
use App\Models\RiwayatCheck;
use Illuminate\Http\Request;
use App\Models\ProductApprove;
use Illuminate\Support\Facades\DB;
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
        if (!$notification) {
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
        try {
            $notification->delete();
            return new ResponseResource(true, "berhasil di hapus", null);
        } catch (\Exception $e) {
            return response()->json(["error" => $e], 402);
        }
    }

    public function approveTransaction($notificationId)
    {
        set_time_limit(300); 
        ini_set('memory_limit', '512M'); 
        $user = User::with('role')->find(auth()->id());

        DB::beginTransaction();

        try {
            if ($user) {
                if ($user->role && $user->role->role_name == 'Spv') {
                    $notification = Notification::where('id', $notificationId)->first();

                    if (!$notification) {
                        return response()->json(['error' => 'Transaksi tidak ditemukan'], 404);
                    }

                    // if ($notification->status == 'done') {
                    //     return response()->json(['message' => 'Transaksi sudah disetujui sebelumnya'], 200);
                    // }

                    $notification->update([
                        'notification_name' => 'Approved',
                        'status' => 'done',
                    ]);
                    
                    if ($notification->riwayat_check_id !== null) {
                        $riwayatCheck = RiwayatCheck::find($notification->riwayat_check_id);
                    
                        if ($riwayatCheck) {
                            $riwayatCheck->update(['status_approve' => 'done']);
                    
                            $productApproves = ProductApprove::where('code_document', $riwayatCheck->code_document)->get();
                    
                            $chunkedProductApproves = $productApproves->chunk(200); 
                    
                            foreach ($chunkedProductApproves as $chunk) {
                                $dataToInsert = [];
                    
                                foreach ($chunk as $productApprove) {
                                    $dataToInsert[] = [
                                        'code_document' => $productApprove->code_document,
                                        'old_barcode_product' => $productApprove->old_barcode_product,
                                        'new_barcode_product' => $productApprove->new_barcode_product,
                                        'new_name_product' => $productApprove->new_name_product,
                                        'new_quantity_product' => $productApprove->new_quantity_product,
                                        'new_price_product' => $productApprove->new_price_product,
                                        'old_price_product' => $productApprove->old_price_product,
                                        'new_date_in_product' => $productApprove->new_date_in_product,
                                        'new_status_product' => $productApprove->new_status_product,
                                        'new_quality' => $productApprove->new_quality,
                                        'new_category_product' => $productApprove->new_category_product,
                                        'new_tag_product' => $productApprove->new_tag_product,
                                    ];
                    
                                    $productApprove->delete();
                                }

                                New_product::insert($dataToInsert);
                            }
                        }
                    }
                    

                    //ini untuk orang yang ngirim product repair
                    $repairCheck = Repair::where('user_id', $notification->user_id )->first();

                    if ($repairCheck) {
                        $repairCheck->update(['status_approve' => 'done']);
                    
                        $productFilter = $repairCheck->repair_products;
                    
                        foreach ($productFilter as $product) {
                            New_product::create([
                                'code_document' => $product->code_document,
                                'old_barcode_product' => $product->old_barcode_product,
                                'new_barcode_product' => $product->new_barcode_product,
                                'new_name_product' => $product->new_name_product,
                                'new_quantity_product' => $product->new_quantity_product,
                                'new_price_product' => $product->new_price_product,
                                'new_date_in_product' => $product->new_date_in_product,
                                'new_status_product' => 'display',
                                'new_quality' => $product->new_quality,
                                'new_category_product' => $product->new_category_product,
                                'new_tag_product' => $product->new_tag_product
                            ]);
                    
                            // Hapus produk terkait dari repair_products sebelum menghapus repairCheck
                            $product->delete();
                        }
                    
                        // Setelah memastikan semua produk terkait dihapus, barulah repairCheck dihapus
                        $repairCheck->delete();
                    }
                    
                    DB::commit();
                    return new ResponseResource(true, 'Transaksi berhasil diapprove', $notification);
                } else {
                    return new ResponseResource(false, "notification tidak di temukan", null);
                }
            } else {
                return (new ResponseResource(false, "User tidak dikenali", null))->response()->setStatusCode(404);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return new ResponseResource(false, "gagal", $e->getMessage());
        }
    }
    // 5|3ucHQClHzI2EGz06w7Do81XWfuI8PeZgkiOT2oKda81a78d9
    //6|VUvwtvBmx9W1dACcufOwoZkc26AMf4i6r88Cxbuf990822a3
    public function getNotificationByRole(Request $request)
    {
        $query = $request->input('q');
        $user = User::with('role')->find(auth()->id());
        if ($user) {
            $notifQuery = Notification::query();
            if ($user->role && $user->role->role_name == 'Spv') {
                $notifQuery->where('role', $user->role->role_name);
            } elseif ($user->role && $user->role->role_name == 'Crew') {
                $notifQuery->where('user_id', $user->id);
            } else {
                $notifQuery->where('user_id', $user->id); 
            }
    
            if (!empty($query)) {
                $notifQuery->where('status', 'LIKE', '%' . $query . '%');
            }
    
            $notifPaginated = $notifQuery->paginate(50);
    
            $userIds = $notifPaginated->pluck('user_id')->unique();
    
            $roles = User::whereIn('id', $userIds)->with('role')->get()->pluck('role.role_name', 'id');
    
            $role_id = $user->role->id;  
    
            $notifPaginated->getCollection()->transform(function ($notification) use ($roles, $role_id) {
                $notification->role_name = $roles[$notification->user_id] ?? null;
                $notification->role_id = $role_id;  
                return $notification;
            });
    
            return new ResponseResource(true, "Notifications", $notifPaginated);
            
        } else {
            return (new ResponseResource(false, "User tidak dikenali", null))->response()->setStatusCode(404);
        }
    }
    

}
