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

    public function countBast(Request $request)
    {
        // Memperpanjang waktu eksekusi dan batas memori
        set_time_limit(600);
        ini_set('memory_limit', '1024M');
        $inventory = New_product::where('code_document', '0130/10/2024')
            ->select('old_barcode_product')->get();

        $stagings = StagingProduct::where('code_document', '0130/10/2024')
            ->select('old_barcode_product')->get();

        $stagingApproves = StagingApprove::where('code_document', '0130/10/2024')
            ->select('old_barcode_product')->get();

        $filterStagings = FilterStaging::where('code_document', '0130/10/2024')
            ->select('old_barcode_product')->get();

        $productBundle = Product_Bundle::where('code_document', '0130/10/2024')
            ->select('old_barcode_product')->get();

        $sales = Sale::where('code_document', '0130/10/2024')->select('code_document')->get();

        $productApprove = ProductApprove::where('code_document', '0130/10/2024')
            ->select('old_barcode_product')->get();

        $repairFilter = RepairFilter::where('code_document', '0130/10/2024')
            ->select('old_barcode_product')->get();

        $repairProduct = RepairProduct::where('code_document', '0130/10/2024')
            ->select('old_barcode_product')->get();

        $allData = count($inventory) + count($stagings) + count($filterStagings) + count($productBundle)
            + count($productApprove) + count($repairFilter) + count($repairProduct) + count($sales) + count($stagingApproves);

        // Cek duplikasi di dalam $combined dan $product_all
        // $duplicates_combined = $combined->duplicates();
        // $duplicates_product_all = $product_all->duplicates();

        // Menampilkan hasil debugging
        return [
            'total_product_all' => $allData,

        ];
    }


    public function findSimilarTabel(Request $request)
    {
        $product_olds = Product_old::where('code_document', '0001/11/2024')->pluck('old_barcode_product');

        // Menghitung jumlah kemunculan setiap barcode
        $barcodeCounts = array_count_values($product_olds->toArray());

        // Mencari barcode yang memiliki duplikat (kemunculan lebih dari 1)
        $duplicateBarcodes = array_filter($barcodeCounts, function ($count) {
            return $count > 1;
        });

        // Memasukkan setiap barcode yang memiliki duplikat ke tabel BarcodeAbnormal
        // foreach ($duplicateBarcodes as $barcode => $count) {
        //     BarcodeAbnormal::create([
        //         'code_document' => '0001/11/2024',
        //         'old_barcode_product' => $barcode
        //     ]);
        // }

        // Mengembalikan respon sesuai dengan hasil
        if (!empty($duplicateBarcodes)) {
            return response()->json($duplicateBarcodes); // Anda dapat mengembalikan data barcode yang memiliki duplikat
        } else {
            return response()->json("Tidak ada data duplikat.");
        }
    }

    function deleteDuplicateOldBarcodes()
    {
        // Dapatkan semua old_barcode_product yang ada di kode dokumen tertentu
        $productOlds = Product_old::where('code_document', '0001/11/2024')
            ->select('id', 'old_barcode_product')
            ->orderBy('id')
            ->get();

        // Simpan barcode yang sudah ditemukan
        $uniqueBarcodes = [];

        // Loop data untuk menghapus yang duplikat
        foreach ($productOlds as $productOld) {
            if (in_array($productOld->old_barcode_product, $uniqueBarcodes)) {
                // Jika barcode sudah ada di array, hapus datanya
                Product_old::where('id', $productOld->id)->delete();
            } else {
                // Jika barcode belum ada, tambahkan ke array
                $uniqueBarcodes[] = $productOld->old_barcode_product;
            }
        }
    }
}
