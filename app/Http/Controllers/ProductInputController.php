<?php

namespace App\Http\Controllers;

use Carbon\Carbon;

use App\Models\Color_tag;
use App\Models\Notification;
use App\Models\ProductInput;
use Illuminate\Http\Request;
use App\Models\FilterProductInput;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\ResponseResource;
use App\Models\ProductScan;
use App\Models\StagingProduct;
use Illuminate\Support\Facades\Validator;

class ProductInputController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $searchQuery = $request->input('q');
        $newProducts = ProductInput::latest()
            ->where(function ($queryBuilder) use ($searchQuery) {
                $queryBuilder->where('old_barcode_product', 'LIKE', '%' . $searchQuery . '%')
                    ->orWhere('new_barcode_product', 'LIKE', '%' . $searchQuery . '%')
                    ->orWhere('new_category_product', 'LIKE', '%' . $searchQuery . '%')
                    ->orWhere('new_name_product', 'LIKE', '%' . $searchQuery . '%');
            });
        $totalPrice = $newProducts->sum('new_price_product');
        $newProducts = $newProducts->paginate(50);
        return new ResponseResource(true, "list product scans", ['tota_price' => $totalPrice, 'products' => $newProducts]);
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
        $userId = auth()->id();
        $validator = Validator::make($request->all(), [
            'new_barcode_product' => 'nullable|unique:new_products,new_barcode_product',
            'new_name_product' => 'required',
            'new_quantity_product' => 'required|integer',
            'new_price_product' => 'required|numeric',
            'new_status_product' => 'nullable|in:display,expired,promo,bundle,palet,dump',
            'condition' => 'nullable|in:lolos,damaged,abnormal',
            'new_category_product' => 'nullable|exists:categories,name_category',
            'new_tag_product' => 'nullable|exists:color_tags,name_color',
            'image' => 'nullable|mimes:jpg,jpeg,png,webp,gif,svg,bmp,tiff|max:1024',
        ],  [
            'new_barcode_product.unique' => 'barcode sudah ada'
        ]);

        // $validator->sometimes('new_category_product', 'required', function ($input) {
        //     return $input->new_price_product >= 100000;
        // });

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        DB::beginTransaction();

        try {

            $imageName = null;

            // Simpan file gambar jika ada
            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                $folderName = 'product_images';
                $imageName = $request->file('image')->hashName();
                $request->file('image')->storeAs($folderName, $imageName, 'public'); 
            }

            // Logika untuk memproses data
            $status = $request->input('condition');
            $description = $request->input('description', '');

            $qualityData = [
                'lolos' => $status === 'lolos' ? 'lolos' : null,
                'damaged' => $status === 'damaged' ? $description : null,
                'abnormal' => $status === 'abnormal' ? $description : null,
            ];


            $inputData = $request->only([
                'old_price_product',
                'new_barcode_product',
                'new_name_product',
                'new_quantity_product',
                'new_price_product',
                'new_status_product',
                'new_category_product',
                'new_tag_product',
                'price_discount',
            ]);

            $inputData['new_status_product'] = 'display';
            $inputData['user_id'] = $userId;

            $inputData['new_date_in_product'] = Carbon::now('Asia/Jakarta')->toDateString();
            $inputData['new_quality'] = json_encode($qualityData);

            if ($status !== 'lolos') {
                $inputData['new_category_product'] = null;
            }
            $inputData['new_discount'] = 0;
            $inputData['display_price'] = $inputData['new_price_product'];

            if (!empty($inputData['new_barcode_product'])) {
                $inputData['new_barcode_product'] = $request->input('new_barcode_product');
            } else {
                $inputData['new_barcode_product'] = generateNewBarcode($inputData['new_category_product']);
            }

            $newProduct = ProductInput::create($inputData);

            $inputData['image'] = $imageName;

            ProductScan::create([
                'user_id' => $userId,
                'product_name' => $inputData['new_name_product'],
                'product_price' => $inputData['old_price_product'],
                'image' => $imageName
            ]);


                DB::commit();

            return new ResponseResource(true, "berhasil menambah data", $newProduct);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }



    /**
     * Display the specified resource.
     */
    public function show(ProductInput $productInput)
    {
        if ($productInput) {
            return new ResponseResource(true, "detail product input", $productInput);
        } else {
            return new ResponseResource(false, "id tidak ada", []);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProductInput $productInput)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProductInput $productInput)
    {
        $validator = Validator::make($request->all(), [
            'code_document' => 'nullable',
            'old_barcode_product' => 'nullable',
            'new_barcode_product' => 'required',
            'new_name_product' => 'required',
            'new_quantity_product' => 'required|integer',
            'new_price_product' => 'required|numeric',
            'old_price_product' => 'required|numeric',
            'new_status_product' => 'required|in:display,expired,promo,bundle,palet,dump,sale,migrate',
            'condition' => 'nullable',
            'new_category_product' => 'nullable',
            'new_tag_product' => 'nullable|exists:color_tags,name_color',
            'new_discount' => 'nullable|numeric',
            'display_price' => 'required|numeric'
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
            'new_barcode_product',
            'new_name_product',
            'new_quantity_product',
            'new_price_product',
            'old_price_product',
            'new_status_product',
            'new_category_product',
            'new_tag_product',
            'new_discount',
            'display_price'
        ]);

        $indonesiaTime = Carbon::now('Asia/Jakarta');
        $inputData['new_date_in_product'] = $indonesiaTime->toDateString();


        if ($inputData['old_price_product'] > 100000) {
            $inputData['new_tag_product'] = null;
        }

        if ($request->input('old_price_product') <= 100000) {
            $tagwarna = Color_tag::where('min_price_color', '<=', $request->input('old_price_product'))
                ->where('max_price_color', '>=', $request->input('old_price_product'))
                ->select('fixed_price_color', 'name_color')->first();
            $inputData['new_tag_product'] = $tagwarna['name_color'];
            $inputData['new_price_product'] = $tagwarna['fixed_price_color'];
            $inputData['new_category_product'] = null;
        }

        if ($status !== 'lolos') {
            // Set nilai-nilai default jika status bukan 'lolos'
            $inputData['new_price_product'] = null;
            $inputData['new_category_product'] = null;
        }

        $inputData['new_quality'] = json_encode($qualityData);

        if ($productInput->new_category_product != null) {
            $inputData['new_barcode_product'] = $productInput->new_barcode_product;
        }

        $productInput->update($inputData);

        return new ResponseResource(true, "New Produk Berhasil di Update", $productInput);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProductInput $productInput)
    {
        $productInput->delete();
        return new ResponseResource(true, "data berhasil di hapus", $productInput);
    }

    public function move_to_stagings(Request $request)
    {
        DB::beginTransaction();
        $userId = auth()->id();
        try {
            $product_filters = FilterProductInput::where('user_id', $userId)->get();
            if ($product_filters->isEmpty()) {
                return new ResponseResource(false, "Tidak ada produk filter yang tersedia saat ini", $product_filters);
            }

            $insertData = $product_filters->map(function ($product) use ($userId) {
                return [
                    'code_document' => $product->code_document,
                    'old_barcode_product' => $product->old_barcode_product,
                    'new_barcode_product' => $product->new_barcode_product,
                    'new_name_product' => $product->new_name_product,
                    'new_quantity_product' => $product->new_quantity_product,
                    'new_price_product' => $product->new_price_product,
                    'old_price_product' => $product->old_price_product,
                    'new_date_in_product' => $product->new_date_in_product,
                    'new_status_product' =>  $product->new_status_product,
                    'new_quality' => $product->new_quality,
                    'new_category_product' => $product->new_category_product,
                    'new_tag_product' => $product->new_tag_product,
                    'new_discount' => $product->new_discount,
                    'display_price' => $product->display_price,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->toArray();

            Notification::create([
                'user_id' => $userId,
                'notification_name' => 'butuh approvemend untuk product staging',
                'role' => 'Spv',
                'read_at' => Carbon::now('Asia/Jakarta'),
                'riwayat_check_id' => null,
                'repair_id' => null,
                'status' => 'done'
            ]);

            FilterProductInput::where('user_id', $userId)->delete();
            StagingProduct::insert($insertData);

            logUserAction($request, $request->user(), "product input", "product input");

            DB::commit();
            return new ResponseResource(true, "staging approve berhasil dibuat", null);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => 'Gagal memindahkan product ke approve', 'error' => $e->getMessage()], 500);
        }
    }
}
