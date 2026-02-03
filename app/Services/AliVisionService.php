<?php

namespace App\Services;

use AlibabaCloud\SDK\Ocrapi\V20210707\Ocrapi;
use AlibabaCloud\SDK\Ocrapi\V20210707\Models\RecognizeAllTextRequest;
use Darabonba\OpenApi\Models\Config;

class AliVisionService
{
    private $client;

    public function __construct()
    {
        $config = new Config();
        $config->accessKeyId = env('ALIYUN_AK');
        $config->accessKeySecret = env('ALIYUN_SK');
        $config->endpoint = "ocr-api.cn-hangzhou.aliyuncs.com";

        $this->client = new Ocrapi($config);
    }

    /**
     * 通用文字识别（OCR），返回 Data 层结构：Content, SubImages 等
     * 文档：Data.Content 为全文，Data.SubImages[].BlockInfo 为文字块
     */
    public function recognize($imageUrl)
    {
        $req = new RecognizeAllTextRequest();
        $req->url = $imageUrl;
        // 用高精版并开启坐标输出，便于做 bbox 融合
        $req->type = "Advanced";           // 高精度通用文字
        $req->outputCoordinate = "rectangle"; // 返回 BlockRect / CellRect 旋转矩形
        $req->outputOricoord   = true;        // 用原图坐标

        try {
            $res = $this->client->recognizeAllText($req);
            // SDK 返回 body->data，转为数组后即 Data 层：Content, SubImages, Height, Width 等
            $data = json_decode(json_encode($res->body->data), true);
            return is_array($data) ? $data : [];
        } catch (\Exception $e) {
            return [
                "error" => true,
                "msg" => $e->getMessage(),
                "code" => $e->getCode()
            ];
        }
    }

    /**
     * 单独条码识别（通用 OCR 的 General 类型不支持条码，需用 Type=BarCode 再调一次）
     * 返回与 recognize 一致的 Data 层，条码在 SubImages[].BarCodeInfo.BarCodeDetails[].Data
     */
    public function recognizeBarcode($imageUrl)
    {
        \Log::info('开始条码识别', ['url' => $imageUrl]);
        
        // 方案1：尝试使用 BarCode 类型
        try {
            $req = new RecognizeAllTextRequest();
            $req->url = $imageUrl;
            $req->type = "BarCode";
            
            $res = $this->client->recognizeAllText($req);
            $data = json_decode(json_encode($res->body->data), true);
            
            \Log::info('阿里云条码识别原始返回', ['data' => $data]);
            
            // 检查是否有条码数据
            if (is_array($data) && !empty($data)) {
                $subImages = $data['subImages'] ?? ($data['SubImages'] ?? []);
                if (!empty($subImages)) {
                    $hasEmptyBarcode = false;
                    
                    foreach ($subImages as $idx => &$sub) {
                        $barInfo = $sub['barCodeInfo'] ?? ($sub['BarCodeInfo'] ?? null);
                        if ($barInfo) {
                            $details = $barInfo['barCodeDetails'] ?? ($barInfo['BarCodeDetails'] ?? []);
                            
                            // 检查是否有有效的条码数据
                            $hasValidData = false;
                            foreach ($details as $detail) {
                                $code = $detail['data'] ?? ($detail['Data'] ?? '');
                                if ($code !== '') {
                                    $hasValidData = true;
                                    \Log::info('找到有效条码数据', ['code' => $code]);
                                    break;
                                }
                            }
                            
                            // 如果阿里云返回了条码位置但数据为空，尝试图片预处理后重新识别
                            if (!$hasValidData && !empty($details)) {
                                \Log::warning('阿里云检测到条码位置但数据为空', ['details' => $details]);
                                $hasEmptyBarcode = true;
                                
                                // 尝试预处理图片后重新识别
                                $enhancedResult = $this->recognizeBarcodeWithEnhancement($imageUrl, $details);
                                if ($enhancedResult) {
                                    \Log::info('图片增强后识别成功', ['result' => $enhancedResult]);
                                    // 用增强识别结果替换空数据
                                    foreach ($details as $detailIdx => &$detail) {
                                        if (isset($enhancedResult[$detailIdx]) && $enhancedResult[$detailIdx] !== '') {
                                            $detail['data'] = $enhancedResult[$detailIdx];
                                            $detail['Data'] = $enhancedResult[$detailIdx];
                                        }
                                    }
                                    $barInfo['barCodeDetails'] = $details;
                                    $barInfo['BarCodeDetails'] = $details;
                                    $sub['barCodeInfo'] = $barInfo;
                                    $sub['BarCodeInfo'] = $barInfo;
                                }
                            }
                        }
                    }
                    
                    $data['subImages'] = $subImages;
                    $data['SubImages'] = $subImages;
                    
                    // 如果有空条码，记录警告
                    if ($hasEmptyBarcode) {
                        \Log::warning('存在无法识别的条码，可能需要人工确认');
                    }
                    
                    return $data;
                }
            }
        } catch (\Exception $e) {
            \Log::error('BarCode type failed', ['error' => $e->getMessage()]);
        }

        // 方案2：使用 Advanced 类型，有时也能识别条码
        try {
            $req = new RecognizeAllTextRequest();
            $req->url = $imageUrl;
            $req->type = "Advanced";
            $req->outputCoordinate = "rectangle";
            $req->outputOricoord = true;
            
            $res = $this->client->recognizeAllText($req);
            $data = json_decode(json_encode($res->body->data), true);
            
            if (is_array($data) && !empty($data)) {
                \Log::info('使用 Advanced 类型识别成功');
                return $data;
            }
        } catch (\Exception $e) {
            \Log::warning('Advanced type for barcode failed: ' . $e->getMessage());
        }

        // 所有方案都失败，返回空结果（不报错，避免影响整体流程）
        \Log::warning('所有条码识别方案均失败');
        return [
            "subImages" => [],
            "SubImages" => [],
        ];
    }

