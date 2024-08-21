<?php

namespace App\Http\Controllers;

use App\Models\Palet;
use App\Models\New_product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\ResponseResource;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Validator;


class PaletController extends Controller
{
    public function display(Request $request)
    {
        $query = $request->input('q');

        $new_products = New_product::query()
            ->where('new_status_product', 'display')
            ->whereJsonContains('new_quality', ['lolos' => 'lolos'])
            ->where(function ($queryBuilder) use ($query) {
                $queryBuilder->where('new_name_product', 'LIKE', '%' . $query . '%')
                    ->orWhere('new_barcode_product', 'LIKE', '%' . $query . '%')
                    ->orWhere('new_tag_product', 'LIKE', '%' . $query . '%')
                    ->orWhere('new_category_product', 'LIKE', '%' . $query . '%');
            })
            ->where('new_tag_product', null)
            ->paginate(50);

        return new ResponseResource(true, "Data produk dengan status display.", $new_products);
    }



    public function index(Request $request)
    {

        $query = $request->input('q');
        $palets = Palet::latest()
            ->with('paletProducts')
            ->where(function ($queryBuilder) use ($query) {
                $queryBuilder->where('name_palet', 'LIKE', '%' . $query . '%')
                    ->orWhere('category_palet', 'LIKE', '%' . $query . '%')
                    ->orWhereHas('paletProducts', function ($subQueryBuilder) use ($query) {
                        $subQueryBuilder->where('new_name_product', 'LIKE', '%' . $query . '%')
                            ->orWhere('new_barcode_product', 'LIKE', '%' . $query . '%')
                            ->orWhere('new_category_product', 'LIKE', '%' . $query . '%')
                            ->orWhere('old_barcode_product', 'LIKE', '%' . $query . '%')
                            ->orWhere('new_tag_product', 'LIKE', '%' . $query . '%');
                    });
            })->paginate(100);
        return new ResponseResource(true, "list palet", $palets);
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
        DB::beginTransaction();

        try{
                // Validasi request
            $validator = Validator::make($request->all(), [
                'name_palet' => 'required|string',
                'category_palet' => 'required|string',
                'total_price_palet' => 'required|numeric',
                'total_product_palet' => 'required|integer',
                'palet_barcode' => 'required|string|unique:palets,palet_barcode',
                'file_pdf' => 'nullable|mimes:pdf|max:2048',
                'description' => 'nullable|string',
                'is_active' => 'boolean',
                'warehouse' => 'required|string',
                'condition' => 'required|string',
                'status' => 'required|string',
                'is_sale' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            if ($request->hasFile('file_pdf')) {
                $file = $request->file('file_pdf');
                $filename = $file->getClientOriginalName();
                $pdfPath = $file->storeAs('palets_pdfs', $filename, 'public'); 
                $validatedData['file_pdf'] = $filename;; 
            }
            
            $palet = Palet::create([
                'name_palet' => $request['name_palet'],
                'category_palet' => $request['category_palet'],
                'total_price_palet' => $request['total_price_palet'],
                'total_product_palet' => $request['total_product_palet'],
                'palet_barcode' => $request['palet_barcode'],
                'file_pdf' => $validatedData['file_pdf'] ?? null,
                'description' => $request['description'] ?? null,
                'is_active' => $request['is_active'],
                'warehouse' => $request['warehouse'],
                'condition' => $request['condition'],
                'status' => $request['status'],
                'is_sale' => $request['is_sale'],
            ]);
            DB::commit();

            return new ResponseResource(true, "data palet berhasil ditambahkan", $palet);
        }catch(\Exception $e){
            DB::rollBack();
            Log::error('Failed to store palet: ' . $e->getMessage());
            return new ResponseResource(false, "data gagal di tambah", null);
        }
     
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Palet $palet)
    {
        $query = $request->input('q');
        $palet->load(['paletProducts' => function ($productPalet) use ($query) {
            if (!empty($query)) {
                $productPalet->where('new_name_product', 'LIKE', '%' . $query . '%')
                    ->orWhere('new_barcode_product', 'LIKE', '%' . $query . '%')
                    ->orWhere('new_tag_product', 'LIKE', '%' . $query . '%')
                    ->orWhere('new_category_product', 'LIKE', '%' . $query . '%')
                    ->orWhere('new_tag_product', 'LIKE', '%' . $query . '%');
            }
        }]);
        $palet->total_harga_lama = $palet->paletProducts->sum('old_price_product');

        return new ResponseResource(true, "list product", $palet);
    }


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Palet $palet)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Palet $palet)
    {
        $validator = Validator::make($request->all(), [
            'nama_palet' => 'required',
            'total_price_palet' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $resource = new ResponseResource(false, "Input tidak valid!", $validator->errors());
            return $resource->response()->setStatusCode(422);
        }

        DB::beginTransaction();
        try {
            $palet->update([
                'name_palet' => $request->nama_palet,
                'total_price_palet' => $request->total_price_palet,
            ]);

            DB::commit();
            return new ResponseResource(true, "palet berhasil di edit", $palet);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Palet gagal di edit" . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Palet gagal di edit', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Palet $palet)
    {
        DB::beginTransaction();
        try {
            $productPalet = $palet->paletProducts;

            foreach ($productPalet as $product) {
                New_product::create([
                    'code_document' => $product->code_document,
                    'old_barcode_product' => $product->old_barcode_product,
                    'new_barcode_product' => $product->new_barcode_product,
                    'new_name_product' => $product->new_name_product,
                    'new_quantity_product' => $product->new_quantity_product,
                    'new_price_product' => $product->new_price_product,
                    'old_price_product' => $product->old_price_product,
                    'new_date_in_product' => $product->new_date_in_product,
                    'new_status_product' => $product->new_status_product,
                    'new_quality' => $product->new_quality,
                    'new_category_product' => $product->new_category_product,
                    'new_tag_product' => $product->new_tag_product,
                    'new_discount' => $product->new_discount,
                    'display_price' => $product->display_price
                ]);

                $product->delete();
            }

            $palet->delete();

            DB::commit();
            return new ResponseResource(true, "palet berhasil dihapus", null);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Gagal menghapus palet: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menghapus palet', 'error' => $e->getMessage()], 500);
        }
    }


    public function exportPalletsDetail($id)
    {
        // Meningkatkan batas waktu eksekusi dan memori
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();


        $paletHeaders = [
            'id',
            'name_palet',
            'category_palet',
            'total_price_palet',
            'total_product_palet',
            'palet_barcode',
            'total_harga_lama',
        ];

        $paletProductsHeaders = [
            'palet_id',
            'code_document',
            'old_barcode_product',
            'new_barcode_product',
            'new_name_product',
            'new_quantity_product',
            'new_price_product',
            'old_price_product',
            'new_date_in_product',
            'new_status_product',
            'new_quality',
            'new_category_product',
            'new_tag_product',
            'new_discount',
            'display_price'
        ];

        $columnIndex = 1;
        foreach ($paletHeaders as $header) {
            $sheet->setCellValueByColumnAndRow($columnIndex, 1, $header);
            $columnIndex++;
        }

        $rowIndex = 2;

        $palet = Palet::with('paletProducts')->where('id', $id)->first();
        if ($palet) {
            $columnIndex = 1;

            foreach ($paletHeaders as $header) {
                $sheet->setCellValueByColumnAndRow($columnIndex, $rowIndex, $palet->$header);
                $columnIndex++;
            }
            $rowIndex++;

            $rowIndex++;
            $productColumnIndex = 1;
            foreach ($paletProductsHeaders as $header) {
                $sheet->setCellValueByColumnAndRow($productColumnIndex, $rowIndex, $header);
                $productColumnIndex++;
            }
            $rowIndex++;

            if ($palet->paletProducts->isNotEmpty()) {
                foreach ($palet->paletProducts as $productPalet) {
                    $productColumnIndex = 1; // Mulai dari kolom pertama
                    foreach ($paletProductsHeaders as $header) {
                        $sheet->setCellValueByColumnAndRow($productColumnIndex, $rowIndex, $productPalet->$header);
                        $productColumnIndex++;
                    }
                    $rowIndex++;
                }
            }
            $rowIndex++;
        } else {
            $sheet->setCellValueByColumnAndRow(1, 1, 'No data found');
        }

        // Menyimpan file Excel
        $writer = new Xlsx($spreadsheet);
        $fileName = 'exportPalet_' . $palet->name_palet . '.xlsx';
        $publicPath = 'exports';
        $filePath = public_path($publicPath) . '/' . $fileName;

        // Membuat direktori exports jika belum ada
        if (!file_exists(public_path($publicPath))) {
            mkdir(public_path($publicPath), 0777, true);
        }

        $writer->save($filePath);

        // Mengembalikan URL untuk mengunduh file
        $downloadUrl = url($publicPath . '/' . $fileName);

        return new ResponseResource(true, "unduh", $downloadUrl);
    }

   
}
