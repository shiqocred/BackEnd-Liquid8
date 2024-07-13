<?php

namespace App\Http\Controllers;

use App\Models\Repair;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\ResponseResource;
use App\Models\New_product;
use App\Models\Color_tag;
use App\Models\Product_old;
use App\Models\Notification;


class RepairController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $userId =  auth()->id();
        $query = $request->input('q');

        $repairs = Repair::latest()
        ->with('repair_products')->where(function($repair) use ($query) {
            $repair->where('repair_name', 'LIKE', '%' . $query . '%')
            ->orWhereHas('repair_products', function($repair_product) use ($query) {
                $repair_product->where('new_name_product', 'LIKE', '%' . $query . '%')
                ->orWhere('new_barcode_product', 'LIKE', '%' . $query . '%')
                ->orWhere('new_category_product', 'LIKE', '%' . $query . '%')
                ->orWhere('old_barcode_product', 'LIKE', '%' . $query . '%')
                ->orWhere('new_tag_product', 'LIKE', '%' . $query . '%');
            });
        })->paginate(50);
        
        return new ResponseResource(true, "list repair products", $repairs);
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
    public function show(Repair $repair)
    {
        $repair->load('repair_products');
        return new ResponseResource(true, 'detail repair', $repair);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Repair $repair)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Repair $repair)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Repair $repair)
    {
        DB::beginTransaction();
        try {
            $productBundles = $repair->repair_products;
            foreach ($productBundles as $product) {
                New_product::create([
                    'code_document' => $product->code_document,
                    'old_barcode_product' => $product->old_barcode_product,
                    'new_barcode_product' => $product->new_barcode_product,
                    'new_name_product' => $product->new_name_product,
                    'new_quantity_product' => $product->new_quantity_product,
                    'new_price_product' => $product->new_price_product,
                    'old_price_product' => $product->new_price_product,
                    'new_date_in_product' => $product->new_date_in_product,
                    'new_status_product' => 'display',
                    'new_quality' => $product->new_quality,
                    'new_category_product' => $product->new_category_product,
                    'new_tag_product' => $product->new_tag_product
                ]);

                $product->delete();
            }
        
            $repair->delete();

            DB::commit();
            return new ResponseResource(true, "Produk repair berhasil dihapus", null);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => 'Gagal menghapus repair', 'error' => $e->getMessage()], 500);
        }
    }

    public function getProductRepair(Request $request)
    {
        $codeDocument = $request->input('code_document');
        $oldBarcode = $request->input('old_barcode_product');

        if (!$codeDocument) {
            return new ResponseResource(false, "Code document tidak boleh kosong.", null);
        }

        if (!$oldBarcode) {
            return new ResponseResource(false, "Barcode tidak boleh kosong.", null);
        }

        // $checkBarcode = New_product::where('code_document', $codeDocument)
        //     ->where('old_barcode_product', $oldBarcode)
        //     ->exists();

        // if ($checkBarcode) {
        //     return new ResponseResource(false, "tidak bisa scan product yang sudah ada.", null);
        // }

        $product = New_product::where('code_document', $codeDocument)
            ->where('old_barcode_product', $oldBarcode)
            ->first();

        if (!$product) {
            return new ResponseResource(false, "Produk tidak ditemukan.", null);
        }

        $newBarcode = $this->generateUniqueBarcode();
        $response = ['product' => $product, 'new_barcode' => $newBarcode];

        if ($product->old_price_product < 100000) {
            $response['color_tags'] = Color_tag::where('min_price_color', '<=', $product->old_price_product)
                ->where('max_price_color', '>=', $product->old_price_product)
                ->get();
        }

        return new ResponseResource(true, "Produk ditemukan.", $response);
    }

    private function generateUniqueBarcode()
    {
        $prefix = 'LQD';
        do {
            $randomNumber = str_pad(mt_rand(0, 99999), 5, '0', STR_PAD_LEFT);
            $barcode = $prefix . $randomNumber;
        } while (New_product::where('new_barcode_product', $barcode)->exists());

        return $barcode;
    }
}