    /**
     * 图片增强后重新识别条码
     * 当阿里云检测到条码位置但无法解码时，裁剪并增强图片后重新识别
     */
    private function recognizeBarcodeWithEnhancement($imageUrl, $barcodeDetails)
    {
        try {
            \Log::info('开始图片增强识别', ['url' => $imageUrl]);
            
            // 下载原图
            $imageContent = @file_get_contents($imageUrl);
            if ($imageContent === false) {
                \Log::error('图片下载失败');
                return null;
            }
            
            $img = @imagecreatefromstring($imageContent);
            if (!$img) {
                \Log::error('无法解析图片');
                return null;
            }
            
            $results = [];
            
            // 对每个检测到的条码区域进行处理
            foreach ($barcodeDetails as $idx => $detail) {
                $points = $detail['barCodePoints'] ?? ($detail['BarCodePoints'] ?? null);
                if (!$points || count($points) < 4) {
                    continue;
                }
                
                // 计算边界框
                $xs = array_column($points, 'x');
                $ys = array_column($points, 'y');
                $x1 = min($xs);
                $y1 = min($ys);
                $x2 = max($xs);
                $y2 = max($ys);
                
                $width = $x2 - $x1;
                $height = $y2 - $y1;
                
                \Log::info('条码区域', ['x' => $x1, 'y' => $y1, 'width' => $width, 'height' => $height]);
                
                // 扩展边界（增加10%的边距）
                $margin = 0.1;
                $x1 = max(0, $x1 - $width * $margin);
                $y1 = max(0, $y1 - $height * $margin);
                $width = $width * (1 + 2 * $margin);
                $height = $height * (1 + 2 * $margin);
                
                // 裁剪条码区域
                $cropped = imagecrop($img, [
                    'x' => (int)$x1,
                    'y' => (int)$y1,
                    'width' => (int)$width,
                    'height' => (int)$height
                ]);
                
                if (!$cropped) {
                    \Log::warning('裁剪失败');
                    continue;
                }
                
                // 放大图片（提高识别率）
                $scale = 5;
                $scaledWidth = (int)($width * $scale);
                $scaledHeight = (int)($height * $scale);
                $scaled = imagecreatetruecolor($scaledWidth, $scaledHeight);
                
                // 使用高质量重采样
                imagecopyresampled($scaled, $cropped, 0, 0, 0, 0, $scaledWidth, $scaledHeight, (int)$width, (int)$height);
                
                // 增强对比度
                imagefilter($scaled, IMG_FILTER_CONTRAST, -20);
                
                // 保存到临时文件
                $tempFile = tempnam(sys_get_temp_dir(), 'barcode_enhanced_') . '.jpg';
                imagejpeg($scaled, $tempFile, 100);
                
                \Log::info('增强图片已保存', ['file' => $tempFile, 'size' => filesize($tempFile)]);
                
                // 尝试用阿里云识别增强后的图片（使用文件流）
                try {
                    $req = new RecognizeAllTextRequest();
                    // 使用 GuzzleHttp\Psr7\Utils::streamFor 创建流对象
                    $stream = \GuzzleHttp\Psr7\Utils::streamFor(fopen($tempFile, 'r'));
                    $req->body = $stream;
                    $req->type = "BarCode";
                    
                    $res = $this->client->recognizeAllText($req);
                    $enhancedData = json_decode(json_encode($res->body->data), true);
                    
                    \Log::info('增强图片识别返回', ['data' => $enhancedData]);
                    
                    // 提取条码数据
                    $subImages = $enhancedData['subImages'] ?? ($enhancedData['SubImages'] ?? []);
                    foreach ($subImages as $sub) {
                        $barInfo = $sub['barCodeInfo'] ?? ($sub['BarCodeInfo'] ?? null);
                        if ($barInfo) {
                            $details = $barInfo['barCodeDetails'] ?? ($barInfo['BarCodeDetails'] ?? []);
                            foreach ($details as $d) {
                                $code = $d['data'] ?? ($d['Data'] ?? '');
                                if ($code !== '') {
                                    \Log::info('增强识别成功', ['code' => $code]);
                                    $results[$idx] = $code;
                                    break 2;
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::warning('增强图片识别失败', ['error' => $e->getMessage()]);
                }
                
                // 清理
                imagedestroy($cropped);
                imagedestroy($scaled);
                @unlink($tempFile);
            }
            
            imagedestroy($img);
            
            return !empty($results) ? $results : null;
            
        } catch (\Exception $e) {
            \Log::error('图片增强识别异常', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
