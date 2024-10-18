<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\New_product;
use Illuminate\Http\Request;
use App\Models\StagingApprove;
use App\Models\StagingProduct;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Http\Resources\ResponseResource;
use App\Models\Product_Bundle;
use App\Models\Product_old;
use App\Models\ProductApprove;
use App\Models\Sale;
use Maatwebsite\Excel\Facades\Excel;
use ProductStagingsExport;

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
        set_time_limit(600);
        ini_set('memory_limit', '1024M');

        $lolos = New_product::where('code_document', '0068/09/2024')
            ->pluck('old_barcode_product');

        $stagings = StagingProduct::where('code_document', '0068/09/2024')
            ->pluck('old_barcode_product');

        $product_olds = Product_old::where('code_document', '0068/09/2024')
            ->pluck('old_barcode_product');


        // $product_olds2 = Product_old::where('code_document', '0003/10/2024')
        //     ->pluck('old_barcode_product');


        $combined = $lolos->merge($stagings)->merge($product_olds);
        dd(count($combined));
        $unique = $product_olds2->diff($combined);

        return $unique->isNotEmpty() ? $unique : "Tidak ada barcode yang unik.";
    }

    public function findSimilarStagingProducts()
    {
        // Tentukan awalan yang ingin diperiksa
        $prefix = '179';

        $similarStagingProducts = New_product::where('code_document', '0068/09/2024')->get();
        $count = count($similarStagingProducts);

        // Mengembalikan respon dengan data yang ditemukan
        return new ResponseResource(true, "Products with similar barcode prefix found", $count);
    }

    public function findSimilarTabel(Request $request)
    {
        $lolos = New_product::where('code_document', '0068/09/2024')
            ->pluck('new_barcode_product');

        $sales = Sale::latest()->pluck('product_barcode_sale');

        // Menggabungkan data $lolos dan $sales
        $combined = $lolos->merge($sales);

        // Memeriksa barcode yang duplikat
        $duplicateBarcodes = $combined->duplicates();;

        // $stagings = StagingProduct::where('code_document', '0068/09/2024')
        //     ->pluck('new_name_product');

        // $approve = StagingApprove::where('code_document', '0068/09/2024')
        //     ->pluck('old_barcode_product');

        // $product_olds2 = Product_old::where('code_document', '0068/09/2024')
        //     ->pluck('old_name_product');



        // $approve = Product_Bundle::where('code_document', '0068/09/2024')
        //     ->pluck('new_name_product');

        // $combined = $lolos->merge($stagings)->merge($product_olds2)->merge($sales)->merge($approve);

        // Menggabungkan dua koleksi ($lolos dan $sales)


        // Mengembalikan data duplikat jika ada, atau pesan jika tidak ada
        if ($duplicateBarcodes->isNotEmpty()) {
            return response()->json($duplicateBarcodes);
        } else {
            return response()->json("Tidak ada data duplikat.");
        }
    }
}
