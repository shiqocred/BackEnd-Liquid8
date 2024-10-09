<?php

namespace App\Http\Controllers;

use App\Models\Palet;
use App\Models\Category;
use App\Models\PaletImage;
use App\Models\Destination;
use App\Models\New_product;
use App\Models\PaletFilter;
use App\Models\PaletProduct;
use Illuminate\Http\Request;
use App\Models\ProductStatus;
use App\Models\ProductCondition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\ResponseResource;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


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
            ->with('paletProducts', 'paletImages', 'paletBrands')
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
            })->paginate(20);
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

        try {
            // Validasi request
            $validator = Validator::make($request->all(), [
                'images' => 'array|nullable',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'name_palet' => 'required|string',
                'category_palet' => 'nullable|string',
                'total_price_palet' => 'required|numeric',
                'total_product_palet' => 'required|integer',
                'palet_barcode' => 'required|string|unique:palets,palet_barcode',
                'file_pdf' => 'nullable|mimes:pdf|max:2048',
                'description' => 'nullable|string',
                'is_active' => 'boolean',
                'warehouse' => 'nullable|string',
                'condition' => 'nullable|string',
                'status' => 'nullable|string',
                'is_sale' => 'boolean',
                'category_id' => 'nullable|exists:categories,id',
                'product_status_id' => 'nullable|exists:product_statuses,id',
                'destination_id' => 'nullable|exists:destinations,id',
                'product_condition_id' => 'nullable|exists:product_conditions,id',
            ]);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            // Handle PDF upload
            if ($request->hasFile('file_pdf')) {
                $file = $request->file('file_pdf');
                $filename = $file->getClientOriginalName();
                $pdfPath = $file->storeAs('palets_pdfs', $filename, 'public');
                $validatedData['file_pdf'] = $filename;
            }

            $category = Category::find($request['category_id']) ?: null;
            $destination = Destination::find($request['destination_id']) ?: null;
            $productStatus = ProductStatus::find($request['product_status_id']) ?: null;
            $productCondition = ProductCondition::find($request['product_condition_id']) ?: null;

            // Create Palet
            $palet = Palet::create([
                'name_palet' => $request['name_palet'],
                'category_palet' => $category->name_category ?? '',
                'total_price_palet' => $request['total_price_palet'],
                'total_product_palet' => $request['total_product_palet'],
                'palet_barcode' => $request['palet_barcode'],
                'file_pdf' => $validatedData['file_pdf'] ?? null,
                'description' => $request['description'] ?? null,
                'is_active' => $request['is_active'] ?? false,
                'warehouse' => $destination->shop_name ?? null,
                'condition' => $productCondition->condition_slug ?? null,
                'status' => $productStatus->status_slug ?? null,
                'is_sale' => $request['is_sale'] ?? false,
                'category_id' => $request['category_id'],
                'product_status_id' => $request['product_status_id'],
                'destination_id' => $request['destination_id'],
                'product_condition_id' => $request['product_condition_id'],
            ]);


            // Handle multiple image uploads
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $imageName = $image->hashName();
                    $imagePath = $image->storeAs('product-images', $imageName, 'public');

                    PaletImage::create([
                        'palet_id' => $palet->id,
                        'filename' => $imageName
                    ]);
                }
            }

            $userId = auth()->id();
            $product_filters = PaletFilter::where('user_id', $userId)->get();  

            $insertData = $product_filters->map(function ($product) use ($palet) {
                return [
                    'palet_id' => $palet->id,
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
                    'display_price'=> $product->display_price,
                    'created_at' => now(),  
                    'updated_at' => now(),
                ];
            })->toArray();

            PaletProduct::insert($insertData);

            PaletFilter::where('user_id', $userId)->delete();

            DB::commit();

            return new ResponseResource(true, "Data palet berhasil ditambahkan", $palet);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to store palet: ' . $e->getMessage());
            return new ResponseResource(false, "Data gagal ditambahkan", null);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(Request $request, Palet $palet)
    {
        $query = $request->input('q');
        $palet->load(['paletImages', 'paletProducts' => function ($productPalet) use ($query) {
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
        DB::beginTransaction();

        try {
            // Validasi request
            $validator = Validator::make($request->all(), [
                'images' => 'array|nullable',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'name_palet' => 'required|string',
                'category_palet' => 'nullable|string',
                'total_price_palet' => 'required|numeric',
                'total_product_palet' => 'required|integer',
                'file_pdf' => 'nullable|mimes:pdf|max:2048',
                'description' => 'nullable|string',
                'is_active' => 'nullable|boolean',
                'warehouse' => 'nullable|string',
                'condition' => 'nullable|string',
                'status' => 'nullable|string',
                'is_sale' => 'nullable|boolean',
                'category_id' => 'nullable|exists:categories,id',
                'product_status_id' => 'nullable|exists:product_statuses,id',
                'destination_id' => 'nullable|exists:destinations,id',
                'product_condition_id' => 'nullable|exists:product_conditions,id',
            ]);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $category = Category::where('id', $request['category_id'])->first();
            $destination = Destination::where('id', $request['destination_id'])->first();
            $productStatus = ProductStatus::where('id', $request['product_status_id'])->first();
            $productCondition = ProductCondition::where('id', $request['product_condition_id'])->first();

            // if (!$category) {
            //     return new ResponseResource(false, "Category ID tidak ditemukan", $request['category_id']);
            // }
            // if (!$destination) {
            //     return new ResponseResource(false, "destination ID tidak ditemukan", $request['destination_id']);
            // }
            // if (!$productStatus) {
            //     return new ResponseResource(false, "productStatus ID tidak ditemukan", $request['product_status_id']);
            // }
            // if (!$productCondition) {
            //     return new ResponseResource(false, "productCondition ID tidak ditemukan", $request['product_condition_id']);
            // }


            if ($request->hasFile('file_pdf')) {
                // Hapus file PDF lama jika ada
                if ($palet->file_pdf) {
                    Storage::disk('public')->delete('palets_pdfs/' . $palet->file_pdf);
                }

                $file = $request->file('file_pdf');
                $filename = $file->getClientOriginalName();
                $pdfPath = $file->storeAs('palets_pdfs', $filename, 'public');
                $palet->file_pdf = $pdfPath;
                $request['file_pdf'] = $filename;
            }

            $palet->update([
                'name_palet' => $request['name_palet'],
                'category_palet' => $category->name_category,
                'total_price_palet' => $request['total_price_palet'],
                'total_product_palet' => $request['total_product_palet'],
                'file_pdf' => $request['file_pdf'] ?? null,
                'description' => $request['description'] ?? null,
                'is_active' => $request['is_active'],
                'warehouse' => $destination->shop_name,
                'condition' => $productCondition->condition_slug,
                'status' => $productStatus->status_slug,
                'is_sale' => $request['is_sale'],
                'category_id' => $request['category_id'],
                'product_status_id' => $request['product_status_id'],
                'destination_id' => $request['destination_id'],
                'product_condition_id' => $request['product_condition_id'],
            ]);

            // Handle multiple image uploads
            if ($request->hasFile('images')) {
                $oldImages = PaletImage::where('palet_id', $palet->id)->get();
                foreach ($oldImages as $oldImage) {
                    Storage::disk('public')->delete('product-images/' . $oldImage->filename);
                    $oldImage->delete();
                }

                // Simpan gambar baru
                foreach ($request->file('images') as $image) {
                    $imageName = $image->hashName();
                    $image->storeAs('product-images', $imageName, 'public');

                    PaletImage::create([
                        'palet_id' => $palet->id,
                        'filename' => $imageName
                    ]);
                }
            }

            DB::commit();

            return new ResponseResource(true, "Data palet berhasil diperbarui", $palet);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update palet: ' . $e->getMessage());
            return new ResponseResource(false, "Data gagal diperbarui", null);
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
            if ($productPalet) {
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
            }
            $oldImages = PaletImage::where('palet_id', $palet->id)->get();
            foreach ($oldImages as $oldImage) {
                Storage::disk('public')->delete('product-images/' . $oldImage->filename);
                $oldImage->delete();
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


    public function exportpaletsDetail($id)
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

    public function updateCategoryPalet(Request $request)
    {
        $palets = Palet::all();

        foreach ($palets as $palet) {
            $category = Category::where('name_category', $palet->category_palet)->first();

            if ($category) {
                $palet->category_id = $category->id;
                $palet->save();
            }
        }
        return new ResponseResource(true, "berhasil di update", []);
    }
}
