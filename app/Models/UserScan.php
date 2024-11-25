<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;


class UserScan extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    public static function updateOrCreateDailyScan($userId, $formatBarcodeId)
{
    $scanDate = Carbon::now('Asia/Jakarta')->toDateString();

    $userScan = self::where('user_id', $userId)
        ->where('format_barcode_id', $formatBarcodeId)
        ->where('scan_date', $scanDate)
        ->first();

    if ($userScan) {
        $userScan->increment('total_scans');
    } else {
        self::create([
            'user_id' => $userId,
            'format_barcode_id' => $formatBarcodeId,
            'total_scans' => 1,
            'scan_date' => $scanDate,
        ]);
    }
}

    public function formatBarcode()
    {
        return $this->belongsTo(FormatBarcode::class, 'format_barcode_id');
    }
}
