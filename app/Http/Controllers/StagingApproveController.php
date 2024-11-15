<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Sale;
use App\Models\User;
use App\Models\Bundle;
use App\Models\New_product;
use App\Models\Product_old;
use App\Models\RepairFilter;
use Illuminate\Http\Request;
use App\Models\FilterStaging;
use App\Models\RepairProduct;
use App\Models\Product_Bundle;
use App\Models\ProductApprove;
use App\Models\StagingApprove;
use App\Models\StagingProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Http\Resources\ResponseResource;
use App\Jobs\DeleteDuplicateProductsJob;
use App\Models\BarcodeAbnormal;
use App\Models\BarcodeDamaged;

class StagingApproveController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = $request->input('q');

        $newProducts = StagingApprove::latest()->where(function ($queryBuilder) use ($query) {
            $queryBuilder->where('old_barcode_product', 'LIKE', '%' . $query . '%')
                ->orWhere('new_barcode_product', 'LIKE', '%' . $query . '%')
                ->orWhere('new_tag_product', 'LIKE', '%' . $query . '%')
                ->orWhere('new_category_product', 'LIKE', '%' . $query . '%')
                ->orWhere('new_name_product', 'LIKE', '%' . $query . '%');
        })->whereNotIn('new_status_product', ['dump', 'expired', 'sale', 'migrate', 'repair'])->paginate(100);

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
    public function store(Request $request) {}

    /**
     * Display the specified resource.
     */
    public function show(StagingApprove $stagingApprove)
    {
        return new ResponseResource(true, "data new product", $stagingApprove);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(StagingApprove $stagingApprove)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, StagingApprove $stagingApprove)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $product_filter = StagingApprove::findOrFail($id);
            StagingProduct::create($product_filter->toArray());
            $product_filter->delete();
            DB::commit();
            return new ResponseResource(true, "berhasil menghapus list product bundle", $product_filter);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function stagingTransaction()
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');
        $user = User::with('role')->find(auth()->id());
        DB::beginTransaction();
        try {
            if ($user) {
                if ($user->role && ($user->role->role_name == 'Kasir leader' || $user->role->role_name == 'Admin' || $user->role->role_name == 'Spv')) {

                    $productApproves = StagingApprove::get();

                    foreach ($productApproves as $productApprove) {
                        $duplicate = New_product::where('new_barcode_product', $productApprove->new_barcode_product)->exists();
                        if ($duplicate) {
                            return new ResponseResource(false, "barcoede product di inventory sudah ada : " . $productApprove->new_barcode_product, null);
                        }
                    }

                    $chunkedProductApproves = $productApproves->chunk(100);

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
                                'new_date_in_product' => Carbon::now('Asia/Jakarta')->toDateString(),
                                'new_status_product' => $productApprove->new_status_product,
                                'new_quality' => $productApprove->new_quality,
                                'new_category_product' => $productApprove->new_category_product,
                                'new_tag_product' => $productApprove->new_tag_product,
                                'new_discount' => $productApprove->new_discount,
                                'display_price' => $productApprove->display_price,
                                'type' => $productApprove->type,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];

                            // Hapus data dari StagingApprove
                            $productApprove->delete();
                        }

                        // Masukkan data ke New_product
                        New_product::insert($dataToInsert);
                    }

                    DB::commit();
                    return new ResponseResource(true, 'Transaksi berhasil diapprove', null);
                } else {
                    return new ResponseResource(false, "notification tidak ditemukan", null);
                }
            } else {
                return (new ResponseResource(false, "User tidak dikenali", null))->response()->setStatusCode(404);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return new ResponseResource(false, "Gagal", $e->getMessage());
        }
    }

    public function findSimilarTabel(Request $request)
    {
        // Memperpanjang waktu eksekusi dan batas memori
        set_time_limit(600);
        ini_set('memory_limit', '1024M');
    
        try {
            // Mengambil semua data barcode berdasarkan kode dokumen
            $documents = ['0001/11/2024', '0002/11/2024', '0003/11/2024'];
    
            $barcodes = Product_old::whereIn('code_document', $documents)
                ->pluck('old_barcode_product');
    
            // Menghitung jumlah kemunculan setiap barcode
            $barcodeCounts = array_count_values($barcodes->toArray());
    
            // Filter barcode yang duplikat (lebih dari 1 kemunculan)
            $duplicateBarcodes = array_filter($barcodeCounts, function ($count) {
                return $count > 1;
            });
    
            // Jika ada barcode duplikat, simpan ke dalam tabel BarcodeDamaged
            if (!empty($duplicateBarcodes)) {
                foreach ($duplicateBarcodes as $barcode => $count) {
                    BarcodeDamaged::updateOrCreate(
                        [
                            'code_document' => '0001/11/2024',
                            'old_barcode_product' => $barcode,
                        ],
                        [
                            'occurrences' => $count, // Kolom tambahan jika Anda ingin menyimpan jumlah duplikat
                        ]
                    );
                }
    
                // Mengembalikan respon dengan data barcode duplikat
                return response()->json($duplicateBarcodes );
            }
    
            // Jika tidak ada barcode duplikat
            return response()->json([
                'status' => 'success',
                'message' => 'Tidak ada barcode duplikat.',
                'data' => [],
            ]);
        } catch (\Exception $e) {
            // Menangkap error dan mengembalikan respon error
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memproses data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    public function deleteDuplicateOldBarcodes()
    {
        try {
            // Ambil semua barcode dari BarcodeDamaged berdasarkan kode dokumen tertentu
            $barcodes = BarcodeDamaged::where('code_document', '0001/11/2024')
                ->pluck('old_barcode_product');
    
            // Daftar tabel yang akan dicek
            $tables = [
                New_product::class,
                ProductApprove::class,
                StagingProduct::class,
                StagingApprove::class,
                FilterStaging::class,
            ];
    
            $deletedCount = 0; // Variabel untuk menghitung jumlah record yang dihapus
    
            // Loop melalui setiap tabel
            foreach ($tables as $table) {
                // Hapus record yang memiliki barcode yang sama
                $deletedCount += $table::whereIn('old_barcode_product', $barcodes)->delete();
            }
    
            return response()->json([
                'status' => 'success',
                'message' => 'Berhasil menghapus data duplikat.',
                'deleted_count' => $deletedCount,
            ]);
        } catch (\Exception $e) {
            // Tangkap error dan kembalikan respon error
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menghapus data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
}
