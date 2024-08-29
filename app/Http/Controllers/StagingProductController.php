<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Document;
use App\Models\Notification;
use App\Models\RiwayatCheck;
use Illuminate\Http\Request;
use App\Models\StagingProduct;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\ResponseResource;
use Illuminate\Support\Facades\Validator;

class StagingProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $searchQuery = $request->input('q');

        $newProducts = StagingProduct::latest()
            ->where(function ($queryBuilder) use ($searchQuery) {
                $queryBuilder->where('old_barcode_product', 'LIKE', '%' . $searchQuery . '%')
                    ->orWhere('new_barcode_product', 'LIKE', '%' . $searchQuery . '%')
                    ->orWhere('new_category_product', 'LIKE', '%' . $searchQuery . '%')
                    ->orWhere('new_name_product', 'LIKE', '%' . $searchQuery . '%');
            })
            ->whereNotIn('new_status_product', ['dump', 'expired', 'sale', 'migrate', 'repair'])
            ->whereNull('new_tag_product')
            ->paginate(50);

        return new ResponseResource(true, "list new product", $newProducts);
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
    public function show(StagingProduct $stagingProduct)
    {
        return new ResponseResource(true, "data new product", $stagingProduct);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(StagingProduct $stagingProduct)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, StagingProduct $stagingProduct)
    {
        $validator = Validator::make($request->all(), [
            'code_document' => 'required',
            'old_barcode_product' => 'required',
            'new_barcode_product' => 'required',
            'new_name_product' => 'required',
            'new_quantity_product' => 'required|integer',
            'new_price_product' => 'required|numeric',
            'old_price_product' => 'required|numeric',
            'new_status_product' => 'required|in:display,expired,promo,bundle,palet,dump,sale,migrate',
            'condition' => 'required|in:lolos,damaged,abnormal',
            'new_category_product' => 'nullable',
            'new_tag_product' => 'nullable|exists:color_tags,name_color',
            'new_discount',
            'display_price'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $status = $request->input('condition');
        $description = $request->input('deskripsi', '');

        $qualityData = [
            'lolos' => $status === 'lolos' ? 'lolos' : null,
            'damaged' => $status === 'damaged' ? $description : null,
            'abnormal' => $status === 'abnormal' ? $description : null,
        ];

        $inputData = $request->only([
            'code_document',
            'old_barcode_product',
            'new_barcode_product',
            'new_name_product',
            'new_quantity_product',
            'new_price_product',
            'old_price_product',
            'new_date_in_product',
            'new_status_product',
            'new_category_product',
            'new_tag_product',
            'new_discount',
            'display_price'
        ]);

        $indonesiaTime = Carbon::now('Asia/Jakarta');
        $inputData['new_date_in_product'] = $indonesiaTime->toDateString();

        if ($status !== 'lolos') {
            // Set nilai-nilai default jika status bukan 'lolos'
            $inputData['new_price_product'] = null;
            $inputData['new_category_product'] = null;
        }

        $inputData['new_quality'] = json_encode($qualityData);

        $stagingProduct->update($inputData);

        return new ResponseResource(true, "New Produk Berhasil di Update", $stagingProduct);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(StagingProduct $stagingProduct)
    {
        //
    }

    public function addStagingToSpv(Request $request)
    {
        DB::beginTransaction();
        $user = auth()->user();
        try {
            $riwayat_check = RiwayatCheck::where('code_document', $request['code_document'])->first();
            if ($riwayat_check->status_approve == 'done') {
                $notif_count = Notification::where('riwayat_check_id', $riwayat_check->id)
                    ->where('status', 'staging')
                    ->count();
                if ($notif_count >= 1) {
                    $response = new ResponseResource(false, "Data sudah ada", null);
                    return $response->response()->setStatusCode(422);
                } else {
                    //keterangan transaksi
                    $keterangan = Notification::create([
                        'user_id' => $user->id,
                        'notification_name' => 'butuh approvemend untuk product staging',
                        'role' => 'Spv',
                        'read_at' => Carbon::now('Asia/Jakarta'),
                        'riwayat_check_id' => $riwayat_check->id,
                        'repair_id' => null,
                        'status' => 'staging'
                    ]);
                    $riwayat_check->update(['status_approve', 'staging']);
                    DB::commit();
                }
            }
            return new ResponseResource(true, "Data berhasil ditambah", [
                $riwayat_check,
                $keterangan
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            $resource = new ResponseResource(false, "Data gagal ditambahkan, terjadi kesalahan pada server : " . $e->getMessage(), null);
            $resource->response()->setStatusCode(500);
        }
    }

    public function documentsApproveStaging(Request $request)
    {
        $query = $request->input('q');

        // Mengambil data dari tabel notifications yang berkaitan dengan riwayat_check
        $notifQuery = Notification::with('riwayat_check')->where('status', 'staging')
            ->whereHas('riwayat_check', function ($q) use ($query) {
                if (!empty($query)) {
                    $q->where('status_approve', $query);
                } else {
                    $q->where('status_approve', 'done');
                }
            })
            ->latest();

        // Eksekusi query dan kembalikan hasilnya
        $notifications = $notifQuery->get();

        return new ResponseResource(true, "List of documents in staging", $notifications);
    }

    public function productStagingByDoc(Request $request, $code_document)
    {
        $query = $request->input('q');
        $user = User::with('role')->find(auth()->id());

        if ($user) {
            $productsQuery = StagingProduct::where('code_document', $code_document);

            if (!empty($query)) {
                $productsQuery->where('new_name_product', 'LIKE', '%' . $query . '%');
            }

            $products = $productsQuery->paginate(50);

            return new ResponseResource(true, 'products', $products);
        } else {
            return (new ResponseResource(false, "User tidak dikenali", null))->response()->setStatusCode(404);
        }
    }


    public function documentStagings(Request $request)
    {
        $query = $request->input('q');

        // Mengambil data notifikasi yang statusnya 'staging' dan menyertakan relasi 'riwayat_check'
        $notifQuery = Notification::with('riwayat_check')->where('status', 'staging')->latest();

        // Jika query tidak kosong, lakukan pencarian berdasarkan 'base_document' atau 'code_document'
        if (!empty($query)) {
            $notifQuery->whereHas('riwayat_check', function ($q) use ($query) {
                $q->where('base_document', $query)
                    ->orWhere('code_document', $query); // Memperbaiki typo dari 'cpde_document' menjadi 'code_document'
            });
        } else {
            // Jika tidak ada query, lakukan pencarian berdasarkan 'status_approve' dengan nilai 'pending' atau 'done'
            $notifQuery->whereHas('riwayat_check', function ($q) {
                $q->where('status_approve', 'pending')
                    ->orWhere('status_approve', 'done');
            });
        }

        // Ambil semua data yang sesuai
        $documents = $notifQuery->get();

        return new ResponseResource(true, "Document Approves", $documents);
    }
}
