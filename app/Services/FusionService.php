<?php

namespace App\Services;

class FusionService
{
    /**
     * 来源优先级：数字越小优先级越高
     */
    private array $sourcePriority = [
        'barcode' => 1,
        'ocr' => 2,
        'yolo' => 3,
    ];

    /**
     * 计算两个矩形的 IoU（交并比），用于判断是否为同一物体
     * IoU > 0.5 认为是同一物体（重叠度超过50%）
     */
    private function bboxIoU(array $a, array $b): float
    {
        $ax1 = $a[0];
        $ay1 = $a[1];
        $ax2 = $a[0] + $a[2];
        $ay2 = $a[1] + $a[3];
        
        $bx1 = $b[0];
        $by1 = $b[1];
        $bx2 = $b[0] + $b[2];
        $by2 = $b[1] + $b[3];
        
        // 计算交集区域
        $interX1 = max($ax1, $bx1);
        $interY1 = max($ay1, $by1);
        $interX2 = min($ax2, $bx2);
        $interY2 = min($ay2, $by2);
        
        // 如果没有交集
        if ($interX2 <= $interX1 || $interY2 <= $interY1) {
            return 0.0;
        }
        
        // 交集面积
        $interArea = ($interX2 - $interX1) * ($interY2 - $interY1);
        
        // 两个矩形的面积
        $areaA = $a[2] * $a[3];
        $areaB = $b[2] * $b[3];
        
        // 并集面积
        $unionArea = $areaA + $areaB - $interArea;
        
        // 避免除以0
        if ($unionArea <= 0) {
            return 0.0;
        }
        
        // IoU = 交集 / 并集
        return $interArea / $unionArea;
    }

    /**
     * 计算两个矩形的 IoM（Intersection over Minimum area）
     * 当一个框被另一个框“包含”时，IoU 可能很低，但 IoM 会很高。
     */
    private function bboxIoM(array $a, array $b): float
    {
        $ax1 = $a[0];
        $ay1 = $a[1];
        $ax2 = $a[0] + $a[2];
        $ay2 = $a[1] + $a[3];

        $bx1 = $b[0];
        $by1 = $b[1];
        $bx2 = $b[0] + $b[2];
        $by2 = $b[1] + $b[3];

        $interX1 = max($ax1, $bx1);
        $interY1 = max($ay1, $by1);
        $interX2 = min($ax2, $bx2);
        $interY2 = min($ay2, $by2);

        if ($interX2 <= $interX1 || $interY2 <= $interY1) {
            return 0.0;
        }

        $interArea = ($interX2 - $interX1) * ($interY2 - $interY1);
        $areaA = $a[2] * $a[3];
        $areaB = $b[2] * $b[3];
        $minArea = min($areaA, $areaB);

        if ($minArea <= 0) {
            return 0.0;
        }

        return $interArea / $minArea;
    }

    /**
     * 两矩形 [x,y,w,h] 是否重叠（用于同区域去重）
     * 保留此方法以兼容，但内部使用 IoU 判断
     */
    private function bboxOverlap(array $a, array $b): bool
    {
        // 同一物体判定：
        // - IoU：用于大小接近的框
        // - IoM：用于“一个框包含在另一个框里”（OCR 小框 vs YOLO 大框）
        return $this->bboxIoU($a, $b) > 0.3 || $this->bboxIoM($a, $b) > 0.7;
    }

