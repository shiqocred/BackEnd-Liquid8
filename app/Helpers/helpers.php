<?php

use App\Models\Sale;
use App\Models\Migrate;
use App\Models\Document;
use App\Models\New_product;
use Illuminate\Support\Str;
use App\Models\SaleDocument;
use App\Models\ProductApprove;
use App\Models\MigrateDocument;
use App\Models\StagingApprove;
use App\Models\StagingProduct;
use App\Models\UserLog;
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
    
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $maxRetry = 5;

    return DB::transaction(function () use ($categoryInitial, $currentMonth, $characters, $maxRetry) {
        for ($i = 0; $i < $maxRetry; $i++) {
            $randomString = '';
            for ($j = 0; $j < 5; $j++) {
                $randomString .= $characters[mt_rand(0, strlen($characters) - 1)];
            }
            $newBarcode = "L{$categoryInitial}{$currentMonth}{$randomString}";
            
            $exists = DB::table('product_approves')
                ->where('new_barcode_product', $newBarcode)
                ->sharedLock()
                ->exists();

            if (!$exists) {
                return $newBarcode;
            }
        }

        throw new \Exception("Terlalu banyak generate, tolong refresh.");
    });
}

//old
// function newBarcodeCustom($code_document, $init_barcode)
// {
//     $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
//     $newBarcode = '';

//     do {
//         $randomString = '';
//         for ($i = 0; $i < 5; $i++) {
//             $randomString .= $characters[mt_rand(0, strlen($characters) - 1)];
//         }

//         $newBarcode = $init_barcode . $randomString;

//         $exists = ProductApprove::where('new_barcode_product', $newBarcode)->exists();
//     } while ($exists);

//     return $newBarcode;
// }


function newBarcodeCustom($init_barcode, $userId)
{
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
    $maxRetry = 5;

    return DB::transaction(function () use ($init_barcode, $characters, $maxRetry, $userId) {
        for ($i = 0; $i < $maxRetry; $i++) {
            $randomString = '';
            for ($j = 0; $j < 5; $j++) {
                $randomString .= $characters[mt_rand(0, strlen($characters) - 1)];
            }
            $newBarcode = $init_barcode . $userId . $randomString;
            $exists = DB::table('product_approves')
                ->where('new_barcode_product', $newBarcode)
                ->sharedLock()
                ->exists();

            if (!$exists) {
                return $newBarcode;
            }
        }

        throw new \Exception("terlalu banyak generate, tolong refresh");
    });
}



if (! function_exists('logUserAction')) {
    function logUserAction($request, $user, $halaman, $pesan)
    {
        UserLog::create([
            'user_id' => $user->id,
            'name_user' => $user->name,
            'page' => $halaman,
            'info' => $pesan,
        ]);
        // Tandai bahwa log sudah dibuat untuk request ini
        $request->attributes->set('log_created', true);
    }
}

function barcodeScan()
{
    return 'SC-' . now()->format('YmdHis');
}


function newBarcodeScan()
{
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $newBarcode = '';

    do {
        $randomString = '';
        for ($i = 0; $i < 5; $i++) {
            $randomString .= $characters[mt_rand(0, strlen($characters) - 1)];
        }

        $newBarcode = "LSC" . $randomString;

        $exists = StagingApprove::where('new_barcode_product', $newBarcode)->exists() ||
            StagingProduct::where('new_barcode_product', $newBarcode)->exists() ||
            New_product::where('new_barcode_product', $newBarcode)->exists();
    } while ($exists);

    return $newBarcode;
}
