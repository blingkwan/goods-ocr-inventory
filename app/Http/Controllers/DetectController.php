<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AliVisionService;
use App\Services\FusionService;
use App\Services\YoloService;
use App\Models\Sku;

class DetectController extends Controller
{
    public function form()
    {
        return view('detect.form');
    }

    /**
     * 从阿里 BarCodeRect（CenterX, CenterY, Width, Height）转为 [x,y,w,h]
     */
    private function barCodeRectToBbox($rect): ?array
    {
        if (empty($rect['Width']) || empty($rect['Height'])) {
            return null;
        }
        $cx = (float)($rect['CenterX'] ?? 0);
        $cy = (float)($rect['CenterY'] ?? 0);
        $w = (float)($rect['Width'] ?? 0);
        $h = (float)($rect['Height'] ?? 0);
        return [$cx - $w / 2, $cy - $h / 2, $w, $h];
    }

    public function detect(Request $request)
    {
        if (!$request->hasFile('image')) {
            return back()->with('error', '请上传图片');
        }

        // ======================
        // 1️⃣ 保存图片
        // ======================
        $file = $request->file('image');
        $path = $file->store('uploads', 'public');
        $fullPath = storage_path('app/public/' . $path);

        $url = url('storage/' . $path);
        
        // ⚠️⚠️⚠️ 开发环境硬编码警告 ⚠️⚠️⚠️
        // 当前使用硬编码的公网图片 URL，这会导致：
        // 1. 阿里云 OCR/条码识别 → 使用硬编码的图片（img11.jpg）
        // 2. YOLO 检测 → 使用本地上传的新图片（$fullPath）
        // 3. 结果：新图片上显示旧图片的 OCR/条码标注！
        // 
        // 解决方案：
        // - 如需测试新图片，请将新图片上传到 http://www.kwan.com.cn/ 并修改下面的 URL
        // - 或者配置内网穿透让阿里云能访问本地文件
        // - 生产环境请注释掉下面这行，使用真实的 $url
        // $url = 'http://www.kwan.com.cn/img3.jpg'; // ← 当前硬编码的测试图片

        // ======================
        // 2️⃣ 条码识别（优先级最高，General 不支持条码，单独调 Type=BarCode）
        // ======================
        $vision = new AliVisionService();
        $barcodeHits = [];
        $barcodeWarnings = [];
        $barcodeRaw = $vision->recognizeBarcode($url);
        
        if (empty($barcodeRaw['error'])) {
            $barcodeSubImages = $barcodeRaw['subImages'] ?? ($barcodeRaw['SubImages'] ?? []);
            
            foreach ($barcodeSubImages as $sub) {
                $barInfo = $sub['barCodeInfo'] ?? ($sub['BarCodeInfo'] ?? null);
                $details = $barInfo['barCodeDetails'] ?? ($barInfo['BarCodeDetails'] ?? []);
                
                if (!$barInfo || empty($details)) {
                    continue;
                }
                
                foreach ($details as $b) {
                    $code = $b['data'] ?? ($b['Data'] ?? null);
                    
                    // 检查条码是否为空（检测到位置但无法解码）
                    if ($code === null || $code === '') {
                        // 计算条码区域大小
                        $points = $b['barCodePoints'] ?? ($b['BarCodePoints'] ?? null);
                        if ($points && count($points) >= 4) {
                            $xs = array_column($points, 'x');
                            $ys = array_column($points, 'y');
                            $width = max($xs) - min($xs);
                            $height = max($ys) - min($ys);
                            
                            // 获取图片尺寸
                            $imgWidth = $barcodeRaw['width'] ?? ($barcodeRaw['Width'] ?? 1);
                            $imgHeight = $barcodeRaw['height'] ?? ($barcodeRaw['Height'] ?? 1);
                            $percentage = ($width * $height) / ($imgWidth * $imgHeight) * 100;
                            
                            $barcodeWarnings[] = [
                                'type' => 'barcode_decode_failed',
                                'message' => '检测到条码位置但无法解码',
                                'barcode_type' => $b['type'] ?? $b['barCodeType'] ?? 'Unknown',
                                'area_size' => "{$width}x{$height}px",
                                'percentage' => round($percentage, 2) . '%',
                                'suggestion' => $width < 100 || $height < 300 
                                    ? '条码区域太小，建议重新拍摄更清晰的图片（条码应占图片 5-20% 的面积）' 
                                    : '图片质量不足，建议提高分辨率或对比度'
                            ];
                            
                            \Log::warning('条码识别失败', [
                                'barcode_area' => "{$width}x{$height}",
                                'image_size' => "{$imgWidth}x{$imgHeight}",
                                'percentage' => "{$percentage}%",
                                'type' => $b['type'] ?? 'Unknown'
                            ]);
                        }
                        continue;
                    }
                    
                    // 条码解码成功，查找对应的 SKU
                    $sku = Sku::where('barcode', $code)->first();
                    if ($sku) {
                        $rect = $b['barCodeRect'] ?? ($b['BarCodeRect'] ?? []);
                        $bbox = $this->barCodeRectToBbox($rect);
                        $barcodeHits[] = [
                            'sku_id' => $sku->id,
                            'name' => $sku->name,
                            'source' => 'barcode',
                            'confidence' => 0.95,
                            'count' => 1,
                            'bbox' => $bbox,
                        ];
                    } else {
                        // 条码解码成功但数据库中没有对应的 SKU
                        $barcodeWarnings[] = [
                            'type' => 'barcode_not_found',
                            'message' => '识别到条码但数据库中未找到对应商品',
                            'barcode' => $code,
                            'barcode_type' => $b['type'] ?? $b['barCodeType'] ?? 'Unknown',
                            'suggestion' => '请检查条码是否正确，或在系统中添加该商品'
                        ];
                        
                        \Log::info('条码识别成功但未找到 SKU', ['barcode' => $code]);
                    }
                }
            }
        }

        // ======================
        // 3️⃣ OCR 文本块识别（优先级第二，只用 keywords 匹配，不用 name）
        // ======================
        $ocrRaw = $vision->recognize($url);
        if (!empty($ocrRaw['error'])) {
            return back()->with('error', 'OCR 识别失败：' . ($ocrRaw['msg'] ?? '未知错误'));
        }
        
        $subImages = $ocrRaw['subImages'] ?? ($ocrRaw['SubImages'] ?? []);
        $ocrHits = [];
        
        if (!empty($subImages)) {
            $skus = Sku::whereNotNull('keywords')->where('keywords', '!=', '')->get();
            foreach ($subImages as $sub) {
                $blockInfo = $sub['blockInfo'] ?? ($sub['BlockInfo'] ?? null);
                $blocks = $blockInfo['blockDetails'] ?? ($blockInfo['BlockDetails'] ?? []);
                foreach ($blocks as $block) {
                    $text = $block['blockContent'] ?? ($block['BlockContent'] ?? '');
                    if ($text === '') {
                        continue;
                    }
                    $textNorm = mb_strtolower(trim($text));
                    $rect = $block['blockRect'] ?? ($block['BlockRect'] ?? []);
                    $bbox = null;
                    if (!empty($rect)) {
                        $cx = (float)($rect['CenterX'] ?? $rect['centerX'] ?? 0);
                        $cy = (float)($rect['CenterY'] ?? $rect['centerY'] ?? 0);
                        $w  = (float)($rect['Width'] ?? $rect['width'] ?? 0);
                        $h  = (float)($rect['Height'] ?? $rect['height'] ?? 0);
                        if ($w > 0 && $h > 0) {
                            $bbox = [$cx - $w / 2, $cy - $h / 2, $w, $h];
                        }
                    }
                    
                    // 只用 keywords 匹配
                    foreach ($skus as $sku) {
                        $matched = false;
                        $kwStr = (string)($sku->keywords ?? '');
                        if ($kwStr !== '') {
                            $kws = array_filter(array_map('trim', explode(',', $kwStr)));
                            foreach ($kws as $kw) {
                                $kwNorm = mb_strtolower($kw);
                                // 包含匹配
                                if ($kwNorm !== '' && mb_strpos($textNorm, $kwNorm) !== false) {
                                    $matched = true;
                                    break;
                                }
                                // 相似度匹配
                                similar_text($kw, $text, $percentKw);
                                if ($percentKw > 60) {
                                    $matched = true;
                                    break;
                                }
                            }
                        }

                        if ($matched) {
                            $ocrHits[] = [
                                'sku_id' => $sku->id,
                                'name' => $sku->name,
                                'source' => 'ocr',
                                'confidence' => 0.75,
                                'count' => 1,
                                'bbox' => $bbox,
                            ];
                            break; // 匹配到一个 SKU 就跳出，避免重复
                        }
                    }
                }
            }
        }

        // ======================
        // 4️⃣ YOLO 检测（优先级第三，数量来源，带 bbox）
        // ======================
        $yolo = new YoloService();
        $yoloRes = $yolo->detect($fullPath);
        $boxes = $yoloRes['boxes'] ?? [];

        $yoloHits = [];
        foreach ($boxes as $box) {
            $skuName = $box['sku'] ?? null;
            if (!$skuName) {
                continue;
            }
            $sku = Sku::where('name', $skuName)->first();
            if (!$sku) {
                continue;
            }
            // 支持 bbox 为 [x,y,w,h] 或 [x1,y1,x2,y2]，统一为 [x,y,w,h]
            $bbox = null;
            if (isset($box['bbox']) && is_array($box['bbox'])) {
                $b = $box['bbox'];
                if (count($b) >= 4) {
                    $x1 = (float)$b[0];
                    $y1 = (float)$b[1];
                    $x2 = (float)$b[2];
                    $y2 = (float)$b[3];

                    // 绝大多数 YOLO 输出是 xyxy（左上+右下）。若满足 x2>x1 且 y2>y1，优先按 xyxy 解析。
                    if ($x2 > $x1 && $y2 > $y1) {
                        $bbox = [$x1, $y1, $x2 - $x1, $y2 - $y1];
                    } else {
                        // 否则按 xywh（左上+宽高）解析
                        $bbox = [$x1, $y1, $x2, $y2];
                    }
                }
            }
            $yoloHits[] = [
                'sku_id' => $sku->id,
                'name' => $sku->name,
                'source' => 'yolo',
                'confidence' => (float)($box['conf'] ?? 0.6),
                'count' => 1,
                'bbox' => $bbox,
            ];
        }

        // ======================
        // 5️⃣ 三方融合去重（按优先级：barcode > ocr > yolo，bbox 重叠去重）
        // ======================
        $fusion = new FusionService();
        $results = $fusion->fuseHits($barcodeHits, $ocrHits, $yoloHits);

        // ======================
        // 6️⃣ 生成可视化标注（使用融合后的去重 bbox，避免重复框）
        // ======================
        $annotations = [];
        foreach ($results as $r) {
            $skuId = $r['sku_id'] ?? null;
            if (!$skuId) {
                continue;
            }
            $bboxes = $r['bboxes'] ?? [];
            if (!is_array($bboxes) || empty($bboxes)) {
                continue;
            }
            $bboxSources = is_array($r['bbox_sources'] ?? null) ? $r['bbox_sources'] : [];
            $bboxConfs = is_array($r['bbox_confidences'] ?? null) ? $r['bbox_confidences'] : [];

            foreach ($bboxes as $i => $bbox) {
                if (!$bbox || !is_array($bbox) || count($bbox) < 4) {
                    continue;
                }
                $annotations[] = [
                    'sku_id' => $skuId,
                    'name' => $r['name'] ?? '',
                    'source' => $bboxSources[$i] ?? ($r['source'] ?? ''),
                    'confidence' => $bboxConfs[$i] ?? ($r['confidence'] ?? null),
                    'bbox' => $bbox,
                    'count' => 1,
                    'total_count' => (int)($r['count'] ?? 1),
                ];
            }
        }

        // ======================
        // 7️⃣ 调试信息：统计各来源检测数量
        // ======================
        $debugInfo = [
            'barcode_count' => count($barcodeHits),
            'barcode_warnings' => $barcodeWarnings,
            'ocr_count' => count($ocrHits),
            'yolo_count' => count($yoloHits),
            'total_annotations' => count($annotations),
            'final_count' => array_sum(array_column($results, 'count')),
        ];

        // ======================
        // 8️⃣ 保存检测记录到数据库
        // ======================
        $detectRecord = \App\Models\DetectRecord::create([
            'image_path' => $path,
            'barcode_json' => $barcodeHits,
            'ocr_json' => $ocrHits,
            'yolo_json' => $yoloHits,
            'final_json' => $results,
            'confidence' => !empty($results) ? max(array_column($results, 'confidence')) : 0,
            'need_manual' => false, // 可根据置信度或数量差异判断是否需要人工确认
        ]);

        // ======================
        // 9️⃣ 输出人工确认页
        // ======================
        return view('detect.result', [
            'image' => $url,
            'results' => $results,
            'annotations' => $annotations,
            'record_id' => $detectRecord->id,
            'debug' => $debugInfo,
        ]);
    }
}
