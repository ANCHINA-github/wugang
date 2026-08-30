<?php
// 开启错误显示（开发环境）
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 调整PHP配置以适应大文件上传
ini_set('upload_max_filesize', '6M');
ini_set('post_max_size', '22M');
ini_set('max_execution_time', 120);
ini_set('max_input_time', 60);
ini_set('memory_limit', '64M');
date_default_timezone_set('Asia/Shanghai');

// 定义缓存文件路径
$cacheDir = 'cache';
$rootJsonPath = 'root.json';
$postsJsonPath = 'posts.json';
$postsCachePath = $cacheDir . '/posts_cache.json';
$cacheDuration = 120; // 缓存时间（秒）

// ========== 违规用户黑名单（硬编码） ==========
$bannedUsers = [
    '2747189199' => ['msg' => '您的账号已被限制登录，如有疑问请联系管理员。'],
    '10' => ['msg' => '已注销，且禁止重新注册登录。']
    //'QQ号' => ['msg' => '提醒内容']
];

// 创建缓存目录
if (!file_exists($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// 确保JSON文件存在，不存在则创建并初始化
if (!file_exists($rootJsonPath)) {
    file_put_contents($rootJsonPath, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
if (!file_exists($postsJsonPath)) {
    file_put_contents($postsJsonPath, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// 创建img文件夹
$imgDir = 'img';
if (!file_exists($imgDir)) {
    mkdir($imgDir, 0755, true);
}

// 获取服务器上传限制信息（缓存结果）
function getUploadLimits() {
    static $limits = null;
    
    if ($limits === null) {
        $limits = [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_file_uploads' => ini_get('max_file_uploads'),
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit')
        ];
        
        // 将人类可读的大小转换为字节
        $limits['max_single_bytes'] = toBytes($limits['upload_max_filesize']);
        $limits['max_total_bytes'] = toBytes($limits['post_max_size']);
    }
    
    return $limits;
}

function toBytes($size) {
    $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
    $size = preg_replace('/[^0-9\.]/', '', $size);
    if ($unit) {
        return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
    } else {
        return round($size);
    }
}

// 新增：图片压缩函数（后台自动压缩，用户不可见）
function compressImage($sourcePath, $quality = 85) {
    if (!file_exists($sourcePath)) {
        return false;
    }
    $fileSize = filesize($sourcePath);
    // 小于1MB不压缩
    if ($fileSize < 1024 * 1024) {
        return true;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $sourcePath);
    finfo_close($finfo);

    $image = null;
    $extension = '';
    switch ($mimeType) {
        case 'image/jpeg':
        case 'image/jpg':
            $image = imagecreatefromjpeg($sourcePath);
            $extension = 'jpg';
            break;
        case 'image/png':
            $image = imagecreatefrompng($sourcePath);
            $extension = 'png';
            break;
        case 'image/gif':
            // GIF动图不压缩，直接返回成功
            return true;
        case 'image/webp':
            if (function_exists('imagewebp')) {
                $image = imagecreatefromwebp($sourcePath);
                $extension = 'webp';
            } else {
                return false;
            }
            break;
        case 'image/bmp':
            if (function_exists('imagecreatefrombmp')) {
                $image = imagecreatefrombmp($sourcePath);
                $extension = 'bmp';
            } else {
                return false;
            }
            break;
        default:
            return false;
    }

    if (!$image) {
        return false;
    }

    $tempPath = $sourcePath . '.tmp';
    $success = false;

    switch ($extension) {
        case 'jpg':
            $success = imagejpeg($image, $tempPath, $quality);
            break;
        case 'png':
            // PNG压缩级别0-9，9最高
            $success = imagepng($image, $tempPath, 9);
            break;
        case 'webp':
            $success = imagewebp($image, $tempPath, $quality);
            break;
        case 'bmp':
            $success = imagebmp($image, $tempPath, true);
            break;
    }

    imagedestroy($image);

    if ($success && file_exists($tempPath)) {
        $newSize = filesize($tempPath);
        if ($newSize < $fileSize) {
            rename($tempPath, $sourcePath);
        } else {
            unlink($tempPath); // 压缩后反而变大，保留原文件
        }
        return true;
    }

    return false;
}

// 新增：获取特定页数的帖子数据
function getPostsByPage($page = 1, $perPage = 10, $filter = 'latest') {
    global $postsCachePath, $postsJsonPath, $cacheDuration;
    
    // 获取所有数据
    if (file_exists($postsCachePath) && 
        (time() - filemtime($postsCachePath)) < $cacheDuration) {
        $cachedData = file_get_contents($postsCachePath);
        $allPostsData = json_decode($cachedData, true);
    } else {
        $sourceData = file_get_contents($postsJsonPath);
        $allPostsData = json_decode($sourceData, true);
        
        if ($allPostsData !== null) {
            usort($allPostsData, function($a, $b) {
                return strtotime($b['pdate']) - strtotime($a['pdate']);
            });
            
            file_put_contents($postsCachePath, json_encode($allPostsData, JSON_UNESCAPED_UNICODE));
        } else {
            $allPostsData = [];
        }
    }
    
    
    // 根据筛选条件过滤数据
    if ($filter === 'type1') {
        $allPostsData = array_filter($allPostsData, function($post) {
            return strpos($post['content'] ?? '', '#交友#') !== false;
        });
        $allPostsData = array_values($allPostsData); // 重新索引数组
    } elseif ($filter === 'type2') {
        $allPostsData = array_filter($allPostsData, function($post) {
            return strpos($post['content'] ?? '', '#emo#') !== false;
        });
        $allPostsData = array_values($allPostsData); // 重新索引数组
    } elseif ($filter === 'type3') {
        $allPostsData = array_filter($allPostsData, function($post) {
            return strpos($post['content'] ?? '', '#游戏#') !== false;
        });
        $allPostsData = array_values($allPostsData); // 重新索引数组
    } elseif ($filter === 'type4') {
        $allPostsData = array_filter($allPostsData, function($post) {
            return strpos($post['content'] ?? '', '#故事集#') !== false;
        });
        $allPostsData = array_values($allPostsData); // 重新索引数组
    } elseif ($filter === 'type5') {
        $allPostsData = array_filter($allPostsData, function($post) {
            return strpos($post['content'] ?? '', '#追星#') !== false;
        });
        $allPostsData = array_values($allPostsData); // 重新索引数组
    }
    
    // 分页处理
    $totalPosts = count($allPostsData);
    $totalPages = ceil($totalPosts / $perPage);
    $startIndex = ($page - 1) * $perPage;
    
    // 确保起始索引有效
    if ($startIndex >= $totalPosts) {
        return [
            'data' => [],
            'total' => $totalPosts,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'has_more' => false
        ];
    }
    
    $currentPageData = array_slice($allPostsData, $startIndex, $perPage);
    
    return [
        'data' => $currentPageData,
        'total' => $totalPosts,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages,
        'has_more' => $page < $totalPages
    ];
}

// 后端处理逻辑：登录验证
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $id = trim($_POST['id']);
    $password = trim($_POST['password']);
    
    // 使用文件锁避免并发读取
    $rootData = json_decode(file_get_contents($rootJsonPath), true);
    $userFound = false;
    $userInfo = [];
    
    foreach ($rootData as $user) {
        if (isset($user['id']) && $user['id'] === $id && isset($user['password']) && $user['password'] === $password) {
            $userFound = true;
            $userInfo = [
                'id' => $user['id'],
                'pname' => $user['pname'],
                'gender' => $user['gender'],
                'portrait' => $user['portrait']
            ];
            break;
        }
    }
    
    if ($userFound) {
        //黑名单检测
    if (isset($bannedUsers[$id])) {
        echo json_encode([
            'status' => 'banned', 
            'msg'    => $bannedUsers[$id]['msg']
        ]);
        exit;
    }
     $loginLogFile = 'login-data.json';
    $loginData = [];
    if (file_exists($loginLogFile)) {
        $content = file_get_contents($loginLogFile);
        $loginData = json_decode($content, true) ?? [];
    }
    // 追加新记录
    $loginData[] = [
        'time'   => date('Y-m-d H:i:s'),
        'pname'  => $userInfo['pname'],
        'id'     => $userInfo['id'],   // QQ号
    ];
    // 写入文件（加锁防止并发）
    file_put_contents($loginLogFile, json_encode($loginData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        echo json_encode(['status' => 'success', 'data' => $userInfo]);
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'QQ号或密码错误']);
    }
    exit;
}

// 检查当前用户是否在黑名单中
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_banned') {
    $id = trim($_POST['id'] ?? '');
    if (isset($bannedUsers[$id])) {
        echo json_encode(['status' => 'banned', 'msg' => $bannedUsers[$id]['msg']]);
    } else {
        echo json_encode(['status' => 'success', 'msg' => '']);
    }
    exit;
}

//获取帖子列表（分页）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_posts') {
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $perPage = isset($_POST['per_page']) ? intval($_POST['per_page']) : 10;
    $filter = isset($_POST['filter']) ? $_POST['filter'] : 'latest';
    
    $result = getPostsByPage($page, $perPage, $filter);
    
    // 处理HTML输出
    ob_start();
    
    if (!empty($result['data'])):
        $today = date('Y-m-d');
        foreach ($result['data'] as $post):
            $device = isset($post['device']) ? $post['device'] : '';
            // 处理关键词高亮
            $content = htmlspecialchars($post['content']);
            // 匹配井号包围的关键词
            $content = preg_replace('/#([^#]+)#/', '<span class="keyword" data-keyword="$1">#$1#</span>', $content);
            
            // 判断内容是否需要折叠
            $needsCollapse = mb_strlen($post['content'], 'UTF-8') > 100;
            $contentClass = $needsCollapse ? 'collapsed' : '';
            
            // 获取评论
            $comments = isset($post['comments']) ? $post['comments'] : [];
            $commentCount = count($comments);
            $showExpand = $commentCount > 3;
            $commentsToShow = $showExpand ? array_slice($comments, 0, 3) : $comments;
            
            // 判断帖子标记
            $isToday = (date('Y-m-d', strtotime($post['pdate'])) == $today);
            $hasSpark = ($post['plikes'] >= 50);
            //上面这行是判断是否有火花标记，50赞以上就有
            //注意！！！这里更改要同步更改js代码中postDetailContent的部分
            $isAuth = (strpos($post['pname'], '元宝') !== false || strpos($post['pname'], '豆包') !== false);
    ?>
    <div class="post-card" data-pid="<?php echo $post['pid']; ?>" data-likes="<?php echo $post['plikes']; ?>" data-content="<?php echo htmlspecialchars($post['content']); ?>" data-pname="<?php echo htmlspecialchars($post['pname']); ?>" data-portrait="<?php echo $post['portrait']; ?>" data-pdate="<?php echo $post['pdate']; ?>" data-device="<?php echo $device; ?>">
        <div class="post-header">
            <img src="<?php echo $post['portrait'] ?: 'default-avatar.png'; ?>" alt="用户头像" class="post-avatar" draggable="false" onerror="this.src='default-avatar.jpg'">
            <div class="post-user-info">
                <div class="post-username">
                    <span class="shimmer-text"><?php echo $post['pname']; ?></span>
                    <?php if ($isAuth): ?>
                        <span class="post-badge badge-auth">
                            <i class="fas fa-check-circle"></i> AI
                        </span>
                    <?php endif; ?>
                </div>
                <div class="post-date">
                    <?php echo $post['pdate']; ?>
                    <?php if (!empty($device)): ?>
                        <span class="post-device"><?php echo $device; ?></span>
                    <?php endif; ?>
                </div>
                <div class="post-badges">
                    <?php if ($hasSpark): ?>
                        <span class="post-badge badge-spark">
                            <i class="fas fa-fire"></i>
                        </span>
                    <?php endif; ?>
                    <?php if ($isToday): ?>
                        <span class="post-badge badge-today">
                            <i class="fas fa-calendar-day"></i> 今日发布
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <!-- 插画裁剪层（限定在头部区域内） -->
<div class="header-clip">
    <div class="corner-art" aria-hidden="true">
        <img src="./doge.png" alt="武冈" loading="lazy" decoding="async" onerror="this.style.opacity='0.5'; this.style.background='linear-gradient(135deg, #8a6d9b, #4a6b8a)';this.src='a.svg'">
    </div>
    </div>
        </div>
        <div class="post-content <?php echo $contentClass; ?>" id="postContent_<?php echo $post['pid']; ?>">
            <?php echo nl2br($content); ?>
            <?php if ($needsCollapse): ?>
                <div class="fade-out"></div>
            <?php endif; ?>
        </div>
        <?php if ($needsCollapse): ?>
            <button class="expand-content-btn" data-pid="<?php echo $post['pid']; ?>">展开全部</button>
        <?php endif; ?>
        
        <!-- 帖子图片展示区域 -->
        <?php if (!empty($post['images'])): 
            $imageCount = count($post['images']);
            $imageClass = $imageCount === 1 ? 'single' : 'multiple';
        ?>
            <div class="post-images-container">
                <?php foreach ($post['images'] as $index => $image): ?>
                    <div class="post-image-item <?php echo $imageClass; ?>" data-image-src="<?php echo $image; ?>" data-index="<?php echo $index; ?>">
                        <img src="<?php echo $image; ?>" alt="帖子" class="post-image" loading="lazy" onerror="this.src='a.svg'" draggable="false">
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        
        <div class="post-actions">
            <button class="action-btn post-like-btn" data-type="post" data-id="<?php echo $post['pid']; ?>">
                <i class="far fa-heart action-icon"></i>
                <span class="like-count"><?php echo $post['plikes']; ?></span> 
            </button>
            <button class="action-btn view-comments-btn" data-pid="<?php echo $post['pid']; ?>" data-count="<?php echo $commentCount; ?>">
            <i class="far fa-comment action-icon"></i>
            <span class="comment-count"><?php echo $commentCount; ?></span>
            </button>
 <!-- 更多按钮（包含分享和举报） -->
    <div class="more-actions-wrapper">
        <button class="action-btn more-toggle-btn" data-pid="<?php echo $post['pid']; ?>">
            <i class="fas fa-ellipsis-h action-icon"></i>
        </button>
        <div class="more-dropdown">
            <button class="action-btn post-share-btn" data-pid="<?php echo $post['pid']; ?>">
                <i class="fas fa-share-alt action-icon"></i> 分享
            </button>
            <button class="action-btn post-report-btn" 
                    onclick="
                        let selfName = document.getElementById('panelPname').innerText.trim();
                        let targetName = this.closest('.post-card').querySelector('.post-username').innerText.trim();
                        let url = './service/jb/report_submit.php?self=' + encodeURIComponent(selfName) + '&target=' + encodeURIComponent(targetName);
                        location.href = url;
                    ">
                <i class="fas fa-exclamation-triangle action-icon"></i> 举报
            </button>
            <?php if (isset($userInfo) && $userInfo['pname'] === $post['pname']): ?>
            <!-- 仅当当前登录用户是作者时才显示删除按钮 -->
            <button class="action-btn post-delete-btn" data-pid="<?php echo $post['pid']; ?>">
                <i class="fas fa-trash-alt action-icon"></i> 删除
            </button>
            <?php endif; ?>
        </div>
    </div>
        </div>
        <div class="post-detail-hint">
    <i class="fas fa-info-circle"></i> 可双击动态和图片
</div>
        
    </div>
    <?php 
        endforeach;
    else:
        if ($page === 1):
    ?>
        <div style="text-align: center; padding: 20px; color: #999999;">暂无帖子，快来发布第一条动态吧~</div>
    <?php
        endif;
    endif;
    
    $html = ob_get_clean();
    
    $result['html'] = $html;
    echo json_encode($result);
    exit;
}

// 文件上传验证函数（统一返回结构）
function validateUploadedFiles($files) {
    $errors = [];
    $totalSize = 0;
    $fileCount = 0;
    
    $maxFiles = 4;
    $maxSingleSize = 5 * 1024 * 1024;
    $maxTotalSize = 20 * 1024 * 1024;
    
    // 允许的文件类型
    $allowedMimeTypes = [
        'image/jpeg',
        'image/jpg', 
        'image/png',
        'image/gif',
        'image/webp',
        'image/bmp'
    ];
    
    // 如果没有上传文件，直接返回成功
    if (empty($files['name'][0])) {
        return ['success' => true, 'msg' => '', 'fileCount' => 0];
    }
    
    // 检查文件数量
    $fileCount = count(array_filter($files['name']));
    if ($fileCount > $maxFiles) {
        return ['success' => false, 'msg' => "最多只能上传{$maxFiles}张图片", 'fileCount' => $fileCount];
    }
    
    for ($i = 0; $i < $fileCount; $i++) {
        $fileName = $files['name'][$i];
        $fileSize = $files['size'][$i];
        $fileTmp = $files['tmp_name'][$i];
        $fileError = $files['error'][$i];
        
        // 检查上传错误
        if ($fileError !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE   => "图片 '{$fileName}' 超过服务器限制大小",
                UPLOAD_ERR_FORM_SIZE  => "图片 '{$fileName}' 超过表单限制大小",
                UPLOAD_ERR_PARTIAL    => "图片 '{$fileName}' 只有部分被上传",
                UPLOAD_ERR_NO_FILE    => "没有选择图片文件",
                UPLOAD_ERR_NO_TMP_DIR => "服务器临时文件夹不存在",
                UPLOAD_ERR_CANT_WRITE => "无法写入服务器磁盘",
                UPLOAD_ERR_EXTENSION  => "图片上传被PHP扩展阻止"
            ];
            
            $errorMsg = isset($errorMessages[$fileError]) 
                ? $errorMessages[$fileError] 
                : "图片 '{$fileName}' 上传失败 (错误代码: {$fileError})";
            
            return ['success' => false, 'msg' => $errorMsg, 'fileCount' => $fileCount];
        }
        
        // 检查文件大小
        if ($fileSize > $maxSingleSize) {
            return ['success' => false, 'msg' => "图片 '{$fileName}' 过大！", 'fileCount' => $fileCount];
        }
        
        // 验证文件是否真的是图片
        if (!is_uploaded_file($fileTmp)) {
            return ['success' => false, 'msg' => "图片 '{$fileName}' 上传验证失败", 'fileCount' => $fileCount];
        }
        
        // 获取MIME类型
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fileTmp);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedMimeTypes)) {
            return ['success' => false, 'msg' => "图片 '{$fileName}' 格式不支持。只支持 JPG, PNG, GIF, WEBP, BMP 格式", 'fileCount' => $fileCount];
        }
        
        // 检查图片是否有效
        $imageInfo = @getimagesize($fileTmp);
        if ($imageInfo === false) {
            return ['success' => false, 'msg' => "图片 '{$fileName}' 不是有效的图片文件", 'fileCount' => $fileCount];
        }
        
        $totalSize += $fileSize;
    }
    
    // 检查总文件大小
    if ($totalSize > $maxTotalSize) {
        return ['success' => false, 'msg' => "请减少图片数量或压缩图片", 'fileCount' => $fileCount];
    }
    
    return ['success' => true, 'msg' => '', 'fileCount' => $fileCount];
}

