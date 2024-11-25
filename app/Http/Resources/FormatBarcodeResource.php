<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormatBarcodeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'format' => $this->format,
            'total_user' => $this->total_user,
            'total_scan' => $this->total_scan,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'users' => $this->users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role_id'  => $user->role_id,
                    'user_scans' => $user->user_scans->map(function ($scan) {
                        return [
                            'id' => $scan->id,
                            'total_scans' => $scan->total_scans,
                            'scan_date' => $scan->scan_date,
                        ];
                    }),
                ];
            }),
        ];
    }
}
