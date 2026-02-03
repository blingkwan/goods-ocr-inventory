<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetectRecord extends Model
{
    protected $fillable = [
        'image_path',
        'barcode_json',
        'ocr_json',
        'yolo_json',
        'final_json',
        'confidence',
        'need_manual'
    ];

    protected $casts = [
        'barcode_json'=>'array',
        'ocr_json'=>'array',
        'yolo_json'=>'array',
        'final_json'=>'array'
    ];
}