// 后端处理逻辑：发布帖子（包含图片上传）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'publish_post') {
    $content = trim($_POST['content']);
    $pname = trim($_POST['pname']);
    $portrait = trim($_POST['portrait']);
    $device = isset($_POST['device']) && $_POST['device'] === 'show' ? $_POST['device_name'] : '';
    
    if (empty($content)) {
        echo json_encode(['status' => 'error', 'msg' => '帖子内容不能为空!']);
        exit;
    }
    
    // 验证上传的文件
    $fileValidation = validateUploadedFiles($_FILES['post_images'] ?? []);
    if (!$fileValidation['success']) {
        echo json_encode(['status' => 'error', 'msg' => $fileValidation['msg']]);
        exit;
    }
    
    // 读取现有数据
    $postsData = json_decode(file_get_contents($postsJsonPath), true);
    
    // 生成6位pid
    $maxPid = 0;
    foreach ($postsData as $post) {
        if (isset($post['pid']) && (int)$post['pid'] > $maxPid) {
            $maxPid = (int)$post['pid'];
        }
    }
    $newPid = str_pad($maxPid + 1, 6, '0', STR_PAD_LEFT);
    $pdate = date('Y-m-d H:i:s');
    
    // 处理图片上传
    $uploadedImages = [];
    if (isset($_FILES['post_images']) && !empty($_FILES['post_images']['name'][0])) {
        $maxFiles = 4;
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
        
        for ($i = 0; $i < min($maxFiles, count($_FILES['post_images']['name'])); $i++) {
            if ($_FILES['post_images']['error'][$i] === UPLOAD_ERR_OK) {
                // 验证文件类型
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $fileType = finfo_file($finfo, $_FILES['post_images']['tmp_name'][$i]);
                finfo_close($finfo);
                
                if (!in_array($fileType, $allowedTypes)) {
                    continue;
                }
                
                // 生成唯一文件名
                $extension = strtolower(pathinfo($_FILES['post_images']['name'][$i], PATHINFO_EXTENSION));
                $fileName = 'post_' . $newPid . '_' . time() . '_' . uniqid() . '.' . $extension;
                $filePath = $imgDir . '/' . $fileName;
                
                // 移动文件到img文件夹
                if (move_uploaded_file($_FILES['post_images']['tmp_name'][$i], $filePath)) {
                    // 后台自动压缩图片（超过1MB）
                    compressImage($filePath);
                    $uploadedImages[] = $filePath;
                }
            }
        }
    }
    
    // 新帖子数据
    $newPost = [
        'pname' => $pname,
        'portrait' => $portrait,
        'content' => $content,
        'pdate' => $pdate,
        'pid' => $newPid,
        'plikes' => 0,
        'images' => $uploadedImages,
        'comments' => [],
        'device' => $device
    ];
    
    // 添加到帖子列表头部
    array_unshift($postsData, $newPost);
    
    // 保存数据
    $result = file_put_contents($postsJsonPath, json_encode($postsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // 清除缓存
    if (file_exists($postsCachePath)) {
        unlink($postsCachePath);
    }
    
    if ($result) {
        echo json_encode(['status' => 'success', 'msg' => '发布成功', 'pid' => $newPid]);
    } else {
        // 如果保存失败，删除已上传的图片
        foreach ($uploadedImages as $imagePath) {
            if (file_exists($imagePath)) {
                @unlink($imagePath);
            }
        }
        echo json_encode(['status' => 'error', 'msg' => '发布失败，请稍后重试']);
    }
    exit;
}

// 后端处理逻辑：发布评论（包含图片上传）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'publish_comment') {
    $comContent = trim($_POST['com_content']);
    $pid = trim($_POST['pid']);
    $pname = trim($_POST['pname']);
    $portrait = trim($_POST['portrait']);
    $device = isset($_POST['device']) && $_POST['device'] === 'show' ? $_POST['device_name'] : '';
    
    if (empty($comContent) || empty($pid)) {
        echo json_encode(['status' => 'error', 'msg' => '评论内容不能为空']);
        exit;
    }
    
    // 验证上传的文件
    $fileValidation = validateUploadedFiles($_FILES['comment_images'] ?? []);
    if (!$fileValidation['success']) {
        echo json_encode(['status' => 'error', 'msg' => $fileValidation['msg']]);
        exit;
    }
    
    $postsData = json_decode(file_get_contents($postsJsonPath), true);
    $postIndex = -1;
    $maxCid = 0;
    
    // 找到对应帖子，并获取全局最大com-cid
    foreach ($postsData as $index => $post) {
        if (isset($post['pid']) && $post['pid'] === $pid) {
            $postIndex = $index;
        }
        if (isset($post['comments']) && is_array($post['comments'])) {
            foreach ($post['comments'] as $comment) {
                if (isset($comment['com_cid']) && (int)$comment['com_cid'] > $maxCid) {
                    $maxCid = (int)$comment['com_cid'];
                }
            }
        }
    }
    
    if ($postIndex === -1) {
        echo json_encode(['status' => 'error', 'msg' => '未找到对应帖子']);
        exit;
    }
    
    // 生成7位com_cid
    $newCid = str_pad($maxCid + 1, 7, '0', STR_PAD_LEFT);
    $comDate = date('Y-m-d H:i:s');
    
    // 处理评论图片上传
    $uploadedCommentImages = [];
    if (isset($_FILES['comment_images']) && !empty($_FILES['comment_images']['name'][0])) {
        $maxFiles = 4;
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
        
        for ($i = 0; $i < min($maxFiles, count($_FILES['comment_images']['name'])); $i++) {
            if ($_FILES['comment_images']['error'][$i] === UPLOAD_ERR_OK) {
                // 验证文件类型
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $fileType = finfo_file($finfo, $_FILES['comment_images']['tmp_name'][$i]);
                finfo_close($finfo);
                
                if (!in_array($fileType, $allowedTypes)) {
                    continue;
                }
                
                // 生成唯一文件名
                $extension = strtolower(pathinfo($_FILES['comment_images']['name'][$i], PATHINFO_EXTENSION));
                $fileName = 'comment_' . $pid . '_' . $newCid . '_' . time() . '_' . uniqid() . '.' . $extension;
                $filePath = $imgDir . '/' . $fileName;
                
                // 移动文件到img文件夹
                if (move_uploaded_file($_FILES['comment_images']['tmp_name'][$i], $filePath)) {
                    // 后台自动压缩图片（超过1MB）
                    compressImage($filePath);
                    $uploadedCommentImages[] = $filePath;
                }
            }
        }
    }
    
    // 新评论数据
    $newComment = [
        'com_pname' => $pname,
        'com_portrait' => $portrait,
        'com_content' => $comContent,
        'com_date' => $comDate,
        'com_cid' => $newCid,
        'clikes' => 0,
        'com_images' => $uploadedCommentImages,
        'com_device' => $device
    ];
    
    // 添加到评论列表头部
    if (!isset($postsData[$postIndex]['comments']) || !is_array($postsData[$postIndex]['comments'])) {
        $postsData[$postIndex]['comments'] = [];
    }
    array_unshift($postsData[$postIndex]['comments'], $newComment);
    
    // 保存数据
    $result = file_put_contents($postsJsonPath, json_encode($postsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // 清除缓存
    if (file_exists($postsCachePath)) {
        unlink($postsCachePath);
    }
    
    if ($result) {
        echo json_encode(['status' => 'success', 'msg' => '评论成功']);
    } else {
        // 如果保存失败，删除已上传的图片
        foreach ($uploadedCommentImages as $imagePath) {
            if (file_exists($imagePath)) {
                @unlink($imagePath);
            }
        }
        echo json_encode(['status' => 'error', 'msg' => '评论失败，请稍后重试']);
    }
    exit;
}

// 后端处理逻辑：点赞
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'like') {
    $type = trim($_POST['type']);
    $id = trim($_POST['id']);
    $action = isset($_POST['like_action']) ? $_POST['like_action'] : 'add'; // 'add' 或 'remove'
    
    if (empty($type) || empty($id)) {
        echo json_encode(['status' => 'error', 'msg' => '点赞参数错误']);
        exit;
    }
    
    $postsData = json_decode(file_get_contents($postsJsonPath), true);
    $updated = false;
    
    if ($type === 'post') {
        // 帖子点赞
        foreach ($postsData as &$post) {
            if (isset($post['pid']) && $post['pid'] === $id) {
                if ($action === 'add') {
                    $post['plikes'] += 1;
                } else {
                    $post['plikes'] = max(0, $post['plikes'] - 1);
                }
                $updated = true;
                break;
            }
        }
    } elseif ($type === 'comment') {
        // 评论点赞
        foreach ($postsData as &$post) {
            if (isset($post['comments']) && is_array($post['comments'])) {
                foreach ($post['comments'] as &$comment) {
                    if (isset($comment['com_cid']) && $comment['com_cid'] === $id) {
                        if ($action === 'add') {
                            $comment['clikes'] += 1;
                        } else {
                            $comment['clikes'] = max(0, $comment['clikes'] - 1);
                        }
                        $updated = true;
                        break 2;
                    }
                }
            }
        }
    }
    
    if (!$updated) {
        echo json_encode(['status' => 'error', 'msg' => '未找到对应内容']);
        exit;
    }
    
    $result = file_put_contents($postsJsonPath, json_encode($postsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // 清除缓存
    if (file_exists($postsCachePath)) {
        unlink($postsCachePath);
    }
    
    echo json_encode(['status' => 'success', 'msg' => $action === 'add' ? '点赞成功' : '取消点赞成功']);
    exit;
}

// 后端处理逻辑：获取帖子的所有评论
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_post_comments') {
    $pid = trim($_POST['pid']);
    
    if (empty($pid)) {
        echo json_encode(['status' => 'error', 'msg' => '帖子ID不能为空']);
        exit;
    }
    
    $postsData = json_decode(file_get_contents($postsJsonPath), true);
    $postFound = false;
    $comments = [];
    
    foreach ($postsData as $post) {
        if (isset($post['pid']) && $post['pid'] === $pid) {
            $postFound = true;
            $comments = isset($post['comments']) ? $post['comments'] : [];
            break;
        }
    }
    
    if ($postFound) {
        echo json_encode(['status' => 'success', 'comments' => $comments]);
    } else {
        echo json_encode(['status' => 'error', 'msg' => '未找到对应帖子']);
    }
    exit;
}

// 新增：搜索建议接口（修复多字节截取问题）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_search_suggestions') {
    $keyword = isset($_POST['keyword']) ? trim($_POST['keyword']) : '';
    
    if (empty($keyword)) {
        echo json_encode(['status' => 'success', 'suggestions' => []]);
        exit;
    }
    
    $postsData = json_decode(file_get_contents($postsJsonPath), true);
    $suggestions = [];
    $keywordLower = strtolower($keyword);
    
    // 从帖子内容和评论中提取关键词建议
    foreach ($postsData as $post) {
        // 检查帖子内容
        if (stripos($post['content'], $keyword) !== false) {
            // 提取包含关键词的片段（使用mb_substr）
            $content = $post['content'];
            $pos = mb_stripos($content, $keyword, 0, 'UTF-8');
            if ($pos !== false) {
                $start = max(0, $pos - 20);
                $length = 60;
                $snippet = mb_substr($content, $start, $length, 'UTF-8');
                if ($start > 0) $snippet = '...' . $snippet;
                if (mb_strlen($content, 'UTF-8') > $start + $length) $snippet .= '...';
                
                $suggestions[] = [
                    'type' => 'content',
                    'text' => $snippet,
                    'pid' => $post['pid']
                ];
            }
        }
        
        // 提取井号标签
        preg_match_all('/#([^#]+)#/u', $post['content'], $matches);
        foreach ($matches[1] as $tag) {
            if (stripos($tag, $keyword) !== false && !in_array('#' . $tag . '#', array_column($suggestions, 'text'))) {
                $suggestions[] = [
                    'type' => 'tag',
                    'text' => '#' . $tag . '#',
                    'pid' => $post['pid']
                ];
            }
        }
        
        // 检查评论内容
        if (isset($post['comments']) && is_array($post['comments'])) {
            foreach ($post['comments'] as $comment) {
                if (stripos($comment['com_content'], $keyword) !== false) {
                    $comContent = $comment['com_content'];
                    $pos = mb_stripos($comContent, $keyword, 0, 'UTF-8');
                    if ($pos !== false) {
                        $start = max(0, $pos - 15);
                        $length = 50;
                        $snippet = mb_substr($comContent, $start, $length, 'UTF-8');
                        if ($start > 0) $snippet = '...' . $snippet;
                        if (mb_strlen($comContent, 'UTF-8') > $start + $length) $snippet .= '...';
                        
                        $suggestions[] = [
                            'type' => 'comment',
                            'text' => '评论: ' . $snippet,
                            'pid' => $post['pid']
                        ];
                    }
                }
            }
        }
        
        // 限制建议数量
        if (count($suggestions) >= 8) break;
    }
    
    echo json_encode(['status' => 'success', 'suggestions' => $suggestions]);
    exit;
}

// 获取上传限制信息
$uploadLimits = getUploadLimits();

// 获取今天日期用于标记
$today = date('Y-m-d');

// 资源版本号，用于静态资源缓存刷新（若外部未设置则使用时间戳）
if (!isset($staticVer)) {
    $staticVer = time();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <!--user-scalable=no：锁住页面，不让用户缩放;viewport-fit=cover：页面全屏渲染，覆盖手机刘海 / 状态栏区域。-->
    <meta name="description" content="武冈学生个人圈">
    <meta name="keywords" content="个人博客、武冈、规范交流">
    <meta name="author" content="段游孤独一生">
    <meta property="og:url" content="http://an.kijk.top/">
    <meta property="og:title" content="武冈 | 学生个人圈">
    <meta property="og:description" content="武冈学生个人圈，分享和交流。">
    <meta property="og:image" content="http://an.kijk.top/a.svg">
    <meta property="og:image:alt" content="武冈 | 网站封面图">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="武冈">
    <meta name="wechat:share:image" content="http://an.kijk.top/a.svg">
    <link rel="shortcut icon" href="/a.svg">
    <title>武冈</title> 
    <?php if (isset($_GET['pid'])): ?>
<script>window.targetPid = '<?php echo htmlspecialchars($_GET['pid']); ?>';</script>
<?php endif; ?>
    <script src="/repository/main.js?v=<?php echo $staticVer; ?>"></script>
    <link rel="stylesheet" href="/repository/awesome/css/all.min.css" onerror="this.href='https://cdn.staticfile.org/font-awesome/6.4.0/css/all.min.css'">
    <link rel="stylesheet" href="/repository/main.css?v=<?php echo $staticVer; ?>">
    <!--?v=尖括号?php echo $staticVer; ?尖括号是用来保证JS文件和CSS文件的版本处于最新，但是要在上文定义它-->
    <style>
        /* 搜索框清除按钮样式 */
        .search-area {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .search-clear {
            display: none;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #999;
            font-size: 20px;
            width: 20px;
            height: 20px;
            line-height: 20px;
            text-align: center;
            border-radius: 50%;
            background: rgba(0,0,0,0.1);
        }
        .search-clear:hover {
            background: rgba(0,0,0,0.2);
            color: #333;
        }
        /*========================================*/
      /* 全局提示浮层 */
.post-tip-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
  z-index: 9998;
  display: none;
}
.post-tip-overlay.show {
  display: block;
}

/* 液态玻璃提示框 */
.liquid-glass-tip {
  position: absolute;
  /* 电脑端：右下角加号按钮上方 */
  right: 30px;
  bottom: 80px;
  
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid rgba(255, 255, 255, 0.5);
  border-radius: 16px;
  padding: 12px 16px;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
  pointer-events: auto;
  width: auto;
  max-width: 220px;
  text-align: center;
  animation: float 3s ease-in-out infinite;
}

/* 箭头：指向右下角加号 */
.tip-arrow {
  position: absolute;
  right: 22px;
  bottom: -10px;
  width: 0;
  height: 0;
  border-left: 10px solid transparent;
  border-right: 10px solid transparent;
  border-top: 10px solid rgba(255, 255, 255, 0.85);
}

/* 文字样式 */
.liquid-glass-tip p {
  margin: 0;
  font-size: 15px;
  color: #333;
  line-height: 1.4;
}

/* 漂浮动画 */
@keyframes float {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-6px); }
}