    /**
     * 融合三方 hits，按优先级（barcode > ocr > yolo）和 bbox 重叠去重计数。
     *
     * 每个 hit 结构建议：
     * - sku_id, name, source, confidence, count
     * - bbox: null 或 [x,y,w,h]
     * 
     * 优先级策略：
     * 1. barcode（条码）优先级最高，精确匹配
     * 2. ocr（文字识别）优先级第二
     * 3. yolo（视觉检测）优先级最低，但提供完整的物体检测
     * 
     * 去重策略：
     * - 按 sku_id 分组
     * - 同一 sku_id 下，bbox 重叠的视为同一物体（不重复计数）
     * - 优先级高的来源会覆盖低优先级的来源信息
     */
    public function fuseHits(array $barcodeHits, array $ocrHits, array $yoloHits): array
    {
        $final = [];
        // sku_id => [ [x,y,w,h], ... ]
        $finalBboxes = [];

        // 按优先级顺序处理：barcode → ocr → yolo
        $priorityGroups = [
            'barcode' => $barcodeHits,
            'ocr' => $ocrHits,
            'yolo' => $yoloHits,
        ];

        foreach ($priorityGroups as $source => $hits) {
            foreach ($hits as $idx => $item) {
                if (!isset($item['sku_id'])) {
                    continue;
                }

                $id = $item['sku_id'];
                $bbox = $item['bbox'] ?? null;

                // 如果这个 SKU 还没有记录，直接添加
                if (!isset($final[$id])) {
                    $final[$id] = $item;
                    $final[$id]['sources'] = [$source];
                    $final[$id]['count'] = 1;
                    $finalBboxes[$id] = $bbox && is_array($bbox) && count($bbox) >= 4 ? [$bbox] : [];
                    // 用于可视化：每个去重后的 bbox 对应一个“被选中的来源/置信度”
                    $final[$id]['bboxes'] = $finalBboxes[$id];
                    $final[$id]['bbox_sources'] = $finalBboxes[$id] ? [$source] : [];
                    $final[$id]['bbox_confidences'] = $finalBboxes[$id] ? [(float)($item['confidence'] ?? 0)] : [];
                    continue;
                }

                // 记录所有参与过的来源
                if (!in_array($source, $final[$id]['sources'], true)) {
                    $final[$id]['sources'][] = $source;
                }

                // 如果有 bbox，检查是否与已有 bbox 重叠
                if ($bbox !== null && is_array($bbox) && count($bbox) >= 4) {
                    $overlap = false;
                    foreach ($finalBboxes[$id] as $existingIdx => $existing) {
                        if ($this->bboxOverlap($bbox, $existing)) {
                            $overlap = true;

                            // 同一个物体（bbox 重叠）：按“来源优先级”选择用于展示的来源；summary 仍按最高置信度更新
                            $curPri = $this->sourcePriority[$source] ?? 99;
                            $oldSource = $final[$id]['bbox_sources'][$existingIdx] ?? null;
                            $oldPri = $this->sourcePriority[$oldSource] ?? 99;
                            if ($curPri < $oldPri) {
                                $final[$id]['bbox_sources'][$existingIdx] = $source;
                                $final[$id]['bbox_confidences'][$existingIdx] = (float)($item['confidence'] ?? 0);
                            }

                            // 更新 summary 的最高置信度（不改变 count）
                            if (($item['confidence'] ?? 0) > ($final[$id]['confidence'] ?? 0)) {
                                $final[$id]['confidence'] = (float)($item['confidence'] ?? 0);
                                $final[$id]['source'] = $source;
                            }
                            break;
                        }
                    }
                    
                    // 如果不重叠，说明是新的物体，增加计数
                    if (!$overlap) {
                        $final[$id]['count'] = (int)($final[$id]['count'] ?? 0) + 1;
                        $finalBboxes[$id][] = $bbox;
                        $final[$id]['bboxes'][] = $bbox;
                        $final[$id]['bbox_sources'][] = $source;
                        $final[$id]['bbox_confidences'][] = (float)($item['confidence'] ?? 0);
                        
                        // 更新最高置信度
                        if (($item['confidence'] ?? 0) > ($final[$id]['confidence'] ?? 0)) {
                            $final[$id]['confidence'] = (float)($item['confidence'] ?? 0);
                            $final[$id]['source'] = $source;
                        }
                    }
                } else {
                    // 无 bbox 的情况（理论上不应该出现，因为我们要求所有检测都有 bbox）
                    // 保守处理：不增加计数，只记录来源
                }
            }
        }

        // 输出给 view 用的数组
        return array_values($final);
    }
}
