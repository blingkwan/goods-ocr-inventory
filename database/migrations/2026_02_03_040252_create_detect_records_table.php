<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('detect_records', function (Blueprint $table) {
            $table->id();
            $table->string('image_path')->comment('图片存储路径');
            $table->json('barcode_json')->nullable()->comment('条码识别结果JSON');
            $table->json('ocr_json')->nullable()->comment('OCR识别结果JSON');
            $table->json('yolo_json')->nullable()->comment('YOLO检测结果JSON');
            $table->json('final_json')->nullable()->comment('融合后的最终结果JSON');
            $table->decimal('confidence', 5, 2)->default(0)->comment('最高置信度');
            $table->boolean('need_manual')->default(false)->comment('是否需要人工确认');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detect_records');
    }
};
