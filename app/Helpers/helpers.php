<?php

use App\Models\Sale;
use App\Models\Migrate;
use App\Models\New_product;
use Illuminate\Support\Str;
use App\Models\SaleDocument;
use App\Models\MigrateDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

function codeDocumentMigrate()
{
    $codeDocumentMigrate = MigrateDocument::selectRaw('MAX(MID(code_document_migrate, 6, 4)) as code_document_migrate')
        ->first();

    if ($codeDocumentMigrate->count() > 0) {
        $n = ((int)$codeDocumentMigrate['code_document_migrate']) + 1;
        $no = sprintf("%'.04d", $n);
    } else {
        $no = "0001";
    }
    $code = "LQMGT" . $no;
    return $code;
}

function codeDocumentSale($userId)
{
    $codeDocumentSale = SaleDocument::selectRaw('MAX(MID(code_document_sale, 7, 5)) as code_document_sale')
        ->first();

    $sales = Sale::where('status_sale', 'proses')
        ->select('user_id', DB::raw('MAX(MID(code_document_sale, 7, 5)) as code_document_sale'))
        ->groupBy('user_id')
        ->get();

    if ($codeDocumentSale->count() > 0) {
        if ($sales->count() > 0) {
            foreach ($sales as $sale) {
                if ($userId == $sale->user_id) {
                    $n = ((int)$sale['code_document_sale']);
                    break;
                } else {
                    $n = ((int)$codeDocumentSale['code_document_sale']) + 1;
                    if ($sale['code_document_sale'] == $codeDocumentSale['code_document_sale'] + 1) {
                        $n += 1;
                    }
                }
            }
        } else {
            $n = ((int)$codeDocumentSale['code_document_sale']) + 1;
        }
        $no = sprintf("%'.05d", $n);
    } else {
        $no = "00001";
    }
    $code = "LQDSLE" . $no;
    return $code;
}

function generateNewBarcode($category)
{
    $bulanIndo = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember'
    ];
    $categoryInitial = strtoupper(substr($category, 0, 1));
    $currentMonth = $bulanIndo[date('n')];
    $currentMonth = strtoupper(substr($currentMonth, 0, 1));
    $randomString = strtoupper(Str::random(5));

    return "L{$categoryInitial}{$currentMonth}{$randomString}";
}


function changeBarcodeByDocument($code_document, $init_barcode)
{
    set_time_limit(300); 
    ini_set('memory_limit', '512M'); 
    DB::beginTransaction();
    try {
        $products = New_product::where('code_document', $code_document)->get();

        $code_parts = explode('/', $code_document);
        $code_numeric = $code_parts[0]; 

        foreach ($products as $product) {
            $newBarcode = $init_barcode . $code_numeric;

            // Cek apakah barcode baru sudah ada di database
            if (New_product::where('new_barcode_product', $newBarcode)->exists()) {
                throw new \Exception("Barcode $newBarcode already exists.");
            }

            $product->new_barcode_product = $newBarcode;
            $product->save();

            $code_numeric++;
        }

        DB::commit();

        return true; // Return true if everything is successful
    } catch (\Exception $e) {
        DB::rollBack();

        // Logging error
        Log::error('Error updating barcodes: ' . $e->getMessage());

        return false; // Return false if an error occurs
    }
}

