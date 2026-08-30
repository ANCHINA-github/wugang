<?php
// SVG 预览页面
// 使用方法：将此文件放到需要浏览的目录中，或通过 ?dir= 参数指定目录

date_default_timezone_set('Asia/Shanghai');

// 获取目标目录
$target_dir = isset($_GET['dir']) ? trim($_GET['dir']) : __DIR__;
$target_dir = realpath($target_dir) ?: __DIR__;

// 安全限制：不允许超出当前脚本所在目录的上一级（可按需调整）
$base_dir = __DIR__;
if (strpos($target_dir, $base_dir) !== 0 && $target_dir !== $base_dir) {
    // 如果指定了外部目录，也允许，但记录一下
}

// 获取目录中的 SVG 文件
function getSvgFiles($dir) {
    $files = [];
    if (!is_dir($dir)) return $files;
    
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $full_path = $dir . DIRECTORY_SEPARATOR . $item;
        
        if (is_file($full_path) && strtolower(pathinfo($item, PATHINFO_EXTENSION)) === 'svg') {
            $files[] = [
                'name' => $item,
                'path' => $full_path,
                'size' => filesize($full_path),
                'modified' => filemtime($full_path),
                'rel_path' => $item,
            ];
        }
    }
    
    // 按文件名排序
    usort($files, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    
    return $files;
}

// 获取子目录列表
function getSubDirs($dir) {
    $dirs = [];
    if (!is_dir($dir)) return $dirs;
    
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $full_path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($full_path)) {
            $dirs[] = [
                'name' => $item,
                'path' => $full_path,
            ];
        }
    }
    
    usort($dirs, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    
    return $dirs;
}

// 获取父目录
function getParentDir($dir) {
    $parent = dirname($dir);
    // 不允许跳出基础目录
    if (strpos($parent, $base_dir ?? __DIR__) === false && $parent !== dirname(__DIR__)) {
        return null;
    }
    return $parent !== $dir ? $parent : null;
}

$svg_files = getSvgFiles($target_dir);
$sub_dirs = getSubDirs($target_dir);
$parent_dir = getParentDir($target_dir);

// 格式化文件大小
function formatSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

// 当前相对路径显示
$display_path = str_replace($base_dir ?? __DIR__, '', $target_dir);
if ($display_path === '') $display_path = '/';

