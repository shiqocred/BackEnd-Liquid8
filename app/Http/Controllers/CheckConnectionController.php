<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use Illuminate\Http\Request;

class CheckConnectionController extends Controller
{
    public function checkPingWithImage()
    {
        $image = url('storage/image-for-check-connection/7kb_image.png');
        $resource = new ResponseResource(true, "List data buyer", $image);
        return $resource->response();
    }
}