/* 手机端适配 */
@media (max-width: 768px) {
  .liquid-glass-tip {
    right: 20px;
    bottom: 70px;
    padding: 10px 14px;
    max-width: 180px;
  }
  .tip-arrow {
    right: 18px;
  }
}
    </style>
</head>
<body>
    <!-- 通知组块 -->
    <div class="ios-glass-notice" id="dailyNotice">
        <div class="notice-content" id="noticeContent">
            <!-- 从JSON加载内容 -->
        </div>
        <div class="notice-close-bar" id="closeBar"></div>
    </div>
    
    <!-- 全局提示框 -->
    <div class="global-tip" id="globalTip" style="z-index: 308;">
    </div>
    
    <!-- 图片查看器模态框 -->
    <div class="modal-mask" id="imageViewerModal" style="z-index: 306;">
        <div class="modal-content image-viewer-content">
            <button class="modal-close-btn" id="imageViewerClose"></button>
            <img class="image-viewer-img" id="viewerImage" src="" alt="图片" draggable="false" onerror="this.src='a.svg'">
            <div class="image-viewer-controls">
                <div style="display: flex; gap: 15px; align-items: center;">
                    <button class="image-viewer-btn" id="prevImageBtn">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <div class="image-viewer-info" id="imageInfo">1 / 1</div>
                    <button class="image-viewer-btn" id="nextImageBtn">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <div class="image-viewer-hint" id="imageViewerHint">
                    小提示：双击图片关闭 | 左右滑动切换图片
                </div>
            </div>
        </div>
    </div>

    <!-- 帖子详情模态框 -->
    <div class="modal-mask" id="postDetailModal" style="z-index: 305;">
        <div class="modal-content post-detail-content">
            <div class="post-detail-header">
                <button class="post-detail-back" id="postDetailBack">
                    返回
                </button>
                <div class="post-detail-title">阅读模式</div>
                <div class="post-detail-user">
                    <img src="default-avatar.jpg" alt="用户头像" class="post-detail-avatar" id="postDetailAvatar" style="display: none;" onerror="this.src='a.svg'" draggable="false">
                </div>
            </div>
            <div class="post-detail-body">
                <div class="post-detail-scrollable" id="postDetailContent">
                    <!-- 帖子详情内容将通过JavaScript动态填充 -->
                </div>
            </div>
        </div>
    </div>

    <!-- 登录模态框 -->
    <div class="modal-mask" id="loginModal" style="z-index: 307;">
        <div class="modal-content">
            <button class="modal-close-btn" id="loginModalClose"></button>
            <h3 class="modal-title">学生圈-登录</h3>
            
            <!-- ID重要性提醒 -->
            <div class="id-reminder">
                <i class="fas fa-exclamation-circle"></i>
                <strong>注意：</strong>显示不安全是因为我们没有经过安全认证
            </div>
            
            <form id="loginForm">
                <div class="form-group">
                    <label for="loginId">QQ号</label>
                    <div class="input-with-tip">
                        <input type="text" id="loginId" name="id" required placeholder="请输入QQ号">
                        <div class="id-format-tip">QQ</div>
                    </div>
                    <!-- ID记忆提示 -->
                    <div class="id-memory-tip" id="idMemoryTip">
                        <i class="far fa-lightbulb" style="color: #f39c12;"></i>
                        <span>注意保密，不要泄露给他人</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="loginPwd">密码</label>
                    <input type="password" id="loginPwd" name="password" required placeholder="请输入密码">
                </div>
                
                <button type="submit" class="btn" id="loginSubmitBtn">登录</button>
                
                <div class="register-btn-wrapper">
                    <a href="register2.php" class="btn register-btn" id="registerBtn" target="_blank">
                        还没有账号？去注册
                    </a>
                </div>
                
                <!-- 登录成功后显示QQ号 -->
                <div class="login-success-id" id="loginSuccessId">
                    <div style="font-size: 14px; margin-bottom: 5px; opacity: 0.9;">
                        <i class="fas fa-check-circle"></i> 登录成功！
                    </div>
                    <div style="font-size: 16px; font-weight: bold;">
                        您的QQ号：<span id="displayUserId" style="
                            background: rgba(255,255,255,0.2);
                            padding: 3px 8px;
                            border-radius: 4px;
                            margin: 0 5px;
                        "></span>
                    </div>
                    <div style="font-size: 12px; margin-top: 5px; opacity: 0.8;">
                        <i class="fas fa-copy" id="copyIdBtn" style="cursor: pointer; margin-right: 5px;"></i>
                        轻触复制QQ号
                    </div>
                </div>
                
                <div class="modal-tip" id="loginTip"></div>
            </form>
        </div>
    </div>

    <!-- 发帖模态框 -->
    <div class="modal-mask bottom-modal" id="postModal" style="z-index: 306;">
        <div class="modal-content">
            <button class="modal-close-btn" id="postModalClose"></button>
            <h3 class="modal-title">发布动态</h3>
            
            <div class="modal-scrollable">  
                <!-- 压缩图片提示（前端不再显示，由后台自动处理，但保留此元素以备将来使用） -->
                <div class="compress-tip" id="compressTip" style="display: none;">
                    <i class="fas fa-compress-alt"></i>
                    <span>检测到大图片，系统将自动压缩...</span>
                </div>
                
                <form id="postForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="postContent">武冈</label>
                        <textarea id="postContent" name="content" required placeholder="请输入你想分享的内容..."></textarea>
                    </div>

                    <!-- 发帖建议区域 -->
                    <div class="suggestions-area">
                        <div class="suggestions-title">tips:可自己添加标签，再次轻触已选标签可撤回相应已输入标签</div>
                        <div class="suggestion-tags" id="postSuggestionTags">
                            <div class="suggestion-tag" data-tag="#故事集#">#故事集#</div>
                            <div class="suggestion-tag" data-tag="#追星#">#追星#</div>
                            <div class="suggestion-tag" data-tag="#交友#">#交友#</div>
                            <div class="suggestion-tag" data-tag="#游戏#">#游戏#</div>
                            <div class="suggestion-tag" data-tag="#emo#">#emo#</div>
                            <div class="suggestion-tag" data-tag="#分享#">#分享#</div>
                        </div>
                    </div>
                    
                    <!-- 帖子图片上传区域 -->
                    <div class="form-group image-upload-area">
                        <label class="image-upload-label">上传图片</label>
                        <input type="file" id="postImages" name="post_images[]" accept="image/*" multiple style="display: none;">
                        <div class="upload-placeholder" id="postUploadPlaceholder">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div class="image-preview-container" id="postImagePreview"></div>
                        <div class="image-count" id="postImageCount">0/4 张图片</div>
                        <div class="file-size-hint">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>注意：为了安全着想，图片太大会显示上传失败</span>
                        </div>
                    </div>
                    
                    <!-- 设备名选择区域 -->
                    <div class="device-select-area" id="postDeviceSelect">
                        <div class="device-header" id="postDeviceHeader">
                            <span>设备名：<span id="postDeviceName">获取中...</span></span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="device-options" id="postDeviceOptions">
                            <div class="device-option">
                                <input type="radio" id="postDeviceShow" name="postDevice" value="show" checked>
                                <label for="postDeviceShow">显示设备名</label>
                            </div>
                            <div class="device-option">
                                <input type="radio" id="postDeviceHide" name="postDevice" value="hide">
                                <label for="postDeviceHide">不显示设备名</label>
                            </div>
                        </div>
                        <input type="hidden" id="postDeviceNameValue" name="device_name" value="">
                    </div>

                    <!-- 上传进度条 -->
                    <div class="upload-progress" id="postUploadProgress">
                        <div class="upload-progress-bar" id="postUploadProgressBar"></div>
                    </div>
                    
                    <input type="hidden" id="postPname" name="pname">
                    <input type="hidden" id="postPortrait" name="portrait">
                    <input type="hidden" id="postDevice" name="device" value="show">
                    <button type="submit" class="btn" id="postSubmitBtn">发布</button>
                    <div class="modal-tip" id="postTip"></div>
                </form>
            </div>
        </div>
    </div>

    <!-- 评论模态框 -->
    <div class="modal-mask bottom-modal" id="commentModal" style="z-index: 306;">
        <div class="modal-content">
            <button class="modal-close-btn" id="commentModalClose"></button>
            <h3 class="modal-title">评论</h3>
            
            <div class="modal-scrollable">
                <form id="commentForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="commentContent">武冈</label>
                        <textarea id="commentContent" name="com_content" required placeholder="请输入你的评论..."></textarea>
                    </div>
                    
                    <!-- 评论图片上传区域 -->
                    <div class="form-group image-upload-area">
                        <label class="image-upload-label">上传图片</label>
                        <input type="file" id="commentImages" name="comment_images[]" accept="image/*" multiple style="display: none;">
                        <div class="upload-placeholder" id="commentUploadPlaceholder">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div class="image-preview-container" id="commentImagePreview"></div>
                        <div class="image-count" id="commentImageCount">0/4 张图片</div>
                    </div>
                    
                    <!-- 设备名选择区域 -->
                    <div class="device-select-area" id="commentDeviceSelect">
                        <div class="device-header" id="commentDeviceHeader">
                            <span>设备名：<span id="commentDeviceName">获取中...</span></span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="device-options" id="commentDeviceOptions">
                            <div class="device-option">
                                <input type="radio" id="commentDeviceShow" name="commentDevice" value="show" checked>
                                <label for="commentDeviceShow">显示设备名</label>
                            </div>
                            <div class="device-option">
                                <input type="radio" id="commentDeviceHide" name="commentDevice" value="hide">
                                <label for="commentDeviceHide">不显示设备名</label>
                            </div>
                        </div>
                        <input type="hidden" id="commentDeviceNameValue" name="device_name" value="">
                    </div>

                    <!-- 上传进度条 -->
                    <div class="upload-progress" id="commentUploadProgress">
                        <div class="upload-progress-bar" id="commentUploadProgressBar"></div>
                    </div>
                    
                    <input type="hidden" id="commentPid" name="pid">
                    <input type="hidden" id="commentPname" name="pname">
                    <input type="hidden" id="commentPortrait" name="portrait">
                    <input type="hidden" id="commentDevice" name="device" value="show">
                    <button type="submit" class="btn" id="commentSubmitBtn">发布评论</button>
                    <div class="modal-tip" id="commentTip"></div>
                </form>
            </div>
        </div>
    </div>

    <!-- 评论显示模态框 -->
