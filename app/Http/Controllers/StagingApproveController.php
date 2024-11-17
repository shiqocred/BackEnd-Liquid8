<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Models\BarcodeDamaged;
use App\Models\FilterStaging;
use App\Models\New_product;
use App\Models\ProductApprove;
use App\Models\Product_old;
use App\Models\StagingApprove;
use App\Models\StagingProduct;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
    public function store(Request $request)
    {}

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
        // Validasi input dokumen dari request
        $documents = $request->input('documents'); // Mengambil array dokumen dari request
    
        if (is_null($documents) || !is_array($documents)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Parameter documents harus berupa array.',
            ], 400);
        }
    
        // Memperpanjang waktu eksekusi dan batas memori
        set_time_limit(600);
        ini_set('memory_limit', '1024M');
    
        try {
            // Mengambil semua data barcode berdasarkan kode dokumen
            $barcodes = Product_old::whereIn('code_document', $documents)
                ->pluck('old_barcode_product');
    
            // Jika tidak ada barcode ditemukan
            if ($barcodes->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Tidak ada barcode yang ditemukan untuk dokumen yang diberikan.',
                    'data' => [],
                ]);
            }
    
            // Menghitung jumlah kemunculan setiap barcode
            $barcodeCounts = array_count_values($barcodes->toArray());
    
            // Filter barcode yang duplikat (lebih dari 1 kemunculan)
            $duplicateBarcodes = array_filter($barcodeCounts, function ($count) {
                return $count > 1;
            });
    
            // Jika ada barcode duplikat, simpan ke dalam tabel BarcodeDamaged
            if (!empty($duplicateBarcodes)) {
                foreach ($duplicateBarcodes as $barcode => $count) {
                    // Cari dokumen mana saja yang memiliki barcode tersebut
                    foreach ($documents as $document) {
                        $existsInDocument = Product_old::where('code_document', $document)
                            ->where('old_barcode_product', $barcode)
                            ->exists();
    
                        if ($existsInDocument) {
                            BarcodeDamaged::updateOrCreate(
                                [
                                    'code_document' => $document, // Simpan dokumen terkait barcode
                                    'old_barcode_product' => $barcode,
                                ],
                                [
                                    'occurrences' => $count, // Menyimpan jumlah duplikasi
                                ]
                            );
                        }
                    }
                }
    
                // Mengembalikan respon dengan data barcode duplikat
                return response()->json([
                    'status' => 'success',
                    'message' => 'Barcode duplikat berhasil diproses.',
                    'data' => $duplicateBarcodes,
                ]);
            }
    
            // Jika tidak ada barcode duplikat
            return response()->json([
                'status' => 'success',
                'message' => 'Tidak ada barcode duplikat yang ditemukan.',
                'data' => [],
            ]);
        } catch (\Exception $e) {
          
            // Mengembalikan respon error
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memproses data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    

    public function deleteDuplicateOldBarcodes(Request $request)
    {
        try {
            // Ambil semua barcode dari BarcodeDamaged berdasarkan kode dokumen tertentu
            $barcodes = BarcodeDamaged::where('code_document', $request->input('code_document'))
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
                foreach ($barcodes as $barcode) {
                    // Ambil ID dari satu record dengan ID terkecil (jika ada)
                    $recordToKeep = $table::where('old_barcode_product', $barcode)
                        ->orderBy('created_at', 'asc') // Urutkan ID untuk mendapatkan ID terkecil
                        ->value('id');

                    if ($recordToKeep) {
                        // Hapus semua record, termasuk yang hanya satu, kecuali jika ada data untuk disisakan
                        $deletedCount += $table::where('old_barcode_product', $barcode)
                            ->where('created_at', '!=', $recordToKeep) // Jika ada ID untuk disisakan
                            ->delete();

                        // Tetap hapus yang terakhir jika cuma satu
                        $deletedCount += $table::where('id', $recordToKeep)->delete();
                    }
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Berhasil menghapus semua data duplikat untuk setiap barcode.',
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
