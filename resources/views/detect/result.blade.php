<h3>识别结果</h3>

@if(strpos($image, 'kwan.com.cn') !== false)
<div style="background: #fff3cd; border: 2px solid #ffc107; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
    <strong>⚠️ 开发模式警告：</strong><br>
    当前使用硬编码的测试图片：<code>{{ $image }}</code><br>
    <span style="color: #d63384;">OCR/条码识别使用的是硬编码图片，YOLO使用的是你上传的图片！</span><br>
    <small>如需测试新图片，请将新图片上传到公网服务器并修改 DetectController.php 中的 URL</small>
</div>
@endif

@if(isset($debug))
<div style="background: #f0f0f0; padding: 10px; margin-bottom: 20px; border-radius: 5px;">
    <strong>🔍 调试信息：</strong><br>
    条码识别数量: {{ $debug['barcode_count'] }} 个<br>
    OCR识别数量: {{ $debug['ocr_count'] }} 个<br>
    YOLO检测数量: {{ $debug['yolo_count'] }} 个<br>
    总标注框数: {{ $debug['total_annotations'] }} 个<br>
    融合后总数: {{ $debug['final_count'] }} 个
</div>
@endif

<div id="imgWrap" style="position:relative; display:inline-block; border:1px solid #ddd; overflow:hidden;">
    <img id="detectImg" src="{{ $image }}" style="max-width:500px; width:100%; height:auto; display:block;">
    <div id="overlay" style="position:absolute; left:0; top:0; right:0; bottom:0; pointer-events:none;"></div>
</div>

<script>
(() => {
    const annotations = @json($annotations ?? []);
    const img = document.getElementById('detectImg');
    const overlay = document.getElementById('overlay');

    function render() {
        overlay.innerHTML = '';
        if (!img.naturalWidth || !img.naturalHeight) return;

        const scaleX = img.clientWidth / img.naturalWidth;
        const scaleY = img.clientHeight / img.naturalHeight;

        annotations.forEach(a => {
            const b = a.bbox;
            if (!b || b.length < 4) return;
            // b 为原图坐标 [x,y,w,h]，缩放到展示坐标并裁剪到图片范围
            let x = b[0] * scaleX;
            let y = b[1] * scaleY;
            let w = b[2] * scaleX;
            let h = b[3] * scaleY;

            // 基础防御：避免负数/溢出导致的超大框
            if (!isFinite(x) || !isFinite(y) || !isFinite(w) || !isFinite(h)) return;
            if (w <= 0 || h <= 0) return;

            // 裁剪到图片可视区域
            const maxW = img.clientWidth;
            const maxH = img.clientHeight;
            if (x < 0) { w += x; x = 0; }
            if (y < 0) { h += y; y = 0; }
            if (x + w > maxW) w = maxW - x;
            if (y + h > maxH) h = maxH - y;
            if (w <= 0 || h <= 0) return;

            // 条码=绿 / OCR=橙 / YOLO=蓝
            const color = (a.source === 'barcode') ? '#22c55e' : (a.source === 'ocr') ? '#f97316' : '#3b82f6';

            const box = document.createElement('div');
            box.style.position = 'absolute';
            box.style.left = `${x}px`;
            box.style.top = `${y}px`;
            box.style.width = `${w}px`;
            box.style.height = `${h}px`;
            box.style.border = `2px solid ${color}`;
            box.style.boxSizing = 'border-box';
            box.style.background = 'rgba(0,0,0,0.02)';

            const tag = document.createElement('div');
            const total = (a.total_count != null) ? a.total_count : a.count;
            tag.textContent = `${a.name} | ${a.source} | 数量:${a.count}/总${total}`;
            tag.style.position = 'absolute';
            tag.style.left = '0';
            tag.style.top = '-22px';
            tag.style.maxWidth = '360px';
            tag.style.whiteSpace = 'nowrap';
            tag.style.overflow = 'hidden';
            tag.style.textOverflow = 'ellipsis';
            tag.style.fontSize = '12px';
            tag.style.lineHeight = '18px';
            tag.style.padding = '2px 6px';
            tag.style.color = '#fff';
            tag.style.background = color;
            tag.style.borderRadius = '4px';
            tag.style.boxShadow = '0 1px 4px rgba(0,0,0,.25)';

            box.appendChild(tag);
            overlay.appendChild(box);
        });
    }

    img.addEventListener('load', render);
    window.addEventListener('resize', render);
})();
</script>

<table border="1">
<tr>
    <th>SKU</th>
    <th>来源</th>
    <th>可信度</th>
    <th>数量</th>
</tr>

@foreach($results as $r)
<tr>
    <td>{{ $r['name'] }}</td>
    <td>{{ is_array($r['sources'] ?? null) ? implode(', ', $r['sources']) : ($r['source'] ?? '') }}</td>
    <td>{{ round($r['confidence'],2) }}</td>
    <td>
        <input value="{{ $r['count'] }}">
    </td>
</tr>
@endforeach
</table>
