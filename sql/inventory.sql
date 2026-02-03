/*
Navicat MySQL Data Transfer

Source Server         : 本地
Source Server Version : 80012
Source Host           : localhost:3306
Source Database       : inventory

Target Server Type    : MYSQL
Target Server Version : 80012
File Encoding         : 65001

Date: 2026-02-03 16:40:05
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for detect_records
-- ----------------------------
DROP TABLE IF EXISTS `detect_records`;
CREATE TABLE `detect_records` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `image_path` varchar(255) DEFAULT NULL,
  `barcode_json` json DEFAULT NULL,
  `ocr_json` json DEFAULT NULL,
  `yolo_json` json DEFAULT NULL,
  `final_json` json DEFAULT NULL,
  `confidence` float DEFAULT NULL,
  `need_manual` tinyint(4) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of detect_records
-- ----------------------------
INSERT INTO `detect_records` VALUES ('1', 'uploads/9rwTzL5S0EmhFMN3M81fBMfc2FEf5C5M5wpMOa6e.jpg', '[]', '[{\"bbox\": [1467, 95.5, 150, 27], \"name\": \"carton_b\", \"count\": 1, \"sku_id\": 4, \"source\": \"ocr\", \"confidence\": 0.75}, {\"bbox\": [513.5, 169, 147, 14], \"name\": \"carton\", \"count\": 1, \"sku_id\": 1, \"source\": \"ocr\", \"confidence\": 0.75}, {\"bbox\": [103.5, 217.5, 145, 13], \"name\": \"carton\", \"count\": 1, \"sku_id\": 1, \"source\": \"ocr\", \"confidence\": 0.75}, {\"bbox\": [949.5, 227, 143, 14], \"name\": \"carton\", \"count\": 1, \"sku_id\": 1, \"source\": \"ocr\", \"confidence\": 0.75}, {\"bbox\": [494, 507, 136, 12], \"name\": \"carton\", \"count\": 1, \"sku_id\": 1, \"source\": \"ocr\", \"confidence\": 0.75}, {\"bbox\": [937, 517.5, 136, 11], \"name\": \"carton\", \"count\": 1, \"sku_id\": 1, \"source\": \"ocr\", \"confidence\": 0.75}, {\"bbox\": [1414, 428, 12, 216], \"name\": \"carton\", \"count\": 1, \"sku_id\": 1, \"source\": \"ocr\", \"confidence\": 0.75}, {\"bbox\": [182, 503.5, 6, 79], \"name\": \"carton\", \"count\": 1, \"sku_id\": 1, \"source\": \"ocr\", \"confidence\": 0.75}, {\"bbox\": [937.5, 814, 83, 16], \"name\": \"carton\", \"count\": 1, \"sku_id\": 1, \"source\": \"ocr\", \"confidence\": 0.75}, {\"bbox\": [1301, 812, 86, 16], \"name\": \"carton\", \"count\": 1, \"sku_id\": 1, \"source\": \"ocr\", \"confidence\": 0.75}, {\"bbox\": [628.5, 1076, 83, 16], \"name\": \"carton\", \"count\": 1, \"sku_id\": 1, \"source\": \"ocr\", \"confidence\": 0.75}, {\"bbox\": [955.5, 1087, 79, 16], \"name\": \"carton\", \"count\": 1, \"sku_id\": 1, \"source\": \"ocr\", \"confidence\": 0.75}, {\"bbox\": [246.5, 1108, 79, 16], \"name\": \"carton\", \"count\": 1, \"sku_id\": 1, \"source\": \"ocr\", \"confidence\": 0.75}]', '[{\"bbox\": [1187, 425, 394, 297], \"name\": \"carton\", \"count\": 1, \"sku_id\": 1, \"source\": \"yolo\", \"confidence\": 0.13876336812973022}, {\"bbox\": [106, 714, 357, 271], \"name\": \"carton\", \"count\": 1, \"sku_id\": 1, \"source\": \"yolo\", \"confidence\": 0.12282101064920424}, {\"bbox\": [452, 692, 352, 287], \"name\": \"carton\", \"count\": 1, \"sku_id\": 1, \"source\": \"yolo\", \"confidence\": 0.07136186957359314}, {\"bbox\": [29, 421, 405, 289], \"name\": \"carton\", \"count\": 1, \"sku_id\": 1, \"source\": \"yolo\", \"confidence\": 0.06606671214103699}, {\"bbox\": [424, 406, 378, 288], \"name\": \"carton\", \"count\": 1, \"sku_id\": 1, \"source\": \"yolo\", \"confidence\": 0.06272377818822861}, {\"bbox\": [2, 103, 394, 310], \"name\": \"carton\", \"count\": 1, \"sku_id\": 1, \"source\": \"yolo\", \"confidence\": 0.05991756170988083}, {\"bbox\": [410, 83, 397, 317], \"name\": \"carton\", \"count\": 1, \"sku_id\": 1, \"source\": \"yolo\", \"confidence\": 0.05394124612212181}, {\"bbox\": [807, 428, 357, 285], \"name\": \"carton\", \"count\": 1, \"sku_id\": 1, \"source\": \"yolo\", \"confidence\": 0.05088523030281067}]', '[{\"bbox\": [1467, 95.5, 150, 27], \"name\": \"carton_b\", \"count\": 1, \"bboxes\": [[1467, 95.5, 150, 27]], \"sku_id\": 4, \"source\": \"ocr\", \"sources\": [\"ocr\"], \"confidence\": 0.75, \"bbox_sources\": [\"ocr\"], \"bbox_confidences\": [0.75]}, {\"bbox\": [513.5, 169, 147, 14], \"name\": \"carton\", \"count\": 14, \"bboxes\": [[513.5, 169, 147, 14], [103.5, 217.5, 145, 13], [949.5, 227, 143, 14], [494, 507, 136, 12], [937, 517.5, 136, 11], [1414, 428, 12, 216], [182, 503.5, 6, 79], [937.5, 814, 83, 16], [1301, 812, 86, 16], [628.5, 1076, 83, 16], [955.5, 1087, 79, 16], [246.5, 1108, 79, 16], [106, 714, 357, 271], [452, 692, 352, 287]], \"sku_id\": 1, \"source\": \"ocr\", \"sources\": [\"ocr\", \"yolo\"], \"confidence\": 0.75, \"bbox_sources\": [\"ocr\", \"ocr\", \"ocr\", \"ocr\", \"ocr\", \"ocr\", \"ocr\", \"ocr\", \"ocr\", \"ocr\", \"ocr\", \"ocr\", \"yolo\", \"yolo\"], \"bbox_confidences\": [0.75, 0.75, 0.75, 0.75, 0.75, 0.75, 0.75, 0.75, 0.75, 0.75, 0.75, 0.75, 0.12282101064920424, 0.07136186957359314]}]', '0.75', '0', '2026-02-03 08:17:45', '2026-02-03 16:39:32');

-- ----------------------------
-- Table structure for skus
-- ----------------------------
DROP TABLE IF EXISTS `skus`;
CREATE TABLE `skus` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `barcode` varchar(50) DEFAULT NULL,
  `keywords` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of skus
-- ----------------------------
INSERT INTO `skus` VALUES ('1', 'carton', '11111', 'Beef Dice,Pre-cooked Chicken Karaage', null, null);
INSERT INTO `skus` VALUES ('2', 'snack_bag', '22222', null, null, null);
INSERT INTO `skus` VALUES ('3', 'seafood', '33333', null, null, null);
INSERT INTO `skus` VALUES ('4', 'carton_b', '19315822022795', 'PRAWNS', null, null);
INSERT INTO `skus` VALUES ('5', 'carton_w', '5555', null, null, null);
