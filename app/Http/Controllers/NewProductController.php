<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Document;
use App\Models\New_product;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Http\Resources\ResponseResource;
use App\Models\ListProductBP;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

class NewProductController extends Controller
{

    public function index()
    {
        $newProducts = New_product::latest()->paginate(50);

        return new ResponseResource(true, "list new product", $newProducts);
    }


    public function create()
    {
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code_document' => 'required',
            'old_barcode_product' => 'required',
            'new_barcode_product' => 'required|unique:new_products,new_barcode_product',
            'new_name_product' => 'required',
            'new_quantity_product' => 'required|integer',
            'new_price_product' => 'required|numeric',
            // 'new_date_in_product' => 'required|date',
            'new_status_product' => 'required|in:display,expired,promo,bundle,palet',
            'condition' => 'required|in:lolos,damaged,abnormal',
            'new_category_product' => 'nullable|exists:categories,name_category',
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
            'new_date_in_product',
            'new_status_product',
            'new_category_product',
            'new_tag_product'
        ]);

        // Set zona waktu ke Indonesia/Jakarta
        $indonesiaTime = Carbon::now('Asia/Jakarta');
        $inputData['new_date_in_product'] = $indonesiaTime->toDateString();
        
        if ($status !== 'lolos') {
            $inputData['new_status_product'] = 'display';
            $inputData['new_quantity_product'] = 0;
            $inputData['new_price_product'] = 0;
            $inputData['new_category_product'] = null;
            $inputData['new_tag_product'] = null;
            $inputData['new_name_product'] = null;
            $inputData['new_barcode_product'] = null;
        }

        $inputData['new_quality'] = json_encode($qualityData);

        $newProduct = New_product::create($inputData);

        //update status document
        $code_document = Document::where('code_document', $request['code_document'])->first();

        if ($code_document->status_document == 'pending') {
            $code_document->update(['status_document' => 'in progress']);
        }

        return new ResponseResource(true, "New Produk Berhasil ditambah", $newProduct);
    }


    /**
     * Display the specified resource.
     */
    public function show(New_product $new_product)
    {
        return new ResponseResource(true, "data new product", $new_product);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(New_product $new_product)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, New_product $new_product)
    {
        $validator = Validator::make($request->all(), [
            'code_document' => 'required',
            'old_barcode_product' => 'required',
            'new_barcode_product' => 'required',
            'new_name_product' => 'required',
            'new_quantity_product' => 'required|integer',
            'new_price_product' => 'required|numeric',
            'new_date_in_product' => 'required|date',
            'new_status_product' => 'required|in:display,expired,promo,bundle,palet',
            'condition' => 'required|in:lolos,damaged,abnormal',
            'new_category_product' => 'nullable|exists:categories,name_category',
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
            'new_date_in_product',
            'new_status_product',
            'new_category_product',
            'new_tag_product'
        ]);

        $indonesiaTime = Carbon::now('Asia/Jakarta');
        $inputData['new_date_in_product'] = $indonesiaTime->toDateString();

        if ($status !== 'lolos') {
            // Set nilai-nilai default jika status bukan 'lolos'
            $inputData['new_status_product'] = 'display';
            $inputData['new_quantity_product'] = 0;
            $inputData['new_price_product'] = 0;
            $inputData['new_category_product'] = null;
            $inputData['new_tag_product'] = null;
            $inputData['new_name_product'] = null;
            $inputData['new_barcode_product'] = null;
        }

        $inputData['new_quality'] = json_encode($qualityData);


        $new_product->update($inputData);

        return new ResponseResource(true, "New Produk Berhasil di Update", $new_product);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(New_product $new_product)
    {
        $new_product->delete();
        return new ResponseResource(true, "data berhasil di hapus", $new_product);
    }

    public function deleteAll()
    {
        try {
            // ListProductBP::query()->delete();
            New_product::query()->delete();
            return new ResponseResource(true, "data berhasil dihapus", null);
        } catch (\Exception $e) {
            return response()->json(["error" => $e], 402);
        }
    }

    public function expireProducts()
    {
        $fourWeeksAgo = now()->subWeeks(4)->toDateString();

        $products = New_product::where('new_date_in_product', '>', $fourWeeksAgo)
            ->where('new_status_product', 'display');

        $products = $products->get();

        foreach ($products as $product) {
            $product->update(['new_status_product' => 'expired']);
        }

        return new ResponseResource(true, "Products expired successfully", $products);
    }



    public function listProductExp()
    {
        try {
            $productExpired = New_product::where('new_status_product', 'expired')->get();
            return new ResponseResource(true, "list product expired", $productExpired);
        } catch (\Exception $e) {
            return response()->json(["error" => $e]);
        }
    }


    public function excelImport(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls'
        ]);

        $file = $request->file('file');

        $filePath = $file->getPathname();
        $fileName = $file->getClientOriginalName();
        $file->storeAs('public/ekspedisis', $file->hashName());
        $header = ['Barcode', 'Description', 'Category', 'Qty', 'Price After Discount'];

        $latestDocument = Document::latest()->first();
        $newId = $latestDocument ? $latestDocument->id + 1 : 1;
        $id_document = str_pad($newId, 4, '0', STR_PAD_LEFT);
        $month = date('m');
        $year = date('Y');
        $code_document = $id_document . '/' . $month . '/' . $year;

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();

            $rowCount = 0;
            $columnCount = count($header);

            foreach ($sheet->getRowIterator(2) as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $rowData[] = $cell->getValue();
                }

                $rowData = array_slice($rowData, 0, $columnCount);

                $combinedData = array_combine($header, $rowData);
                $indonesiaTime = Carbon::now('Asia/Jakarta');
                $new_date_in_product = $indonesiaTime->toDateString();
                New_product::create([
                    'code_document' => $code_document,
                    'new_barcode_product' => $combinedData['Barcode'],
                    'new_name_product' => $combinedData['Description'],
                    'new_category_product' => $combinedData['Category'],
                    'new_quantity_product' => $combinedData['Qty'],
                    'new_price_product' => $combinedData['Price After Discount'],
                    'new_date_in_product' => $new_date_in_product

                ]);

                $rowCount++;
            }

            $fileDetails = [
                'total_column_count' => $columnCount,
                'total_row_count' => $rowCount
            ];

            // Now, create a Document record with the new code_document
            Document::create([
                'code_document' => $code_document,
                'base_document' => $fileName,
                'total_column_document' => $columnCount,
                'total_column_in_document' => $rowCount,
            ]);
        } catch (ReaderException $e) {
            return back()->with('error', 'Error processing file: ' . $e->getMessage());
        }

        return new ResponseResource(true, "New Produk Berhasil ditambah", $fileDetails);
    }

}
