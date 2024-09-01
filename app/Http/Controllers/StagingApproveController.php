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
use App\Models\Product_old;
use App\Models\ProductApprove;

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
                if ($user->role && ($user->role->role_name == 'Admin Kasir' ||  $user->role->role_name == 'Admin' ||  $user->role->role_name == 'Spv')) {

                    $productApproves = StagingApprove::get();

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

                            $productApprove->delete();
                        }

                        New_product::insert($dataToInsert);
                    }

                    DB::commit();
                    return new ResponseResource(true, 'Transaksi berhasil diapprove', null);
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

    public function export_product_staging(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'ID',
            'Code Document',
            'Old Barcode Product',
            'New Barcode Product',
            'New Name Product',
            'New Quantity Product',
            'New Price Product',
            'Old Price Product',
            'New Date In Product',
            'New Status Product',
            'New Quality',
            'New Category Product',
            'New Tag Product',
            'Created At',
            'Updated At'
        ];

        // Menuliskan headers ke sheet
        $columnIndex = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($columnIndex, 1, $header);
            $columnIndex++;
        }

        // Variabel untuk melacak baris
        $rowIndex = 2;

        // Mengambil data dalam batch
        StagingProduct::where('code_document', '0003/08/2024')
            ->chunk(1000, function ($products) use ($sheet, &$rowIndex) {
                foreach ($products as $product) {
                    $sheet->setCellValueByColumnAndRow(1, $rowIndex, $product->id);
                    $sheet->setCellValueByColumnAndRow(2, $rowIndex, $product->code_document);
                    $sheet->setCellValueByColumnAndRow(3, $rowIndex, $product->old_barcode_product);
                    $sheet->setCellValueByColumnAndRow(4, $rowIndex, $product->new_barcode_product);
                    $sheet->setCellValueByColumnAndRow(5, $rowIndex, $product->new_name_product);
                    $sheet->setCellValueByColumnAndRow(6, $rowIndex, $product->new_quantity_product);
                    $sheet->setCellValueByColumnAndRow(7, $rowIndex, $product->new_price_product);
                    $sheet->setCellValueByColumnAndRow(8, $rowIndex, $product->old_price_product);
                    $sheet->setCellValueByColumnAndRow(9, $rowIndex, $product->new_date_in_product);
                    $sheet->setCellValueByColumnAndRow(10, $rowIndex, $product->new_status_product);
                    $sheet->setCellValueByColumnAndRow(11, $rowIndex, $product->new_quality);
                    $sheet->setCellValueByColumnAndRow(12, $rowIndex, $product->new_category_product);
                    $sheet->setCellValueByColumnAndRow(13, $rowIndex, $product->new_tag_product);
                    $sheet->setCellValueByColumnAndRow(14, $rowIndex, $product->created_at);
                    $sheet->setCellValueByColumnAndRow(15, $rowIndex, $product->updated_at);
                    $rowIndex++;
                }
            });

        // Menyimpan file Excel
        $writer = new Xlsx($spreadsheet);
        $fileName = 'new_products_export.xlsx';
        $publicPath = 'exports';
        $filePath = public_path($publicPath) . '/' . $fileName;

        // Membuat direktori exports jika belum ada
        if (!file_exists(public_path($publicPath))) {
            mkdir(public_path($publicPath), 0777, true);
        }

        $writer->save($filePath);

        // Mengembalikan URL untuk mengunduh file
        $downloadUrl = url($publicPath . '/' . $fileName);

        return new ResponseResource(true, "file diunduh", $downloadUrl);
    }


    public function countBast()
    {
        $lolos = New_product::where('code_document', '0003/08/2024')
            ->pluck('old_barcode_product');

        $stagings = StagingProduct::where('code_document', '0003/08/2024')
            ->pluck('old_barcode_product');

        $product_olds = Product_old::where('code_document', '0004/08/2024')
            ->pluck('old_barcode_product');

        $combined = $lolos->merge($stagings);

        $unique = $product_olds->diff($combined);

        return $unique->isNotEmpty() ? $unique : "Tidak ada barcode yang unik.";
    }

   
    
}
