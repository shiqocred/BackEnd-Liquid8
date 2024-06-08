<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class     ProductapproveResource extends JsonResource
{
    protected $status, $message, $needConfirmation, $isMultiple;

    public function __construct($isMultiple, $needConfirmation, $status, $message, $resource)
    {
        $this->isMultiple = $isMultiple;
        $this->needConfirmation = $needConfirmation;
        $this->status = $status;
        $this->message = $message;
        $this->resource = $resource;
    }


    public function toArray(Request $request): array
    {
        return [
            "isMultiple" => $this->isMultiple,
            "needConfirmation" => $this->needConfirmation,
            "status" => $this->status,
            "message" => $this->message,
            "resource" => $this->resource
        ];
    }
}
