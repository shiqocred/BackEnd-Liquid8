<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Repair;
use App\Models\Notification;
use App\Models\RepairFilter;
use Illuminate\Http\Request;
use App\Models\RepairProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\ResponseResource;
use App\Models\New_product;

class RepairProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $product_repairs = RepairProduct::latest()->paginate(100);
        return new ResponseResource(true, "list product repair", $product_repairs);
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
        set_time_limit(300);
        ini_set('memory_limit', '512M');
        $user = User::find(auth()->id());

        DB::beginTransaction();
        try {
            $product_filters = RepairFilter::all();
            if ($product_filters->isEmpty()) {
                return new ResponseResource(false, "Tidak ada produk filter yang tersedia saat ini", $product_filters);
            }

            $repair = Repair::create([
                'user_id' => $user->id,
                'repair_name' => $request->repair_name,
                'total_price' => $request->total_price,
                'total_custom_price' => $request->total_custom_price,
                'total_products' => $request->total_products,
                'product_status' => 'not sale',
                'barcode' => $request->barcode,
            ]);

            $insertData = $product_filters->map(function ($product) use ($repair) {
                return [
                    'repair_id' => $repair->id,
                    'code_document' => $product->code_document,
                    'old_barcode_product' => $product->old_barcode_product,
                    'new_barcode_product' => $product->new_barcode_product,
                    'new_name_product' => $product->new_name_product,
                    'new_quantity_product' => $product->new_quantity_product,
                    'new_price_product' => $product->new_price_product,
                    'old_price_product' => $product->old_price_product,
                    'new_date_in_product' => $product->new_date_in_product,
                    'new_status_product' => 'repair',
                    'new_quality' => $product->new_quality,
                    'new_category_product' => $product->new_category_product,
                    'new_tag_product' => $product->new_tag_product
                ];
            })->toArray();

            RepairProduct::insert($insertData);

            RepairFilter::query()->delete();

            $keterangan = Notification::create([
                'user_id' => $user->id,
                'notification_name' => 'Butuh Approvement untuk Repair',
                'role' => 'Spv',
                'read_at' => Carbon::now('Asia/Jakarta'),
                'riwayat_check_id' => null,
                'repair_id' => $repair->id
            ]);

            DB::commit();
            return new ResponseResource(true, "repair berhasil dibuat", [$repair, $keterangan]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Gagal membuat repair: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal memindahkan product ke repair', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(RepairProduct $repairProduct)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(RepairProduct $repairProduct)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, RepairProduct $repairProduct)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RepairProduct $repairProduct)
    {
        //
    }

    public function updateRepair($id)
    {
        // Mulai transaksi database
        DB::beginTransaction();

        try {
            $product = RepairProduct::find($id);
            $repair = Repair::where('id', $product->repair_id)->first();


            if ($product->new_status_product == 'dump') {
                // Batalkan transaksi dan kembalikan respons
                DB::rollback();
                return new ResponseResource(false, "status product sudah dump", $product);
            }
            if (!$product) {
                // Batalkan transaksi dan kembalikan respons
                DB::rollback();
                return new ResponseResource(false, "Produk tidak ditemukan", null);
            }

            $totalQuantity = $repair->total_products - 1;
            $totalPrice = $repair->total_price - $product->new_price_product;
            $totalCustomPrice = $repair->total_custom_price - $product->new_price_product;
            $user = User::find(auth()->id());
            // Perbarui entri Repair
            $repairProduct = Repair::where('id', $product->repair_id)->update([
                "user_id" => $user->id,
                "repair_name" => $repair->repair_name,
                "total_price" => $totalPrice,
                "total_custom_price" => $totalCustomPrice,
                "total_products" => $totalQuantity,
                "product_status" => $repair->product_status,
                "barcode" => $repair->barcode,
            ]);
            
            $getRepairProduct = Repair::find($product->repair_id);

            // Perbarui status produk menjadi 'dump'
            $product->update(['new_status_product' => 'dump']);

            New_product::create([
                "code_document" => $product->code_document,
                "old_barcode_product" => $product->old_barcode_product,
                "new_barcode_product" => $product->new_barcode_product,
                "new_name_product" => $product->new_name_product,
                "new_quantity_product" => $product->new_quantity_product,
                "new_price_product" => $product->new_price_product,
                "old_price_product" => $product->old_price_product,
                "new_date_in_product" => $product->new_date_in_product,
                "new_status_product" => $product->new_status_product,
                "new_quality" => $product->new_quality,
                "new_category_product" => $product->new_category_product,
                "new_tag_product" => $product->new_tag_product,
            ]);

            // Hancurkan objek produk
            $product->delete();

            // Commit transaksi karena operasi-operasi database berhasil
            DB::commit();

            return new ResponseResource(true, "data product sudah di update", $getRepairProduct);
        } catch (\Exception $e) {
            // Batalkan transaksi dan kembalikan respons jika terjadi kesalahan
            DB::rollback();
            return new ResponseResource(false, "Terjadi kesalahan saat memperbarui produk", null);
        }
    }
}