// 视图模式
$view = $_GET['view'] ?? 'grid'; // grid 或 list
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SVG 预览 - <?php echo htmlspecialchars($display_path); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            min-height: 100vh;
        }

        /* 顶部导航 */
        .topbar {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0,0,0,.05);
        }
        .topbar h1 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #6366f1;
            white-space: nowrap;
        }
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: .35rem;
            font-size: .85rem;
            color: #64748b;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }
        .breadcrumb a {
            color: #6366f1;
            text-decoration: none;
        }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb .sep { color: #cbd5e1; }

        .topbar-right {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .view-toggle {
            display: flex;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            overflow: hidden;
        }
        .view-toggle a {
            padding: .35rem .6rem;
            font-size: .8rem;
            color: #64748b;
            text-decoration: none;
            background: #fff;
            border-right: 1px solid #e2e8f0;
        }
        .view-toggle a:last-child { border-right: none; }
        .view-toggle a.active { background: #6366f1; color: #fff; }
        .btn {
            padding: .4rem .8rem;
            border-radius: 6px;
            font-size: .8rem;
            text-decoration: none;
            border: 1px solid #e2e8f0;
            color: #475569;
            background: #fff;
        }
        .btn:hover { background: #f1f5f9; }

        /* 主体 */
        .container { max-width: 1400px; margin: 0 auto; padding: 1.5rem; }

        /* 子目录 */
        .dirs-row {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            margin-bottom: 1.5rem;
        }
        .dir-chip {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .4rem .8rem;
            background: #eef2ff;
            color: #4f46e5;
            border-radius: 6px;
            text-decoration: none;
            font-size: .85rem;
            transition: background .15s;
        }
        .dir-chip:hover { background: #e0e7ff; }
        .dir-chip.parent { background: #f1f5f9; color: #64748b; }

        /* 网格视图 */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 1rem;
        }
        .card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            transition: all .2s;
            cursor: pointer;
        }
        .card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,.08);
            transform: translateY(-2px);
            border-color: #c7d2fe;
        }
        .card .preview {
            width: 100%;
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            border-bottom: 1px solid #f1f5f9;
            overflow: hidden;
        }
        .card .preview svg,
        .card .preview img {
            max-width: 70%;
            max-height: 70%;
        }
        .card .info {
            padding: .6rem .75rem;
        }
        .card .name {
            font-size: .8rem;
            font-weight: 500;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .card .meta {
            font-size: .7rem;
            color: #94a3b8;
            margin-top: .2rem;
        }

        /* 列表视图 */
        .list-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .list-table th {
            background: #f8fafc;
            text-align: left;
            padding: .6rem .8rem;
            font-size: .75rem;
            text-transform: uppercase;
            color: #94a3b8;
            font-weight: 600;
            border-bottom: 1px solid #e2e8f0;
        }
        .list-table td {
            padding: .6rem .8rem;
            font-size: .85rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .list-table tr:hover { background: #fafbfc; }
        .list-table tr:last-child td { border-bottom: none; }
        .list-table .thumb {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            border-radius: 6px;
            overflow: hidden;
        }
        .list-table .thumb svg,
        .list-table .thumb img {
            max-width: 80%;
            max-height: 80%;
        }
        .list-table .name-cell {
            font-weight: 500;
            color: #1e293b;
        }
        .list-table .actions a {
            color: #6366f1;
            text-decoration: none;
            font-size: .8rem;
            margin-right: .5rem;
        }
        .list-table .actions a:hover { text-decoration: underline; }

        /* 空状态 */
        .empty {
            text-align: center;
            padding: 4rem 2rem;
            color: #94a3b8;
        }
        .empty svg { width: 48px; height: 48px; margin-bottom: 1rem; opacity: .4; }
        .empty h3 { font-size: 1rem; color: #64748b; margin-bottom: .25rem; }

        /* 模态框 */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .75);
            z-index: 200;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: #fff;
            border-radius: 12px;
            max-width: 800px;
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .modal-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-header h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #94a3b8;
            cursor: pointer;
            padding: 0 .5rem;
            line-height: 1;
        }
        .modal-close:hover { color: #475569; }
        .modal-body {
            padding: 1.5rem;
            overflow: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 200px;
            background: #f8fafc;
        }
        .modal-body svg,
        .modal-body img {
            max-width: 100%;
            max-height: 60vh;
        }
        .modal-footer {
            padding: .75rem 1.25rem;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: .5rem;
            font-size: .8rem;
            color: #64748b;
        }
        .modal-footer code {
            background: #f1f5f9;
            padding: .15rem .4rem;
            border-radius: 4px;
            font-size: .75rem;
        }

        /* 代码查看 */
        .code-view {
            display: none;
            padding: 1.5rem;
            overflow: auto;
            max-height: 60vh;
            background: #1e293b;
            color: #e2e8f0;
            font-family: "Fira Code", "Consolas", monospace;
            font-size: .8rem;
            line-height: 1.6;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .code-view.active { display: block; }
        .modal-body.hidden { display: none; }

        /* 统计信息 */
        .stats {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            font-size: .8rem;
            color: #64748b;
        }
        .stats span {
            background: #fff;
            padding: .3rem .6rem;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }

        /* 响应式 */
        @media (max-width: 640px) {
            .topbar { padding: .5rem .75rem; }
            .topbar h1 { display: none; }
            .container { padding: .75rem; }
            .grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: .6rem; }
        }
    </style>
</head>
<body>

<div class="topbar">
    <h1>🎨 SVG 预览</h1>
    <nav class="breadcrumb">
        <a href="?dir=<?php echo urlencode(__DIR__); ?>">根目录</a>
        <?php
        // 生成面包屑
        $base = __DIR__;
        $rel = ltrim(str_replace($base, '', $target_dir), DIRECTORY_SEPARATOR);
        if ($rel !== '') {
            $parts = explode(DIRECTORY_SEPARATOR, $rel);
            $accum = '';
            foreach ($parts as $i => $part) {
                $accum .= ($accum ? DIRECTORY_SEPARATOR : '') . $part;
                echo '<span class="sep">/</span>';
                $full = $base . DIRECTORY_SEPARATOR . $accum;
                if ($i < count($parts) - 1) {
                    echo '<a href="?dir=' . urlencode($full) . '">' . htmlspecialchars($part) . '</a>';
                } else {
                    echo '<span>' . htmlspecialchars($part) . '</span>';
                }
            }
        }
        ?>
    </nav>
    <div class="topbar-right">
        <div class="view-toggle">
            <a href="?dir=<?php echo urlencode($target_dir); ?>&view=grid" class="<?php echo $view==='grid'?'active':''; ?>">▦ 网格</a>
            <a href="?dir=<?php echo urlencode($target_dir); ?>&view=list" class="<?php echo $view==='list'?'active':''; ?>">☰ 列表</a>
        </div>
        <?php if ($parent_dir): ?>
            <a href="?dir=<?php echo urlencode($parent_dir); ?>&view=<?php echo $view; ?>" class="btn">⬆ 上级</a>
        <?php endif; ?>
    </div>
</div>

<div class="container">

    <!-- 统计 -->
    <div class="stats">
        <span>📁 当前目录：<strong><?php echo htmlspecialchars($display_path); ?></strong></span>
        <span>🖼 SVG 文件：<strong><?php echo count($svg_files); ?></strong> 个</span>
        <span>📂 子目录：<strong><?php echo count($sub_dirs); ?></strong> 个</span>
    </div>

    <!-- 子目录导航 -->
    <?php if ($parent_dir || !empty($sub_dirs)): ?>
    <div class="dirs-row">
        <?php if ($parent_dir): ?>
            <a href="?dir=<?php echo urlencode($parent_dir); ?>&view=<?php echo $view; ?>" class="dir-chip parent">⬆ 上级目录</a>
        <?php endif; ?>
        <?php foreach ($sub_dirs as $d): ?>
            <a href="?dir=<?php echo urlencode($d['path']); ?>&view=<?php echo $view; ?>" class="dir-chip">📁 <?php echo htmlspecialchars($d['name']); ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 空状态 -->
    <?php if (empty($svg_files)): ?>
        <div class="empty">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            <h3>该目录下没有 SVG 文件</h3>
            <p>试试切换到其他目录，或者上传一些 .svg 文件</p>
        </div>
    <?php endif; ?>

    <!-- 网格视图 -->
    <?php if ($view === 'grid' && !empty($svg_files)): ?>
    <div class="grid">
        <?php foreach ($svg_files as $file): ?>
        <div class="card" onclick="openModal('<?php echo urlencode($file['name']); ?>', '<?php echo urlencode($target_dir); ?>', '<?php echo htmlspecialchars(addslashes($file['name'])); ?>')">
            <div class="preview">
                <?php
                // 直接内嵌 SVG 预览
                $svg_content = file_get_contents($file['path']);
                // 移除可能的 XML 声明以方便嵌入
                $svg_content = preg_replace('/<\?xml[^>]*>\s*/', '', $svg_content);
                echo $svg_content;
                ?>
            </div>
            <div class="info">
                <div class="name" title="<?php echo htmlspecialchars($file['name']); ?>"><?php echo htmlspecialchars($file['name']); ?></div>
                <div class="meta"><?php echo formatSize($file['size']); ?> · <?php echo date('m-d H:i', $file['modified']); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 列表视图 -->
    <?php if ($view === 'list' && !empty($svg_files)): ?>
    <table class="list-table">
        <thead>
            <tr>
                <th style="width:48px"></th>
                <th>文件名</th>
                <th>大小</th>
                <th>修改时间</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($svg_files as $file): ?>
            <tr>
                <td>
                    <div class="thumb">
                        <?php
                        $svg_content = file_get_contents($file['path']);
                        $svg_content = preg_replace('/<\?xml[^>]*>\s*/', '', $svg_content);
                        echo $svg_content;
                        ?>
                    </div>
                </td>
                <td class="name-cell"><?php echo htmlspecialchars($file['name']); ?></td>
                <td><?php echo formatSize($file['size']); ?></td>
                <td><?php echo date('Y-m-d H:i:s', $file['modified']); ?></td>
                <td class="actions">
                    <a href="#" onclick="event.preventDefault(); openModal('<?php echo urlencode($file['name']); ?>', '<?php echo urlencode($target_dir); ?>', '<?php echo htmlspecialchars(addslashes($file['name'])); ?>')">预览</a>
                    <a href="?dir=<?php echo urlencode($target_dir); ?>&download=<?php echo urlencode($file['name']); ?>">下载</a>
                    <a href="#" onclick="event.preventDefault(); openCode('<?php echo urlencode($file['name']); ?>', '<?php echo urlencode($target_dir); ?>')">源码</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

</div>

<!-- 预览模态框 -->
<div class="modal-overlay" id="modal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modal-title">SVG 预览</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="modal-body">
            <!-- SVG 内容将在这里显示 -->
        </div>
        <div class="modal-footer" id="modal-footer">
            <!-- 文件信息 -->
        </div>
        <div class="code-view" id="code-view"></div>
    </div>
</div>

<script>
    function openModal(filename, dir, displayName) {
        const overlay = document.getElementById('modal');
        const body = document.getElementById('modal-body');
        const footer = document.getElementById('modal-footer');
        const codeView = document.getElementById('code-view');
        const title = document.getElementById('modal-title');

        title.textContent = decodeURIComponent(displayName || filename);
        body.classList.remove('hidden');
        codeView.classList.remove('active');

        // 通过 AJAX 加载 SVG 内容
        const url = '?dir=' + dir + '&raw=' + filename + '&t=' + Date.now();
        fetch(url)
            .then(r => r.text())
            .then(svg => {
                body.innerHTML = svg;
                // 让 SVG 可缩放
                const svgEl = body.querySelector('svg');
                if (svgEl) {
                    svgEl.style.maxWidth = '100%';
                    svgEl.style.maxHeight = '60vh';
                }
            })
            .catch(() => {
                body.innerHTML = '<p style="color:#94a3b8">加载失败</p>';
            });

        // 加载文件信息
        footer.innerHTML = '<span>📄 ' + decodeURIComponent(displayName || filename) + '</span>' +
            '<span><a href="?dir=' + dir + '&download=' + filename + '" style="color:#6366f1;text-decoration:none">⬇ 下载</a></span>' +
            '<span><a href="#" onclick="event.preventDefault();toggleCode(\'' + filename + '\',\'' + dir + '\')" style="color:#6366f1;text-decoration:none">📝 查看源码</a></span>';

        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function openCode(filename, dir) {
        const codeView = document.getElementById('code-view');
        const body = document.getElementById('modal-body');

        fetch('?dir=' + dir + '&raw=' + filename + '&t=' + Date.now())
            .then(r => r.text())
            .then(svg => {
                codeView.textContent = svg;
                body.classList.add('hidden');
                codeView.classList.add('active');
            });
    }

    function toggleCode(filename, dir) {
        const codeView = document.getElementById('code-view');
        const body = document.getElementById('modal-body');
        if (codeView.classList.contains('active')) {
            codeView.classList.remove('active');
            body.classList.remove('hidden');
        } else {
            openCode(filename, dir);
        }
    }

    function closeModal() {
        document.getElementById('modal').classList.remove('active');
        document.getElementById('modal-body').innerHTML = '';
        document.getElementById('code-view').textContent = '';
        document.body.style.overflow = '';
    }

    // ESC 关闭
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeModal();
    });

    // 点击背景关闭
    document.getElementById('modal').addEventListener('click', e => {
        if (e.target === e.currentTarget) closeModal();
    });
</script>

</body>
</html>

<?php
// ---- AJAX / 下载处理 ----
if (isset($_GET['raw']) && isset($_GET['dir'])) {
    $raw_dir = realpath(urldecode($_GET['dir'])) ?: __DIR__;
    $raw_file = basename(urldecode($_GET['raw']));
    $raw_path = $raw_dir . DIRECTORY_SEPARATOR . $raw_file;

    if (is_file($raw_path) && strtolower(pathinfo($raw_path, PATHINFO_EXTENSION)) === 'svg') {
        header('Content-Type: image/svg+xml; charset=utf-8');
        header('Cache-Control: no-cache');
        readfile($raw_path);
        exit;
    }
    http_response_code(404);
    echo 'File not found';
    exit;
}

if (isset($_GET['download']) && isset($_GET['dir'])) {
    $dl_dir = realpath(urldecode($_GET['dir'])) ?: __DIR__;
    $dl_file = basename(urldecode($_GET['download']));
    $dl_path = $dl_dir . DIRECTORY_SEPARATOR . $dl_file;

    if (is_file($dl_path) && strtolower(pathinfo($dl_path, PATHINFO_EXTENSION)) === 'svg') {
        header('Content-Type: image/svg+xml');
        header('Content-Disposition: attachment; filename="' . $dl_file . '"');
        header('Content-Length: ' . filesize($dl_path));
        readfile($dl_path);
        exit;
    }
    http_response_code(404);
    echo 'File not found';
    exit;
}
?>
