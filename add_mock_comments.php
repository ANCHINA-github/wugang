<?php
// add_mock_comments.php
// 只允许 root.json 中 password=dca123 的用户作为评论者
// 运行一次即可生成新的 posts.json

$postsFile = 'posts.json';
$rootFile  = 'root.json';
$cacheFile = 'cache/posts_cache.json';

if (!file_exists($postsFile) || !file_exists($rootFile)) {
    exit('posts.json 或 root.json 不存在');
}

$posts = json_decode(file_get_contents($postsFile), true);
$root  = json_decode(file_get_contents($rootFile), true);

if (!is_array($posts) || !is_array($root)) {
    exit('JSON 解析失败');
}

// 1. 收集所有 password=dca123 的马甲号
$validUsers = [];
foreach ($root as $u) {
    if (isset($u['password']) && $u['password'] === 'dca123') {
        $pname = $u['pname'] ?? '';
        if ($pname !== '') {
            $validUsers[$pname] = $u['portrait'] ?? 'portrait-img/default-avatar.jpg';
        }
    }
}

// 2. 找当前最大 com_cid
$maxCid = 0;
foreach ($posts as $post) {
    if (!empty($post['comments']) && is_array($post['comments'])) {
        foreach ($post['comments'] as $c) {
            if (!empty($c['com_cid'])) {
                $n = (int)$c['com_cid'];
                if ($n > $maxCid) $maxCid = $n;
            }
        }
    }
}

