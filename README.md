## Goods OCR Inventory

基于 **阿里云 OCR / 条码识别 + YOLO 目标检测** 的箱货盘点系统，用于自动统计堆叠纸箱的 SKU 数量，辅助仓库/门店做库存盘点。
项目托管在 GitHub：[goods-ocr-inventory](https://github.com/blingkwan/goods-ocr-inventory)。

---

### 功能特性

- **多源识别融合**
  - **条码识别（barcode）**：调用阿里云条码识别接口，根据条码精确匹配 SKU，优先级最高。
  - **OCR 文本识别（ocr）**：调用阿里云通用文字识别，通过数据库中维护的 `keywords` 字段模糊匹配 SKU。
  - **YOLO 目标检测（yolo）**：本地 YOLO 模型检测每个纸箱的位置，提供箱体数量基础。

- **智能去重与数量统计**
  - 对同一张图来自三方的检测结果进行 **融合去重**：
    - 同一 SKU 下，使用 **IoU + IoM**（交集/最小面积）判断是否为同一箱（解决“小 OCR 框在大 YOLO 框内”的情况）。
    - 按优先级：`barcode > ocr > yolo`，高优先级信息会覆盖低优先级。
  - 最终得到每个 SKU 的：
    - 盘点数量（count）
    - 综合置信度（confidence）
    - 识别来源列表（sources）

- **可视化标注界面**
  - 在原图上叠加不同颜色的框：
    - 绿色：barcode
    - 橙色：ocr
    - 蓝色：yolo
  - 标签格式：`SKU 名称 | 来源 | 数量:1/总N`。
  - 下方表格展示融合后的 SKU 列表，可手工修改数量。

- **检测记录存档**
  - 每次检测会写入 `detect_records` 表（模型 `DetectRecord`），记录：
    - 原始图片路径
    - 条码 / OCR / YOLO 原始结果 JSON
    - 融合后的最终结果 JSON
    - 最大置信度、是否需要人工确认等字段。

---

### 技术栈

- **后端框架**：Laravel (PHP)
- **前端视图**：Blade 模板 + 原生 JavaScript/CSS
- **识别服务**
  - 阿里云视觉服务：OCR / 条码识别
  - 本地 YOLO 推理服务：通过 `YoloService` 调用
- **数据库**：MySQL（或其它 Laravel 支持的关系型数据库）

---

### 主要代码结构（节选）

- `app/Http/Controllers/DetectController.php`
  - 处理图片上传、调用阿里云服务与 YOLO、调 `FusionService` 做结果融合，渲染结果页面。

- `app/Services/AliVisionService.php`
  - 封装阿里云 OCR / 条码识别 HTTP 调用及结果解析。

- `app/Services/YoloService.php`
  - 封装 YOLO 检测调用（例如调用本地 Python 脚本或者 HTTP 服务）。

- `app/Services/FusionService.php`
  - 实现按 SKU 聚合、IoU/IoM 去重、优先级融合与数量统计的核心逻辑。

- `resources/views/detect/form.blade.php`
  - 图片上传表单页。

- `resources/views/detect/result.blade.php`
  - 识别结果展示页（带图片标注和 SKU 表格）。

- `sql/`
  - 数据库相关的 SQL 脚本（如初始化表结构、示例数据等）。

---

### 快速开始

####
1. 克隆项目

git clone https://github.com/blingkwan/goods-ocr-inventory.git
cd goods-ocr-inventory

2. 安装依赖
composer install
如需前端构建：
npm installnpm run build    # 或 npm run dev
3. 环境配置
复制 .env 示例：
   cp .env.example .env
根据实际环境修改 .env，包括但不限于：
应用基础配置：APP_NAME、APP_URL 等
数据库配置：DB_HOST、DB_DATABASE、DB_USERNAME、DB_PASSWORD
阿里云配置：ALIYUN_ACCESS_KEY_ID、ALIYUN_ACCESS_KEY_SECRET、ALIYUN_OCR_ENDPOINT 等
YOLO 服务相关配置：如服务地址或脚本路径（视 YoloService 实现而定）
生成应用密钥：
   php artisan key:generate
4. 数据库迁移与初始化
php artisan migrate
如果 sql/ 目录下提供了初始化脚本，可以根据需要手动导入，如：
# 示例，具体文件名视项目而定mysql -u root -p your_db_name < sql/init_skus.sql
确保 skus 表中包含至少以下信息：
name：SKU 名称（需与 YOLO 输出的名称对应）
barcode：条码（供条码识别匹配）
keywords：关键字列表（逗号分隔，供 OCR 模糊匹配）
5. 启动开发服务器
php artisan serve
默认访问：http://127.0.0.1:8000
根据路由配置，识别入口通常为：
上传页面：/detect 或类似路径（参考 routes/web.php）
使用说明
打开识别页面，在浏览器中上传一张包含多个纸箱的照片。
后端流程：
保存上传图片到本地。
调用阿里云条码识别接口，尝试直接按条码匹配 SKU。
调用阿里云 OCR 接口，将文本内容与 SKU 的 keywords 进行模糊/包含匹配。
使用 YOLO 检测所有纸箱的边界框。
使用 FusionService 按 SKU 聚合、对 bbox 做 IoU/IoM 去重，并根据来源优先级生成最终结果。
前端展示：
在图片上画出条码 / OCR / YOLO 的融合后标注框。
下方表格按 SKU 展示来源、综合置信度和数量。
如有需要，可以在表格里手工调整数量并进行后续保存/导出（视你实现的功能而定）。
安全与隐私
.env 已加入 .gitignore，不会被提交到 Git 仓库。
仓库中只提供 .env.example 作为参考模板，不包含任何真实的密钥或账号信息。
部署到生产环境前，请务必：
替换为自己的阿里云 AccessKey / YOLO 服务地址。
使用 HTTPS 访问站点，避免敏感信息在传输过程中泄露。
TODO / 规划（示例）
[ ] 增加 Web 管理后台查看历史盘点记录、手工修正结果
[ ] 支持导出盘点结果为 Excel / CSV
[ ] 增加简单的权限系统（仅登录用户可使用识别功能）
[ ] Docker 化部署脚本