<div class="modal-mask bottom-modal" id="commentshowModal" style="z-index: 305;">
    <div class="modal-content">
        <button class="modal-close-btn" id="commentshowModalClose"></button>
        <h3 class="modal-title">评论</h3>
        <!-- 伪输入框（放在最上方） -->
        <div class="fake-input-modal" data-pid="" id="modalFakeInput">
            <i class="far fa-comment"></i>
            <span>发表评论：</span>
        </div>
        <!-- 评论列表容器 -->
        <div class="modal-comments-list" id="modalCommentsList">
            <!-- 动态加载评论 -->
        </div>
    </div>
</div>

    <!-- 主内容区 -->
    <div class="container" >
        <!-- 导航 section1 -->
        <div id="section1">
            <div class="section1-top">
                <div class="logo-area">
                    <div class="logo">
                        <img src="/a.svg" alt="logo" style="width: 40px; height: 40px;">
                    </div>
                    <div class="service-name">武冈</div>
                </div>
                <div class="nav-right">
                    <button id="toggleSearchBtn" class="fold-btn">
                    <i class="fas fa-search"></i> 搜索动态
                    </button>
                    <!-- 导航栏区域 - 折叠/展开 Section3 按钮 -->
                    <button id="toggleSection3Btn" class="fold-btn">
                    <i class="fas fa-chevron-up"></i> 更多服务
                    </button>
                    <img src="default-avatar.jpg" alt="用户头像" class="user-avatar" id="userAvatar" style="display: none;" draggable="false" onerror="this.src='default-avatar.jpg'">
                    <!-- 用户信息面板 -->
                    <div class="user-info-panel" id="userInfoPanel">
                        <div class="user-info-item">昵称：<span id="panelPname" class="shimmer-text"></span></div>
                        <div class="user-info-item">性别：<span id="panelGender"></span></div>
                        <div class="user-info-item">QQ号：
                            <span id="panelId" style="
                                color: #ff6b6b;
                                font-weight: bold;
                                background: rgba(255,107,107,0.1);
                                padding: 2px 6px;
                                border-radius: 4px;
                                margin-right: 5px;
                            "></span>
                            <i class="fas fa-copy" id="copyPanelIdBtn" 
                               style="font-size: 12px; color: #4a90e2; cursor: pointer;" 
                               title="复制ID"></i>
                        </div>
                        <button class="logout-btn" id="logoutBtn">退出登录</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- section3 外接服务版块 -->
        <div id="section3Container">
        <div id="section3">
            <div class="service-button" onclick="location.href='/guide.html'">
                <div class="service-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="service-text">使用引导</div>
            </div>

            <div class="service-button" onclick="location.href='/mp.html'">
                <div class="service-icon">
                    <i class="fas fa-search"></i>
                </div>
                <div class="service-text">搜索账号</div>
            </div>

            <div class="service-button" onclick="location.href='/find.php'">
                <div class="service-icon">
                    <i class="fas fa-search"></i>
                </div>
                <div class="service-text">密码找回</div>
            </div>

            <div class="service-button" href="mqqapi://card/show_pslcard?src_type=internal&version=1&uin=3279613515&card_type=person&source=qrcode" 
                           data-fallback="#" 
                           onclick="return handleLinkClick(this)">
                <div class="service-icon">
                    <i class="fab fa-qq"></i>
                </div>
            <div class="service-text">小屋QQ</div>
            </div>

             <div class="service-button" onclick="location.href='/service/transfer/'">
                <div class="service-icon">
                    <i class="fas fa-file"></i>
                </div>
                <div class="service-text">文件传输助手</div>
            </div>

            <div class="service-button" onclick="location.href='/service/file/'">
                <div class="service-icon">
                    <i class="fas fa-file"></i>
                </div>
                <div class="service-text">公共文件存储系统</div>
            </div>

            <div class="service-button" onclick="location.href='/service/ancloud/'">
                <div class="service-icon">
                    <i class="fas fa-file"></i>
                </div>
                <div class="service-text">Ancloud</div>
            </div>

            <div class="service-button" onclick="location.href='/service/music-player/player.php'">
                <div class="service-icon">
                    <i class="fas fa-music"></i>
                </div>
                <div class="service-text">Anmusic</div>
            </div>

            <div class="service-button" onclick="window.open('https://free.xn--fiqs8s/', '_blank')">
                <div class="service-icon">
                    <i class="fas fa-link"></i>
                </div>
                <div class="service-text">Free.中国</div>
            </div>

            <div class="service-button" onclick="window.open('https://woobx.cn/', '_blank')">
                <div class="service-icon">
                    <i class="fas fa-link"></i>
                </div>
                <div class="service-text">一个木函</div>
            </div>
            
            <div class="service-button" onclick="window.open('https://www.jyshare.com/', '_blank')">
                <div class="service-icon">
                    <i class="fas fa-link"></i>
                </div>
                <div class="service-text">菜鸟工具</div>
            </div>

            <div class="service-button" onclick="window.open('http://music.alger.fun/', '_blank')">
                <div class="service-icon">
                    <i class="fas fa-music"></i>
            </div>
                <div class="service-text">在线音乐-千万曲库</div>
            </div>

            <div class="service-button" onclick="location.href='/service/jb/report_submit.php'">
                <div class="service-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="service-text">举报</div>
            </div>

            <div class="service-button" onclick="location.href='/service/problem/problem.php'">
                <div class="service-icon">
                    <i class="fas fa-question-circle"></i>
                </div>
            <div class="service-text">反馈</div>
            </div>

                        <div class="service-button" onclick="location.href='/admin.html'">
                <div class="service-icon">
                    <i class="fas fa-cog"></i>
                </div>
            <div class="service-text">开发者</div>
            </div>


        </div>
        </div>
        
        <!-- section1.5 切换栏 -->
        <div id="section1_5">
            <button class="tab-button active" id="latestTab">最新</button>
            <button class="tab-button" id="type5Tab">追星</button>
            <button class="tab-button" id="type4Tab">故事集</button>
            <button class="tab-button" id="type1Tab">交友</button>
            <button class="tab-button" id="type2Tab">emo</button>
            <button class="tab-button" id="type3Tab">游戏</button> 
        </div>

        <!-- 帖子 section4 -->
        <div id="section4">
            <div class="section4-header">
                <div class="current-tab" id="currentTab">最新</div>
                <div class="refresh-btn" id="refreshBtn">刷新</div>
            </div>
            
            <div id="searchSectionContainer" style="display: none;">
                <!-- 搜索框区域 -->
                <div class="search-area">
                <div style="position: relative; flex: 1;">
                    <input type="text" class="search-box" id="postSearch" placeholder="搜索帖子内容或关键词..." autocomplete="off">
                    <span class="search-clear" id="searchClear" style="display: none;">&times;</span>
                </div>
                <!-- 搜索建议下拉框 -->
                <div class="search-suggestions" id="searchSuggestions" style="display: none;">
                    <div class="suggestions-header">
                        <i class="fas fa-lightbulb"></i>
                        <span>搜索建议</span>
                    </div>
                    <div class="suggestions-list" id="suggestionsList">
                        <!-- 建议内容将通过JS动态填充 -->
                    </div>
                </div>
                </div>
                <!-- 推荐搜索标签区域 -->
                <div class="recommend-search-area">
                <div id="top-notice" class="top-notice">
                <div class="top-notice-text"><strong>提示：双击图片能开关图片详情·双击动态进入阅读模式（轻触关闭）</strong></div>
                </div>
                <div class="recommend-header">
                    <div class="recommend-title">
                        <i class="fas fa-fire"></i>
                        <span>推荐搜索标签</span>
                    </div>
                    <button class="recommend-toggle" id="recommendToggle">
                        <span>展开</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>
                <div class="recommend-tags-container collapsed" id="recommendTagsContainer">
                    <div class="recommend-tag" data-keyword="故事集">#故事集#</div>
                    <div class="recommend-tag" data-keyword="分享">#分享#</div>
                    <div class="recommend-tag" data-keyword="交友">#交友#</div>
                    <div class="recommend-tag" data-keyword="追星">#追星#</div>
                    <div class="recommend-tag" data-keyword="emo">#emo#</div>
                    <div class="recommend-tag" data-keyword="游戏">#游戏#</div>
                    
                </div>
                </div>
            </div>

            <!-- 帖子列表容器 -->
            <div class="posts-list" id="postsList">
                <!-- 帖子将通过JavaScript动态加载 -->
            </div>
            
            <!-- 加载更多按钮 -->
            <div class="load-more-container" id="loadMoreContainer" style="display: none;">
                <button class="load-more-btn" id="loadMoreBtn">
                    <span>加载更多</span>
                </button>
            </div>
            
            <!-- 加载中提示 -->
            <div class="loading-indicator" id="loadingIndicator" style="text-align: center; padding: 20px; display: none;">
                <div class="loading-spinner"></div>
                <div style="margin-top: 10px; color: #666;">加载中...</div>
            </div>
        </div>

        <!-- 页脚 section5 -->
        <div id="section5">
            <div class="footer-logo-area">
                <div class="footer-logo">
                    <img src="/a.svg" alt="logo">
                </div>
                <div class="footer-service-name">武冈 | 湘ICP备13005896号</div>
            </div>
        </div>
    </div>

    <!-- 底部登录提示 -->
    <div class="bottom-login-prompt" id="bottomLoginPrompt" style="z-index: 304;">
        <i class="fas fa-user"></i>
        <span>登录/注册</span>
    </div>

    <!-- 发布引导提示框 - 指向右下角加号按钮 -->
    <div id="postTipOverlay" class="post-tip-overlay">
    <div class="liquid-glass-tip">
    <div class="tip-arrow"></div>
    <p>🎉 点击这里发布你的动态吧（轻触关闭）</p>
    </div>
    </div>

    <!-- 右下角固定图标 -->
    <div class="fixed-icons" style="z-index: 304;">
        <div class="fixed-icon top-icon" id="topIcon"></div>
        <div class="fixed-icon post-btn" id="publishPostBtn"></div>
    </div>
    <script>
