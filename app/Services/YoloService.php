<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class YoloService
{
    public function detect($path)
    {
        $res = Http::attach(
            'file',
            file_get_contents($path),
            basename($path)
        )->post(env('YOLO_API'));

        return $res->json();
    }
}
