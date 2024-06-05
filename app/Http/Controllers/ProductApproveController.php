<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Document;
use Illuminate\Http\Request;
use App\Models\ProductApprove;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\ResponseResource;
use App\Models\New_product;
use App\Models\Product_old;
use Illuminate\Support\Facades\Validator;

class ProductApproveController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = $request->input('q');
        $newProducts = ProductApprove::latest()->where(function ($queryBuilder) use ($query) {
            $queryBuilder->where('old_barcode_product', 'LIKE', '%' . $query . '%')
                ->orWhere('new_barcode_product', 'LIKE', '%' . $query . '%')
                ->orWhere('new_tag_product', 'LIKE', '%' . $query . '%')
                ->orWhere('new_category_product', 'LIKE', '%' . $query . '%')
                ->orWhere('new_name_product', 'LIKE', '%' . $query . '%');
        })->where('new_status_product', '!=', 'dump')
            ->where('new_status_product', '!=', 'expired')
            ->where('new_status_product', '!=', 'sale')
            ->where('new_status_product', '!=', 'migrate')
            ->where('new_status_product', '!=', 'repair')
            ->paginate(100);

        // $startNumber = ($newProducts->currentPage() - 1) * $newProducts->perPage() + 1 ;

        // $newProducts->getCollection()->transform(function($product) use (&$startNumber){
        //     $product->number = $startNumber++;
        //     return $product;
        // });

        return new ResponseResource(true, "list new product", $newProducts);
    }

    public function byDocument(Request $request)
    {
        $query = $request->input('code_document');

        $newProducts = ProductApprove::where('code_document', $query)->paginate(100);

        if ($newProducts->isEmpty()) {
            return new ResponseResource(false, "No data found", null);
        }

        return new ResponseResource(true, "List new products", $newProducts);
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
        $validator = Validator::make($request->all(), [
            'code_document' => 'required',
            'old_barcode_product' => 'required',
            'new_barcode_product' => 'required|unique:new_products,new_barcode_product',
            'new_name_product' => 'required',
            'new_quantity_product' => 'required|integer',
            'new_price_product' => 'required|numeric',
            'old_price_product' => 'required|numeric',
            // 'new_date_in_product' => 'required|date',
            'new_status_product' => 'required|in:display,expired,promo,bundle,palet,dump',
            'condition' => 'required|in:lolos,damaged,abnormal',
            'new_category_product' => 'nullable|exists:categories,name_category',
            'new_tag_product' => 'nullable|exists:color_tags,name_color'
        ],  [
            'new_barcode_product.unique' => 'barcode sudah ada',
            'old_barcode_product.exists' => 'barcode tidak ada '

        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        DB::beginTransaction();

        try {
            // Logika untuk memproses data
            $status = $request->input('condition');
            $description = $request->input('deskripsi', '');

            $qualityData = $this->prepareQualityData($status, $description);

            $inputData = $this->prepareInputData($request, $status, $qualityData);
            $oldBarcode = New_product::where('old_barcode_product', $request->input('old_barcode_product'))->first();

            if ($oldBarcode) {
                return response()->json([
                    'needConfirmation' => true,
                    'message' => 'Product dengan barcode ini sudah ada. Apakah Anda yakin ingin melanjutkan?',
                    'inputData' => $inputData
                ]);
            } else{
                $newProduct = ProductApprove::create($inputData);
            }


            $this->updateDocumentStatus($request->input('code_document'));

            $this->deleteOldProduct($request->input('old_barcode_product'));

            DB::commit();

            return new ResponseResource(true, "New Produk Berhasil ditambah", $newProduct);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function prepareQualityData($status, $description)
    {
        return [
            'lolos' => $status === 'lolos' ? 'lolos' : null,
            'damaged' => $status === 'damaged' ? $description : null,
            'abnormal' => $status === 'abnormal' ? $description : null
        ];
    }

    private function prepareInputData($request, $status, $qualityData)
    {
        $inputData = $request->only([
            'code_document',
            'old_barcode_product',
            'new_barcode_product',
            'new_name_product',
            'new_quantity_product',
            'new_price_product',
            'old_price_product',
            'new_status_product',
            'new_category_product',
            'new_tag_product'
        ]);

        if($inputData['old_price_product'] < 100000) {
            $inputData['new_barcode_product'] = $inputData['old_barcode_product'];
        }

        $inputData['new_date_in_product'] = Carbon::now('Asia/Jakarta')->toDateString();
        $inputData['new_quality'] = json_encode($qualityData);

        if ($status !== 'lolos') {
            $inputData['new_category_product'] = null;
            $inputData['new_price_product'] = null;
        }

        return $inputData;
    }

    private function updateDocumentStatus($codeDocument)
    {
        $document = Document::where('code_document', $codeDocument)->firstOrFail();
        if ($document->status_document === 'pending') {
            $document->update(['status_document' => 'in progress']);
        }
    }

    private function deleteOldProduct($old_barcode_product)
    {
        $affectedRows = DB::table('product_olds')->where('old_barcode_product', $old_barcode_product)->delete();

        if ($affectedRows > 0) {
            return true;
        } else {
            return new ResponseResource(false, "Produk lama dengan barcode tidak ditemukan.", null);
        }
    }
    private function deleteOldProduct22($old_barcode_product)
    {
        $affectedRows = DB::table('product_olds')->where('old_barcode_product', $old_barcode_product)->delete();

        if ($affectedRows > 0) {
            return true;
        } else {
            return new ResponseResource(false, "Produk lama dengan barcode tidak ditemukan.", null);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(ProductApprove $productApprove)
    {
        return new ResponseResource(true, "data new product", $productApprove);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ProductApprove $productApprove)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProductApprove $productApprove)
    {
        $validator = Validator::make($request->all(), [
            'code_document' => 'required',
            'old_barcode_product' => 'required',
            'new_barcode_product' => 'required',
            'new_name_product' => 'required',
            'new_quantity_product' => 'required|integer',
            'new_price_product' => 'required|numeric',
            'old_price_product' => 'required|numeric',
            'new_status_product' => 'required|in:display,expired,promo,bundle,palet,dump,sale,migrate',
            'condition' => 'required|in:lolos,damaged,abnormal',
            'new_category_product' => 'nullable',
            'new_tag_product' => 'nullable|exists:color_tags,name_color'
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
            'old_barcode_product',
            'new_barcode_product',
            'new_name_product',
            'new_quantity_product',
            'new_price_product',
            'old_price_product',
            'new_date_in_product',
            'new_status_product',
            'new_category_product',
            'new_tag_product'
        ]);

        $indonesiaTime = Carbon::now('Asia/Jakarta');
        $inputData['new_date_in_product'] = $indonesiaTime->toDateString();

        if ($status !== 'lolos') {
            // Set nilai-nilai default jika status bukan 'lolos'
            $inputData['new_price_product'] = null;
            $inputData['new_category_product'] = null;
        }

        $inputData['new_quality'] = json_encode($qualityData);


        $productApprove->update($inputData);

        return new ResponseResource(true, "New Produk Berhasil di Update", $productApprove);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProductApprove $productApprove)
    {
        // Salin data dari ProductApprove ke New_product
        $newProduct = new Product_old([
            'code_document' => $productApprove->code_document,
            'old_barcode_product' => $productApprove->old_barcode_product,
            'old_name_product' => $productApprove->new_name_product,
            'old_quantity_product' => $productApprove->new_quantity_product,
            'old_price_product' => $productApprove->old_price_product,
          
            // Tambahkan kolom lainnya sesuai kebutuhan
        ]);
    
        $newProduct->save(); // Simpan data baru ke New_product
    
        // Hapus data dari ProductApprove setelah data baru tersimpan
        $productApprove->delete();
    
        return new ResponseResource(true, "Data berhasil dihapus dan ditambahkan ke New_product", $newProduct);
    }

    public function deleteAll()
    {
        try {
            // ListProductBP::query()->delete();
            ProductApprove::query()->delete();
            return new ResponseResource(true, "data berhasil dihapus", null);
        } catch (\Exception $e) {
            return response()->json(["error" => $e], 402);
        }
    }

    public function getTagColor(Request $request)
    {
        $query = $request->input('q');
        try {
            $productByTagColor = ProductApprove::latest()
                ->whereNotNull('new_tag_product')
                ->when($query, function ($queryBuilder) use ($query) {
                    $queryBuilder->where('new_tag_product', 'LIKE', '%' . $query . '%')
                        ->orWhere('new_barcode_product', 'LIKE', '%' . $query . '%')
                        ->orWhere('old_barcode_product', 'LIKE', '%' . $query . '%')
                        ->orWhere('new_category_product', 'LIKE', '%' . $query . '%')
                        ->orWhere('new_tag_product', 'LIKE', '%' . $query . '%')
                        ->orWhere('new_name_product', 'LIKE', '%' . $query . '%');
                })
                ->paginate(50);
        } catch (\Exception $e) {
            return (new ResponseResource(false, "data tidak ada", $e->getMessage()))->response()->setStatusCode(500);
        }

        return new ResponseResource(true, "list product by tag color", $productByTagColor);
    }

    public function getByCategory(Request $request)
    {
        $query = $request->input('q');
        try {
            $productByTagColor = ProductApprove::latest()
                ->whereNotNull('new_category_product')
                ->when($query, function ($queryBuilder) use ($query) {
                    $queryBuilder->where('new_category_product', 'LIKE', '%' . $query . '%')
                        ->orWhere('new_barcode_product', 'LIKE', '%' . $query . '%')
                        ->orWhere('old_barcode_product', 'LIKE', '%' . $query . '%')
                        ->orWhere('new_category_product', 'LIKE', '%' . $query . '%')
                        ->orWhere('new_name_product', 'LIKE', '%' . $query . '%');
                })
                ->paginate(50);
        } catch (\Exception $e) {
            return (new ResponseResource(false, "data tidak ada", $e->getMessage()))->response()->setStatusCode(500);
        }

        return new ResponseResource(true, "list product by tag color", $productByTagColor);
    }

    public function searchByDocument(Request $request)
    {
        $code_documents = ProductApprove::where('code_document', $request->input('search'))->paginate(50);

        if ($code_documents->isNotEmpty()) {
            return new ResponseResource(true, "list product_old", $code_documents);
        } else {
            return new ResponseResource(false, "code document tidak ada", null);
        }
    }
}