// 搜索框一键清除功能
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('postSearch');
    const searchClear = document.getElementById('searchClear');
    const searchSuggestions = document.getElementById('searchSuggestions');

    if (searchInput && searchClear) {
        // 输入事件：控制清除按钮显示/隐藏
        searchInput.addEventListener('input', function() {
            if (this.value.trim() !== '') {
                searchClear.style.display = 'block';
            } else {
                searchClear.style.display = 'none';
                // 可选：输入为空时隐藏搜索建议
                if (searchSuggestions) searchSuggestions.style.display = 'none';
            }
        });

        // 清除按钮点击事件
        searchClear.addEventListener('click', function() {
            searchInput.value = '';
            searchInput.focus();
            searchClear.style.display = 'none';
            // 隐藏搜索建议
            if (searchSuggestions) searchSuggestions.style.display = 'none';
            // 可选：触发输入事件以便其他监听器响应
            searchInput.dispatchEvent(new Event('input', { bubbles: true }));
        });

        // 初始状态检查（如果页面加载时输入框有内容）
        if (searchInput.value.trim() !== '') {
            searchClear.style.display = 'block';
        }
    }

});
</script>
<script>
    document.addEventListener('DOMContentLoaded', async function() {
        // 获取DOM元素
        const noticeEl = document.getElementById('dailyNotice');
        const contentEl = document.getElementById('noticeContent');
        const closeBarEl = document.getElementById('closeBar');

        // ---------- 修改点：改用一次性标志 ----------
        // 检查用户是否已经显示过通知（仅一次）
        function hasShownBefore() {
            return localStorage.getItem('noticeShownOnce') === 'true';
        }

        // 标记通知已显示（永久记录）
        function markShown() {
            localStorage.setItem('noticeShownOnce', 'true');
        }

        // 关闭通知（淡出效果）
        function closeNotice() {
            noticeEl.classList.remove('show');
            noticeEl.classList.add('hidden');
        }

        // 从JSON文件加载动态内容（保持不变）
        async function loadDynamicContent() {
            try {
                // 请确保notice-content.json与当前页面同目录
                const response = await fetch('notice-content.json?v=123xyz');
                if (!response.ok) throw new Error('内容加载失败');
                const content = await response.json();
                
                // 渲染内容
                contentEl.innerHTML = `
                    <h3>${content.title || '每日通知'}</h3>
                    <p>${content.content || '加载中'}</p>
                    ${content.subContent ? `<p style="margin-top: 8px; font-size: 14px;">${content.subContent}</p>` : ''}
                `;
            } catch (err) {
                // 加载失败兜底内容
                contentEl.innerHTML = `
                    <h3>加载失败</h3>
                    <p>请检查notice-content.json文件是否存在</p>
                `;
                console.error('加载通知内容失败：', err);
            }
        }

        // 初始化通知逻辑（修改后）
        function initNotice() {
            // 若已经显示过（永久记录），则直接隐藏，不再显示
            if (hasShownBefore()) {
                noticeEl.classList.add('hidden');
                return;
            }

            // 首次访问：标记已显示（永久）
            markShown();
            
            // 加载动态内容
            loadDynamicContent();
            
            // 绑定关闭事件
            closeBarEl.addEventListener('click', closeNotice);
            
            // 延迟触发淡入动画（100ms避免页面加载卡顿）
            setTimeout(() => {
                noticeEl.classList.add('show');
            }, 100);
        }

        // 执行初始化
        initNotice();
    });