// 3. 新增评论数据：键是 pid，值是评论数组
// 评论者 pname 必须来自上面 password=dca123 的名单
$newComments = [
    '000149' => [
        ['pname' => '太犹豫被人打', 'content' => '补胎还是补刀😂', 'clikes' => 2, 'device' => 'Android'],
        ['pname' => '高胜美', 'content' => '这胎补得很有仪式感', 'clikes' => 1, 'device' => 'Android'],
    ],
    '000148' => [
        ['pname' => '我的轻舟是我自己', 'content' => '哈喽，眼熟一下', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '数学退退', 'content' => '上线就看见你', 'clikes' => 0, 'device' => 'Android'],
    ],
    '000147' => [
        ['pname' => '何夕永远', 'content' => '太狠了这句', 'clikes' => 3, 'device' => 'iPhone'],
        ['pname' => '零点的韵律', 'content' => '山河四省听了都沉默', 'clikes' => 2, 'device' => 'Android'],
    ],
    '000146' => [
        ['pname' => '小懒猫', 'content' => '教师节快乐呀', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '课代表', 'content' => '老师辛苦了', 'clikes' => 2, 'device' => 'Windows PC'],
    ],
    '000145' => [
        ['pname' => '做数学的我陷入沉思', 'content' => '老黄刀法精准', 'clikes' => 4, 'device' => 'Android'],
        ['pname' => '段游', 'content' => '显卡又涨了？', 'clikes' => 1, 'device' => 'Android'],
    ],
    '000144' => [
        ['pname' => '太犹豫被人打', 'content' => '武冈理发店申请出战', 'clikes' => 2, 'device' => 'Android'],
        ['pname' => '武冈事件墙', 'content' => '这价格战可以', 'clikes' => 1, 'device' => 'Android'],
    ],
    '000143' => [
        ['pname' => '零点的韵律', 'content' => '图呢，被吞了？', 'clikes' => 0, 'device' => 'Android'],
        ['pname' => '信号弱', 'content' => '加载失败？', 'clikes' => 1, 'device' => 'Android'],
    ],
    '000142' => [
        ['pname' => '小懒猫', 'content' => '教师节快乐', 'clikes' => 2, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '快乐快乐', 'clikes' => 1, 'device' => 'iPhone'],
    ],
    '000141' => [
        ['pname' => '段游', 'content' => '新功能牛', 'clikes' => 3, 'device' => 'Android'],
        ['pname' => '信号弱', 'content' => '测试成功', 'clikes' => 1, 'device' => 'Windows PC'],
    ],
    '000140' => [
        ['pname' => '武冈事件墙', 'content' => '支持一下', 'clikes' => 0, 'device' => 'Android'],
        ['pname' => '高胜美', 'content' => '来了', 'clikes' => 1, 'device' => 'Android'],
    ],
    '000139' => [
        ['pname' => '何夕永远', 'content' => '加油', 'clikes' => 2, 'device' => 'iPhone'],
        ['pname' => '我的轻舟是我自己', 'content' => '一人开发不容易', 'clikes' => 3, 'device' => 'Android'],
        ['pname' => '小懒猫', 'content' => '支持', 'clikes' => 1, 'device' => 'Android'],
    ],
    '000138' => [
        ['pname' => '零点的韵律', 'content' => 'Reqable 还行', 'clikes' => 2, 'device' => 'Android'],
        ['pname' => '段游', 'content' => '这个工具不错', 'clikes' => 1, 'device' => 'Android'],
    ],
    '000137' => [
        ['pname' => '小懒猫', 'content' => '戒指收到了', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '好浪漫', 'clikes' => 2, 'device' => 'iPhone'],
    ],
    '000136' => [
        ['pname' => '武冈事件墙', 'content' => '致敬', 'clikes' => 3, 'device' => 'Android'],
        ['pname' => '我的轻舟是我自己', 'content' => '国士无双', 'clikes' => 2, 'device' => 'Android'],
    ],
    '000135' => [
        ['pname' => '吃我蛋挞干什么', 'content' => '磕到了', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '全娱资讯君', 'content' => '绝配', 'clikes' => 2, 'device' => ''],
    ],
    '000134' => [
        ['pname' => '数学退退', 'content' => '收藏了', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '信号弱', 'content' => '感谢分享', 'clikes' => 0, 'device' => 'Windows PC'],
    ],
    '000133' => [
        ['pname' => '课代表', 'content' => '背单词有救了', 'clikes' => 2, 'device' => 'iPhone'],
        ['pname' => '小懒猫', 'content' => '已收藏', 'clikes' => 1, 'device' => 'Android'],
    ],
    '000132' => [
        ['pname' => '何夕永远', 'content' => '抱抱', 'clikes' => 2, 'device' => 'iPhone'],
        ['pname' => '我的轻舟是我自己', 'content' => '会好的', 'clikes' => 1, 'device' => 'Android'],
    ],
    '000131' => [
        ['pname' => '武冈事件墙', 'content' => '注意安全', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '信号弱', 'content' => '台风路径关注', 'clikes' => 0, 'device' => 'Android'],
    ],
    '000130' => [
        ['pname' => '何夕永远', 'content' => '秋天快乐', 'clikes' => 2, 'device' => 'iPhone'],
        ['pname' => '小懒猫', 'content' => '贴秋膘', 'clikes' => 1, 'device' => 'Android'],
    ],
    '000128' => [
        ['pname' => '太犹豫被人打', 'content' => '空空儿可爱', 'clikes' => 2, 'device' => 'Android'],
        ['pname' => '高胜美', 'content' => '带带', 'clikes' => 1, 'device' => 'Android'],
    ],
    '000127' => [
        ['pname' => '武冈事件墙', 'content' => '好吃', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '小懒猫', 'content' => '想要', 'clikes' => 0, 'device' => 'Android'],
    ],
    '000126' => [
        ['pname' => '段游', 'content' => '想去', 'clikes' => 2, 'device' => 'Android'],
        ['pname' => '高胜美', 'content' => '约起', 'clikes' => 1, 'device' => 'Android'],
    ],
    '000125' => [
        ['pname' => '信号弱', 'content' => '关注', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '太犹豫被人打', 'content' => '可惜', 'clikes' => 0, 'device' => 'Android'],
    ],
    '000124' => [
        ['pname' => '武冈个人博客', 'content' => '历史', 'clikes' => 2, 'device' => 'Android'],
        ['pname' => '我的轻舟是我自己', 'content' => '值得去', 'clikes' => 1, 'device' => 'Android'],
    ],
    '000123' => [
        ['pname' => '小懒猫', 'content' => '想去看海', 'clikes' => 3, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '想独立', 'clikes' => 2, 'device' => 'iPhone'],
    ],
    '000122' => [
        ['pname' => '高胜美', 'content' => '确实丑', 'clikes' => 2, 'device' => 'Android'],
        ['pname' => '太犹豫被人打', 'content' => '设计问题', 'clikes' => 1, 'device' => 'Android'],
    ],
    '000121' => [
        ['pname' => '小懒猫', 'content' => '有人', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '零点的韵律', 'content' => '在', 'clikes' => 0, 'device' => 'Android'],
    ],
    '000120' => [
        ['pname' => '何夕永远', 'content' => '好看', 'clikes' => 2, 'device' => 'iPhone'],
        ['pname' => '武冈事件墙', 'content' => '想去', 'clikes' => 1, 'device' => 'Android'],
    ],
    '000119' => [
        ['pname' => '小懒猫', 'content' => '吓人', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '信号弱', 'content' => '后羿叔叔', 'clikes' => 2, 'device' => 'Android'],
    ],
    '000118' => [
        ['pname' => '焦虑', 'content' => '查了', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '课代表', 'content' => '过了', 'clikes' => 2, 'device' => 'iPhone'],
    ],
    '000116' => [
        ['pname' => '我的轻舟是我自己', 'content' => '看哭了', 'clikes' => 3, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '感动', 'clikes' => 2, 'device' => 'iPhone'],
    ],
    '000115' => [
        ['pname' => '小懒猫', 'content' => '翻到最后一页是新的开始', 'clikes' => 3, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '泪目', 'clikes' => 2, 'device' => 'iPhone'],
    ],
    '000114' => [
        ['pname' => '何夕永远', 'content' => '喜欢全部', 'clikes' => 2, 'device' => 'iPhone'],
        ['pname' => '小懒猫', 'content' => '抱抱', 'clikes' => 1, 'device' => 'Android'],
    ],
    '000113' => [
        ['pname' => '课代表', 'content' => '说得好', 'clikes' => 2, 'device' => 'iPhone'],
        ['pname' => '小懒猫', 'content' => '加油', 'clikes' => 1, 'device' => 'Android'],
    ],
    '000112' => [
        ['pname' => '何夕永远', 'content' => '勇敢一点', 'clikes' => 3, 'device' => 'iPhone'],
        ['pname' => '小懒猫', 'content' => '同意', 'clikes' => 1, 'device' => 'Android'],
    ],
    '000111' => [
        ['pname' => '课代表', 'content' => '感谢分享', 'clikes' => 2, 'device' => 'iPhone'],
        ['pname' => '小懒猫', 'content' => '收藏', 'clikes' => 1, 'device' => 'Android'],
    ],
    '000110' => [
        ['pname' => '小懒猫', 'content' => '好看', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '不错', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000109' => [
        ['pname' => '太犹豫被人打', 'content' => '我玩', 'clikes' => 2, 'device' => 'Android'],
        ['pname' => '高胜美', 'content' => '带我一个', 'clikes' => 1, 'device' => 'Android'],
    ],
    '000108' => [
        ['pname' => '何夕永远', 'content' => '看完了', 'clikes' => 3, 'device' => 'iPhone'],
        ['pname' => '小懒猫', 'content' => '释然', 'clikes' => 1, 'device' => 'Android'],
    ],
    '000107' => [
        ['pname' => '小懒猫', 'content' => '哈哈', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '真实', 'clikes' => 2, 'device' => 'iPhone'],
    ],
    '000106' => [
        ['pname' => '小懒猫', 'content' => '找到了吗', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '帮转', 'clikes' => 2, 'device' => 'iPhone'],
    ],
    '000105' => [
        ['pname' => '武冈事件墙', 'content' => '美丽', 'clikes' => 2, 'device' => 'Android'],
        ['pname' => '小懒猫', 'content' => '想去', 'clikes' => 1, 'device' => 'Android'],
    ],
    '000104' => [
        ['pname' => '小懒猫', 'content' => '？', 'clikes' => 0, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '？', 'clikes' => 1, 'device' => 'iPhone'],
    ],
    '000103' => [
        ['pname' => '何夕永远', 'content' => '何意味', 'clikes' => 1, 'device' => 'iPhone'],
        ['pname' => '零点的韵律', 'content' => '？', 'clikes' => 0, 'device' => 'Android'],
    ],
    '000102' => [
        ['pname' => '小懒猫', 'content' => '帅', 'clikes' => 2, 'device' => 'Android'],
        ['pname' => '高胜美', 'content' => '可以', 'clikes' => 1, 'device' => 'Android'],
    ],
    '000101' => [
        ['pname' => '小懒猫', 'content' => '我爱你', 'clikes' => 3, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '妈不饿', 'clikes' => 2, 'device' => 'iPhone'],
    ],
    '000100' => [
        ['pname' => '小懒猫', 'content' => '键盘不错', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '好看', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000099' => [
        ['pname' => '何夕永远', 'content' => '注意保暖', 'clikes' => 1, 'device' => 'iPhone'],
        ['pname' => '零点的韵律', 'content' => '风大', 'clikes' => 0, 'device' => 'Android'],
    ],
    '000098' => [
        ['pname' => '课代表', 'content' => '答案？', 'clikes' => 1, 'device' => 'iPhone'],
        ['pname' => '数学退退', 'content' => '哪个联考', 'clikes' => 2, 'device' => 'Android'],
    ],
    '000097' => [
        ['pname' => '高胜美', 'content' => '还有吗', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '太犹豫被人打', 'content' => '价格', 'clikes' => 0, 'device' => 'Android'],
    ],
    '000096' => [
        ['pname' => '小懒猫', 'content' => '能发', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '哈哈', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000095' => [
        ['pname' => '小懒猫', 'content' => '好看', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '不错', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000094' => [
        ['pname' => '小懒猫', 'content' => '不知道', 'clikes' => 0, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '风景好', 'clikes' => 1, 'device' => 'iPhone'],
    ],
    '000093' => [
        ['pname' => '小懒猫', 'content' => '确实', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '好看', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000092' => [
        ['pname' => '何夕永远', 'content' => '抱抱', 'clikes' => 2, 'device' => 'iPhone'],
        ['pname' => '小懒猫', 'content' => '会好的', 'clikes' => 1, 'device' => 'Android'],
    ],
    '000091' => [
        ['pname' => '何夕永远', 'content' => '毕业快乐', 'clikes' => 3, 'device' => 'iPhone'],
        ['pname' => '小懒猫', 'content' => '未来可期', 'clikes' => 2, 'device' => 'Android'],
    ],
    '000090' => [
        ['pname' => '何夕永远', 'content' => '加油', 'clikes' => 2, 'device' => 'iPhone'],
        ['pname' => '小懒猫', 'content' => '明天去学校', 'clikes' => 1, 'device' => 'Android'],
    ],
    '000089' => [
        ['pname' => '小懒猫', 'content' => '我也是', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '抱抱', 'clikes' => 2, 'device' => 'iPhone'],
    ],
    '000088' => [
        ['pname' => '何夕永远', 'content' => '会好的', 'clikes' => 2, 'device' => 'iPhone'],
        ['pname' => '小懒猫', 'content' => '加油', 'clikes' => 1, 'device' => 'Android'],
    ],
    '000087' => [
        ['pname' => '小懒猫', 'content' => '好看', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '链接呢', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000086' => [
        ['pname' => '小懒猫', 'content' => '报名', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '带带', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000085' => [
        ['pname' => '小懒猫', 'content' => '加了', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '美女', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000084' => [
        ['pname' => '小懒猫', 'content' => '交友', 'clikes' => 0, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '？', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000083' => [
        ['pname' => '小懒猫', 'content' => '算', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '王俊凯', 'clikes' => 2, 'device' => 'iPhone'],
    ],
    '000082' => [
        ['pname' => '小懒猫', 'content' => '哈哈', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '真实', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000081' => [
        ['pname' => '小懒猫', 'content' => '吓人', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '看完了', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000080' => [
        ['pname' => '小懒猫', 'content' => '精彩', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '推理', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000079' => [
        ['pname' => '小懒猫', 'content' => '肖战', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '数据', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000078' => [
        ['pname' => '小懒猫', 'content' => '拿捏', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '可爱', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000077' => [
        ['pname' => '小懒猫', 'content' => '有', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '？', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000076' => [
        ['pname' => '小懒猫', 'content' => '抱抱', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '会好的', 'clikes' => 2, 'device' => 'iPhone'],
    ],
    '000075' => [
        ['pname' => '小懒猫', 'content' => '去过', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '不错', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000074' => [
        ['pname' => '小懒猫', 'content' => '马宁', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '牛', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000073' => [
        ['pname' => '小懒猫', 'content' => '加油', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '注意安全', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000072' => [
        ['pname' => '何夕永远', 'content' => '加油', 'clikes' => 1, 'device' => 'iPhone'],
        ['pname' => '课代表', 'content' => '加油', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000071' => [
        ['pname' => '小懒猫', 'content' => '加油', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '加油', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000070' => [
        ['pname' => '小懒猫', 'content' => '好看', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '拍得好', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000069' => [
        ['pname' => '小懒猫', 'content' => '加了', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '？', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000068' => [
        ['pname' => '小懒猫', 'content' => '有', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '同求', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000067' => [
        ['pname' => '小懒猫', 'content' => '高级', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '确实', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000066' => [
        ['pname' => '小懒猫', 'content' => '有', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '加你', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000065' => [
        ['pname' => '小懒猫', 'content' => '帮转', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '认识吗', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000064' => [
        ['pname' => '小懒猫', 'content' => '好听', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '牛', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000063' => [
        ['pname' => '小懒猫', 'content' => '加油', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '标准', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000062' => [
        ['pname' => '小懒猫', 'content' => '美', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '爱了', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000061' => [
        ['pname' => '小懒猫', 'content' => '说得好', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '珍惜当下', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000060' => [
        ['pname' => '小懒猫', 'content' => '压力给到高一', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '哈哈', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000059' => [
        ['pname' => '小懒猫', 'content' => '期待', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '新版本', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000058' => [
        ['pname' => '小懒猫', 'content' => '别慌', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '加油', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000057' => [
        ['pname' => '小懒猫', 'content' => '帅', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '好看', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000056' => [
        ['pname' => '小懒猫', 'content' => '美女', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '哈喽', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000055' => [
        ['pname' => '小懒猫', 'content' => '狼人杀', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '击鼓传花', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000054' => [
        ['pname' => '小懒猫', 'content' => '包邮', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '哈哈', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000053' => [
        ['pname' => '小懒猫', 'content' => '惊艳', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '好看', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000052' => [
        ['pname' => '小懒猫', 'content' => '加油', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '？', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000051' => [
        ['pname' => '小懒猫', 'content' => '好看', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '文案好', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000050' => [
        ['pname' => '小懒猫', 'content' => '抱抱', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '表白吧', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000049' => [
        ['pname' => '小懒猫', 'content' => '我也要', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '我也要', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000048' => [
        ['pname' => '小懒猫', 'content' => '看哭了', 'clikes' => 2, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '写得真好', 'clikes' => 1, 'device' => 'iPhone'],
    ],
    '000047' => [
        ['pname' => '小懒猫', 'content' => '真实', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '哦', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000046' => [
        ['pname' => '小懒猫', 'content' => '难', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '高考加油', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000045' => [
        ['pname' => '小懒猫', 'content' => '富婆', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '哈哈', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000044' => [
        ['pname' => '小懒猫', 'content' => '来得及', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '课代表', 'content' => '加油', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000043' => [
        ['pname' => '小懒猫', 'content' => '？', 'clikes' => 0, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '扫了', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000042' => [
        ['pname' => '小懒猫', 'content' => '自由', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '陪一根', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000041' => [
        ['pname' => '小懒猫', 'content' => '一样', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '哈哈', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000040' => [
        ['pname' => '小懒猫', 'content' => '哈哈', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '喜欢', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000039' => [
        ['pname' => '小懒猫', 'content' => '？', 'clikes' => 0, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '数字货币', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000038' => [
        ['pname' => '小懒猫', 'content' => '加油', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '金榜题名', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000037' => [
        ['pname' => '小懒猫', 'content' => '举手', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '我要', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000036' => [
        ['pname' => '小懒猫', 'content' => '看看', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '？', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000035' => [
        ['pname' => '小懒猫', 'content' => '？', 'clikes' => 0, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '内网', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000034' => [
        ['pname' => '小懒猫', 'content' => '磕', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '好看', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000033' => [
        ['pname' => '小懒猫', 'content' => '帅', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '爱了', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000032' => [
        ['pname' => '小懒猫', 'content' => '歌单不错', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '收藏', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000031' => [
        ['pname' => '小懒猫', 'content' => '？', 'clikes' => 0, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '技术', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000030' => [
        ['pname' => '小懒猫', 'content' => '好看', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '不错', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000029' => [
        ['pname' => '小懒猫', 'content' => '？', 'clikes' => 0, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '图', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000028' => [
        ['pname' => '小懒猫', 'content' => '游戏', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '牛', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000027' => [
        ['pname' => '小懒猫', 'content' => '？', 'clikes' => 0, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '卖家', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000026' => [
        ['pname' => '小懒猫', 'content' => '吓人', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '看完了', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000001' => [
        ['pname' => '小懒猫', 'content' => '哈哈', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '别气', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000002' => [
        ['pname' => '小懒猫', 'content' => '深情', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '加油', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000003' => [
        ['pname' => '小懒猫', 'content' => '抱抱', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '课代表', 'content' => '会好的', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000004' => [
        ['pname' => '小懒猫', 'content' => '哈哈', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '？', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000005' => [
        ['pname' => '小懒猫', 'content' => '浪漫', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '爱了', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000006' => [
        ['pname' => '小懒猫', 'content' => '带带', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '我玩', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000007' => [
        ['pname' => '小懒猫', 'content' => '什么歌', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '好听', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000008' => [
        ['pname' => '小懒猫', 'content' => '牛', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '带带', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000009' => [
        ['pname' => '小懒猫', 'content' => '勇敢', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '加油', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000010' => [
        ['pname' => '小懒猫', 'content' => '抱抱', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '会好的', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000011' => [
        ['pname' => '小懒猫', 'content' => '哈哈', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '真实', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000012' => [
        ['pname' => '何夕永远', 'content' => '浪漫', 'clikes' => 1, 'device' => 'iPhone'],
        ['pname' => '课代表', 'content' => '爱了', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000013' => [
        ['pname' => '小懒猫', 'content' => '报名', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '带带', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000014' => [
        ['pname' => '小懒猫', 'content' => '什么电影', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '好看', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000015' => [
        ['pname' => '小懒猫', 'content' => '哈哈', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '别卸载', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000016' => [
        ['pname' => '小懒猫', 'content' => '加油', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '勇敢', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000017' => [
        ['pname' => '小懒猫', 'content' => '抱抱', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '会好的', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000018' => [
        ['pname' => '小懒猫', 'content' => '哈哈', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '？', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000019' => [
        ['pname' => '小懒猫', 'content' => '浪漫', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '爱了', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000020' => [
        ['pname' => '小懒猫', 'content' => '喜欢', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '我也', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000021' => [
        ['pname' => '小懒猫', 'content' => '欧', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '吸吸', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000022' => [
        ['pname' => '小懒猫', 'content' => '带带', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '666', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000023' => [
        ['pname' => '小懒猫', 'content' => '浪漫', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '爱了', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000024' => [
        ['pname' => '小懒猫', 'content' => '抱抱', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '会好的', 'clikes' => 0, 'device' => 'iPhone'],
    ],
    '000025' => [
        ['pname' => '小懒猫', 'content' => '哈哈', 'clikes' => 1, 'device' => 'Android'],
        ['pname' => '何夕永远', 'content' => '真实', 'clikes' => 0, 'device' => 'iPhone'],
    ],
];

// 4. 开始插入
$timeBase = strtotime('2026-09-11 23:50:00');
$added = 0;

foreach ($newComments as $pid => $comments) {
    $found = false;

    foreach ($posts as &$post) {
        if (($post['pid'] ?? '') !== $pid) {
            continue;
        }

        $found = true;

        if (!isset($post['comments']) || !is_array($post['comments'])) {
            $post['comments'] = [];
        }

        // 倒序插入，保证给定顺序显示在前面
        foreach (array_reverse($comments) as $c) {
            $pname = $c['pname'] ?? '';

            // 关键限制：只能 password=dca123 的用户评论
            if (!isset($validUsers[$pname])) {
                continue;
            }

            $maxCid++;
            $newComment = [
                'com_pname'    => $pname,
                'com_portrait' => $validUsers[$pname],
                'com_content'  => $c['content'] ?? '',
                'com_date'     => date('Y-m-d H:i:s', $timeBase + $added * 60),
                'com_cid'      => str_pad((string)$maxCid, 7, '0', STR_PAD_LEFT),
                'clikes'       => isset($c['clikes']) ? (int)$c['clikes'] : 0,
                'com_images'   => [],
                'com_device'   => $c['device'] ?? '',
            ];

            array_unshift($post['comments'], $newComment);
            $added++;
        }

        break;
    }

    if (!$found) {
        echo "未找到帖子：{$pid}\n";
    }
}

// 5. 备份并写入
$backup = $postsFile . '.bak.' . date('YmdHis');
copy($postsFile, $backup);

file_put_contents(
    $postsFile,
    json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
    LOCK_EX
);

// 6. 清除缓存
if (file_exists($cacheFile)) {
    @unlink($cacheFile);
}

echo "完成，新增评论 {$added} 条。\n";
echo "原文件已备份为：{$backup}\n";
echo "新的 posts.json 已生成。\n";