</script>
<script>
// 全新独立脚本，无冲突
window.addEventListener('load', function(){
  const notice = document.getElementById('top-notice');
  const target = document.querySelector('.recommend-search-area');
  if(!notice || !target) return;

  target.prepend(notice);
  notice.onclick = function(){
    notice.remove();
  };
});
    </script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const toggleBtn = document.getElementById('toggleSection3Btn');
  const section3 = document.getElementById('section3Container');

  // ============== 核心修改：默认收起 ==============
  let isSection3Collapsed = true;
  section3.style.display = 'none'; // 页面加载直接隐藏

  // 点击切换
  toggleBtn.addEventListener('click', function() {
    isSection3Collapsed = !isSection3Collapsed;

    if (isSection3Collapsed) {
      section3.style.display = 'none';
      toggleBtn.innerHTML = '<i class="fas fa-chevron-down"></i> 更多服务';
    } else {
      section3.style.display = 'block'; // 如果是 flex 布局就改成 flex
      toggleBtn.innerHTML = '<i class="fas fa-chevron-up"></i> 轻触收起';
    }
  });
});
</script>
<script>
// 检查今天是否已经显示过提示
function checkShowPostTip() {
  const today = new Date().toLocaleDateString();
  const shown = localStorage.getItem('postTip_' + today);
  if (!shown) {
    document.getElementById('postTipOverlay').classList.add('show');
  }
}

// 关闭提示，并记录今天已显示
function closePostTip() {
  document.getElementById('postTipOverlay').classList.remove('show');
  const today = new Date().toLocaleDateString();
  localStorage.setItem('postTip_' + today, 'true');
}

// 初始化
document.addEventListener('DOMContentLoaded', function() {
  checkShowPostTip();

  // 点击提示框 → 关闭
  document.querySelector('.liquid-glass-tip').addEventListener('click', closePostTip);

  // 点击右下角 + 发布按钮 → 关闭
  document.getElementById('publishPostBtn').addEventListener('click', closePostTip);
});
// ========== 搜索区域折叠控制 ==========
const toggleSearchBtn = document.getElementById('toggleSearchBtn');
const searchSection = document.getElementById('searchSectionContainer');
let isSearchCollapsed = true; // 默认折叠

if (toggleSearchBtn && searchSection) {
    toggleSearchBtn.addEventListener('click', function() {
        isSearchCollapsed = !isSearchCollapsed;
        if (isSearchCollapsed) {
            searchSection.style.display = 'none';
            toggleSearchBtn.innerHTML = '<i class="fas fa-search"></i> 搜索动态';
        } else {
            searchSection.style.display = 'block';
            toggleSearchBtn.innerHTML = '<i class="fas fa-chevron-up"></i> 收起搜索';
        }
    });
}
</script>
<script>
// 重写 performSearch 以在搜索时自动展开搜索区域
(function() {
    // 等待 main.js 加载完成
    if (typeof window.performSearch === 'function') {
        const originalPerformSearch = window.performSearch;
        window.performSearch = function(keyword) {
            // 展开搜索区域（如果折叠）
            const searchSection = document.getElementById('searchSectionContainer');
            const toggleBtn = document.getElementById('toggleSearchBtn');
            if (searchSection && searchSection.style.display === 'none') {
                searchSection.style.display = 'block';
                if (toggleBtn) toggleBtn.innerHTML = '<i class="fas fa-chevron-up"></i> 收起搜索';
                // 可选：同步更新折叠状态变量（isSearchCollapsed）
                // 由于我们无法直接访问 isSearchCollapsed，但可以忽略，因为下次点击按钮时会重置。
                // 更好的做法：通过自定义事件或全局变量，但为了简单，我们只修改显示。
                // 如果用户点击按钮展开/收起，状态会被按钮逻辑管理。
                // 这里只负责展开，不会破坏按钮的切换逻辑。
            }
            // 调用原函数
            originalPerformSearch.call(this, keyword);
        };
    } else {
        // 如果 main.js 未加载或函数未定义，可以设置一个定时器重试
        console.warn('performSearch 尚未定义，稍后重试');
        setTimeout(function() {
            if (typeof window.performSearch === 'function') {
                const original = window.performSearch;
                window.performSearch = function(keyword) {
                    // 同上展开逻辑
                    const searchSection = document.getElementById('searchSectionContainer');
                    const toggleBtn = document.getElementById('toggleSearchBtn');
                    if (searchSection && searchSection.style.display === 'none') {
                        searchSection.style.display = 'block';
                        if (toggleBtn) toggleBtn.innerHTML = '<i class="fas fa-chevron-up"></i> 收起搜索';
                    }
                    original.call(this, keyword);
                };
            }
        }, 500);
    }
})();
</script>
</body>
</html>