// 全局变量
const userInfo = JSON.parse(localStorage.getItem('userInfo')) || null;
let currentTab = 'latest'; // 默认显示
let lastRefreshTime = 0;
let replyUserName = ''; // 记录楼中楼要@的用户名

// 分页相关变量
let currentPage = 1;
const postsPerPage = 10;
let isLoading = false;
let hasMorePosts = true;
let postsLoaded = false;

// 新增：用于恢复位置的变量
let sessionLastViewedPid = sessionStorage.getItem('lastViewedPostId');
let isRestoringPosition = false;
let restoreTargetPid = null;
let postsListHidden = false;
let intersectionObserver = null;
let isFetching = false; // 防止并发请求

// 防止重复提交的全局锁
const submitLock = {
    login: false,
    register: false,
    post: false,
    comment: false,
    like: false
};

// 图片查看器相关变量
let currentViewerImages = [];
let currentViewerIndex = 0;

// 服务器上传限制信息 - 已修改为单张5MB，总共20MB，最多4张
const uploadLimits = {
    maxSingleSize: 5 * 1024 * 1024,    // 单张图片最大5MB
    maxTotalSize: 20 * 1024 * 1024,   // 所有图片总大小最大20MB
    maxFiles: 4                        // 最多4张图片
};

// 已点赞的内容ID集合（用于本地记录点赞状态）
let likedPosts = JSON.parse(localStorage.getItem('likedPosts')) || [];
let likedComments = JSON.parse(localStorage.getItem('likedComments')) || [];

// 搜索建议防抖定时器
let searchSuggestionTimer = null;

// 页面加载完成后执行
document.addEventListener('DOMContentLoaded', function() {
    // 初始化用户信息
    initUserInfo();
    // 初始化搜索功能
    initSearch();
    // 初始化模态框事件
    initModalEvents();
    // 初始化按钮事件
    initButtonEvents();
    // 初始化标签切换
    initTabEvents();
    // 初始化图片上传功能
    initImageUpload();
    // 初始化图片查看器
    initImageViewer();
    // 初始化设备名获取
    initDeviceName();
    // 初始化关键词点击事件
    initKeywordEvents();
    // 初始化推荐标签区域
    initRecommendTags();
    
    // 检查登录状态，未登录则显示底部登录提示
    if (!userInfo) {
        document.getElementById('bottomLoginPrompt').style.display = 'flex';
    } else {
        document.getElementById('bottomLoginPrompt').style.display = 'none';
    }
    
    // 检查是否有保存的位置
    if (sessionLastViewedPid) {
        restoreTargetPid = sessionLastViewedPid;
        isRestoringPosition = true;
        // 隐藏帖子列表
        const postsList = document.getElementById('postsList');
        if (postsList) {
            postsList.style.opacity = '0';
            postsListHidden = true;
            postsList.innerHTML = ''; // 清空现有内容，准备重新加载
        }
        // 开始加载直到目标帖子
        loadPostsUntilTarget(restoreTargetPid, 1, postsPerPage, currentTab);
    } else {
        loadPosts(currentPage, postsPerPage, currentTab, true);
    }
    
    // 检查是否有保存的上次登录ID
    checkLastLoginId();
    
    // 初始化内容折叠功能
    initContentCollapse();
    
    // 初始化评论折叠功能
    initCommentsCollapse();
    
    // 初始化伪输入框点击事件
    initFakeInputEvents();
    
    // 初始化帖子详情查看功能
    initPostDetailView();
    
    // 监听滚动事件实现无限滚动
    initInfiniteScroll();
    
    // 初始化搜索框外部点击关闭建议
    initSearchOutsideClick();
    
    // 初始化 Intersection Observer
    initIntersectionObserver();
});

// 初始化推荐标签区域
function initRecommendTags() {
    const recommendToggle = document.getElementById('recommendToggle');
    const recommendTagsContainer = document.getElementById('recommendTagsContainer');
    
    if (recommendToggle && recommendTagsContainer) {
        recommendToggle.addEventListener('click', function() {
            const isCollapsed = recommendTagsContainer.classList.contains('collapsed');
            
            if (isCollapsed) {
                recommendTagsContainer.classList.remove('collapsed');
                recommendTagsContainer.classList.add('expanded');
                recommendToggle.querySelector('span').textContent = '收起';
                recommendToggle.querySelector('i').style.transform = 'rotate(180deg)';
            } else {
                recommendTagsContainer.classList.remove('expanded');
                recommendTagsContainer.classList.add('collapsed');
                recommendToggle.querySelector('span').textContent = '展开';
                recommendToggle.querySelector('i').style.transform = 'rotate(0deg)';
            }
        });
        
        // 标签点击事件
        const recommendTags = recommendTagsContainer.querySelectorAll('.recommend-tag');
        recommendTags.forEach(tag => {
            tag.addEventListener('click', function() {
                const keyword = this.getAttribute('data-keyword');
                searchByKeyword(keyword);
            });
        });
    }
}

// 初始化搜索框外部点击关闭建议
function initSearchOutsideClick() {
    document.addEventListener('click', function(e) {
        const searchArea = document.querySelector('.search-area');
        const searchSuggestions = document.getElementById('searchSuggestions');
        
        if (searchArea && searchSuggestions && !searchArea.contains(e.target)) {
            searchSuggestions.style.display = 'none';
        }
    });
}

// 初始化无限滚动
function initInfiniteScroll() {
    let ticking = false;
    
    window.addEventListener('scroll', function() {
        if (!ticking) {
            window.requestAnimationFrame(function() {
                checkScrollPosition();
                ticking = false;
            });
            ticking = true;
        }
    });
    
    // 加载更多按钮点击事件
    document.getElementById('loadMoreBtn').addEventListener('click', function() {
        loadMorePosts();
    });
}

// 检查滚动位置
function checkScrollPosition() {
    if (isLoading || !hasMorePosts || !postsLoaded || isRestoringPosition) return;
    
    const loadMoreContainer = document.getElementById('loadMoreContainer');
    const containerRect = loadMoreContainer.getBoundingClientRect();
    const windowHeight = window.innerHeight;
    
    // 如果"加载更多"按钮进入视口，自动加载
    if (containerRect.top <= windowHeight + 100) {
        loadMorePosts();
    }
}

// 加载更多帖子
function loadMorePosts() {
    if (isLoading || !hasMorePosts || isRestoringPosition) return;
    
    currentPage++;
    loadPosts(currentPage, postsPerPage, currentTab, false);
}

// 加载帖子数据（新增回调参数）
function loadPosts(page, perPage, filter, clearExisting = false, callback = null) {
    if (isFetching) return;
    isFetching = true;
    if (isLoading) return;
    
    isLoading = true;
    postsLoaded = false;
    
    // 显示加载指示器
    const loadingIndicator = document.getElementById('loadingIndicator');
    const loadMoreContainer = document.getElementById('loadMoreContainer');
    const postsList = document.getElementById('postsList');
    
    if (clearExisting) {
        postsList.innerHTML = '';
        currentPage = 1;
        loadMoreContainer.style.display = 'none';
    }
    
    loadingIndicator.style.display = 'block';
    
    // 发送AJAX请求获取帖子数据
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'core.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.timeout = 30000;
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            isFetching = false;
            isLoading = false;
            loadingIndicator.style.display = 'none';
            
            if (xhr.status === 200) {
                try {
                    const result = JSON.parse(xhr.responseText);
                    
                    if (result.html) {
                        if (clearExisting) {
                            postsList.innerHTML = result.html;
                        } else {
                            postsList.insertAdjacentHTML('beforeend', result.html);
                        }
                        
                        // 更新点赞状态显示
                        updateLikeStatus();
                        
                        // 重新绑定事件
                        bindPostsEvents();
                        
                        // 更新是否有更多帖子
                        hasMorePosts = result.has_more;
                        
                        // 显示或隐藏"加载更多"按钮
                        if (hasMorePosts) {
                            loadMoreContainer.style.display = 'block';
                        } else {
                            loadMoreContainer.style.display = 'none';
                            if (page > 1 && result.total > 0) {
                                showGlobalTip('已加载所有帖子', 'info');
                            }
                        }
                        
                        postsLoaded = true;
                        
                        // 如果有搜索关键词，进行搜索
                        const searchBox = document.getElementById('postSearch');
                        if (searchBox.value.trim()) {
                            performSearch(searchBox.value.trim());
                        }
                        
                        // 调用回调
                        if (callback) callback(result);
                    }
                } catch (e) {
                    console.error('解析帖子数据失败:', e);
                    showGlobalTip('加载帖子失败，请刷新重试', 'error');
                }
            } else {
                showGlobalTip('网络错误，请检查连接后重试', 'error');
            }
        }
    };
    
    xhr.ontimeout = function() {
        isFetching = false;
        isLoading = false;
        loadingIndicator.style.display = 'none';
        showGlobalTip('加载超时，请稍后重试', 'error');
    };
    
    xhr.onerror = function() {
        isFetching = false;
        isLoading = false;
        loadingIndicator.style.display = 'none';
        showGlobalTip('网络连接失败，请检查网络', 'error');
    };
    
    xhr.send(`action=get_posts&page=${page}&per_page=${perPage}&filter=${filter}`);
}

// 恢复位置专用函数 - 从第 startPage 页开始加载，直到找到目标帖子
function loadPostsUntilTarget(targetPid, startPage, perPage, filter) {
    let currentPage = startPage;

    function loadNextPage() {
        if (!isRestoringPosition) return;

        loadPosts(currentPage, perPage, filter, false, function(result) {
            // 检查当前页是否包含目标帖子
            const found = result.data.some(post => post.pid === targetPid);

            if (found) {
                // 找到目标帖子，停止恢复状态
                isRestoringPosition = false;
                // 设置当前页码为找到的页
                // currentPage 已经是目标页，无需修改
                // 更新 hasMorePosts 状态（从 result 中获取）
                hasMorePosts = result.has_more;
                
                // 增强：轮询检测元素出现，确保DOM已渲染
                const checkElement = setInterval(function() {
                    const targetElement = document.querySelector(`.post-card[data-pid="${targetPid}"]`);
                    if (targetElement) {
                        clearInterval(checkElement);
                        // 使用无参数的 scrollIntoView 提高兼容性
                        targetElement.scrollIntoView();
                        const postsList = document.getElementById('postsList');
                        if (postsList) {
                            postsList.style.opacity = '1';
                        }
                        postsListHidden = false;
                    }
                }, 50);
                
                // 设置超时，防止无限等待
                setTimeout(function() {
                    clearInterval(checkElement);
                    const postsList = document.getElementById('postsList');
                    if (postsList) {
                        postsList.style.opacity = '1';
                    }
                    postsListHidden = false;
                }, 2500); // 2.5秒后强制显示
            } else {
                // 未找到，继续加载下一页
                if (result.has_more) {
                    currentPage++;
                    loadNextPage();
                } else {
                    // 没有更多页了，目标不存在，恢复正常
                    isRestoringPosition = false;
                    const postsList = document.getElementById('postsList');
                    if (postsList) {
                        postsList.style.opacity = '1';
                    }
                    postsListHidden = false;
                    showGlobalTip('未找到上次浏览的帖子，已显示最新内容', 'info');
                }
            }
        });
    }

    loadNextPage();
}

// 初始化 Intersection Observer，监测可见帖子并保存 ID 到 sessionStorage
function initIntersectionObserver() {
    if (intersectionObserver) return;

    intersectionObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const pid = entry.target.dataset.pid;
                if (pid) {
                    sessionStorage.setItem('lastViewedPostId', pid);
                }
            }
        });
    }, { threshold: 0.5 });

    // 观察已存在的帖子
    document.querySelectorAll('.post-card').forEach(card => {
        if (!card.dataset.observed) {
            intersectionObserver.observe(card);
            card.dataset.observed = 'true';
        }
    });
}

// 更新点赞状态显示
function updateLikeStatus() {
    // 更新帖子点赞状态
    likedPosts.forEach(pid => {
        const likeBtn = document.querySelector(`.post-like-btn[data-id="${pid}"]`);
        if (likeBtn) {
            const heartIcon = likeBtn.querySelector('.fa-heart');
            if (heartIcon) {
                heartIcon.classList.remove('far');
                heartIcon.classList.add('fas');
            }
        }
    });
    
    // 更新评论点赞状态
    likedComments.forEach(cid => {
        const likeBtn = document.querySelector(`.comment-like-btn[data-id="${cid}"]`);
        if (likeBtn) {
            const heartIcon = likeBtn.querySelector('.fa-heart');
            if (heartIcon) {
                heartIcon.classList.remove('far');
                heartIcon.classList.add('fas');
            }
        }
    });
}

// 绑定帖子相关事件
function bindPostsEvents() {
    // 绑定图片点击事件
    initExistingImageClickEvents();
    
    // 绑定点赞事件
    document.querySelectorAll('.post-like-btn, .comment-like-btn').forEach(btn => {
        btn.addEventListener('click', handleLikeClick);
    });
    
    // 绑定评论按钮事件
    document.querySelectorAll('.post-comment-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (!userInfo) {
                showModal('loginModal');
                return;
            }
            const pid = this.getAttribute('data-pid');
            document.getElementById('commentPid').value = pid;
            document.getElementById('commentPname').value = userInfo.pname;
            document.getElementById('commentPortrait').value = userInfo.portrait || '';
            showModal('commentModal');
        });
    });

// 分享按钮 - 全局事件委托（适配所有动态加载的帖子，不会失效）
document.addEventListener('click', function(e) {
    // 匹配分享按钮
    const shareBtn = e.target.closest('.post-share-btn');
    if (!shareBtn) return;

    e.stopPropagation();
    // 获取当前帖子卡片
    const postCard = shareBtn.closest('.post-card');
    if (!postCard) return;

    // 读取卡片上预存的数据（你原有的 data 属性，原生自带）
    const userName = postCard.dataset.pname || '';
    const content = postCard.dataset.content || '';
    // 改成你自己的域名
    const siteUrl = 'an.kijk.top';

    // 拼接分享文本
    let shareText = '';
    if(userName && content){
        shareText = `${userName}：${content}，更多内容请访问${siteUrl}`;
    }else if(userName){
        shareText = `${userName}，更多内容请访问${siteUrl}`;
    }else{
        shareText = `更多内容请访问${siteUrl}`;
    }

    // 调用你项目自带的复制函数
    const copyResult = copyToClipboard(shareText);
    if(copyResult){
        showGlobalTip('已复制！请前往其他平台分享', 'success');
    }else{
        showGlobalTip('复制失败，请手动复制', 'error');
    }
});
    
    // 绑定内容展开按钮事件
    document.querySelectorAll('.expand-content-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const pid = this.getAttribute('data-pid');
            const contentElement = document.getElementById(`postContent_${pid}`);
            if (contentElement) {
                contentElement.classList.remove('collapsed');
                contentElement.classList.add('expanded');
                this.style.display = 'none';
            }
        });
    });
    
    // 绑定评论展开按钮事件
    document.querySelectorAll('.expand-comments-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            handleExpandComments(this);
        });
    });

    // 为新帖子添加观察（用于位置记录）
    if (intersectionObserver) {
        document.querySelectorAll('.post-card:not([data-observed])').forEach(card => {
            intersectionObserver.observe(card);
            card.dataset.observed = 'true';
        });
    }
}

// 初始化伪输入框点击事件
function initFakeInputEvents() {
    // 使用事件委托处理伪输入框点击
    document.addEventListener('click', function(e) {
        const fakeInput = e.target.closest('.fake-input');
        if (fakeInput) {
            e.stopPropagation();
            const pid = fakeInput.getAttribute('data-pid');
            
            if (!userInfo) {
                showModal('loginModal');
                return;
            }
            
            // 打开评论模态框
            document.getElementById('commentPid').value = pid;
            document.getElementById('commentPname').value = userInfo.pname;
            document.getElementById('commentPortrait').value = userInfo.portrait || '';
            showModal('commentModal');
        }
    });
}

// 初始化帖子详情查看功能
function initPostDetailView() {
    // 帖子详情返回按钮
    document.getElementById('postDetailBack').addEventListener('click', function() {
        hideModal('postDetailModal');
    });
    
    // 帖子详情用户头像点击
    document.getElementById('postDetailAvatar').addEventListener('click', function(e) {
        e.stopPropagation();
        const panel = document.getElementById('userInfoPanel');
        panel.classList.toggle('show');
    });
}

// 初始化设备名获取
function initDeviceName() {
    // 获取设备信息
    const userAgent = navigator.userAgent;
    let deviceName = '未知设备';
    
    // 检测常见设备
    if (/iPhone|iPad|iPod/.test(userAgent)) {
        deviceName = /iPhone/.test(userAgent) ? 'iPhone' : 'iPad';
    } else if (/Android/.test(userAgent)) {
        deviceName = 'Android';
    } else if (/Windows/.test(userAgent)) {
        deviceName = 'Windows PC';
    } else if (/Mac/.test(userAgent)) {
        deviceName = 'Mac';
    } else if (/Linux/.test(userAgent)) {
        deviceName = 'Linux';
    }
    
    // 设置设备名
    const postDeviceName = document.getElementById('postDeviceName');
    const postDeviceNameValue = document.getElementById('postDeviceNameValue');
    const commentDeviceName = document.getElementById('commentDeviceName');
    const commentDeviceNameValue = document.getElementById('commentDeviceNameValue');
    
    if (postDeviceName) postDeviceName.textContent = deviceName;
    if (postDeviceNameValue) postDeviceNameValue.value = deviceName;
    if (commentDeviceName) commentDeviceName.textContent = deviceName;
    if (commentDeviceNameValue) commentDeviceNameValue.value = deviceName;
    
    // 设备名选择区域交互
    const postDeviceHeader = document.getElementById('postDeviceHeader');
    const postDeviceOptions = document.getElementById('postDeviceOptions');
    const commentDeviceHeader = document.getElementById('commentDeviceHeader');
    const commentDeviceOptions = document.getElementById('commentDeviceOptions');
    
    // 发帖设备选择
    if (postDeviceHeader && postDeviceOptions) {
        postDeviceHeader.addEventListener('click', function() {
            const isExpanded = postDeviceOptions.classList.contains('expanded');
            postDeviceHeader.classList.toggle('expanded', !isExpanded);
            postDeviceOptions.classList.toggle('expanded', !isExpanded);
        });
    }
    
    // 评论设备选择
    if (commentDeviceHeader && commentDeviceOptions) {
        commentDeviceHeader.addEventListener('click', function() {
            const isExpanded = commentDeviceOptions.classList.contains('expanded');
            commentDeviceHeader.classList.toggle('expanded', !isExpanded);
            commentDeviceOptions.classList.toggle('expanded', !isExpanded);
        });
    }
    
    // 设备选择变化
    const postDeviceShow = document.getElementById('postDeviceShow');
    const postDeviceHide = document.getElementById('postDeviceHide');
    const commentDeviceShow = document.getElementById('commentDeviceShow');
    const commentDeviceHide = document.getElementById('commentDeviceHide');
    
    if (postDeviceShow) {
        postDeviceShow.addEventListener('change', function() {
            document.getElementById('postDevice').value = 'show';
        });
    }
    
    if (postDeviceHide) {
        postDeviceHide.addEventListener('change', function() {
            document.getElementById('postDevice').value = 'hide';
        });
    }
    
    if (commentDeviceShow) {
        commentDeviceShow.addEventListener('change', function() {
            document.getElementById('commentDevice').value = 'show';
        });
    }
    
    if (commentDeviceHide) {
        commentDeviceHide.addEventListener('change', function() {
            document.getElementById('commentDevice').value = 'hide';
        });
    }
}

// 初始化关键词点击事件
function initKeywordEvents() {
    // 使用事件委托处理关键词点击
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('keyword')) {
            const keyword = e.target.getAttribute('data-keyword');
            searchByKeyword(keyword);
        }
    });
}

// 为已有的帖子图片添加点击事件
function initExistingImageClickEvents() {
    // 使用事件委托，提高性能
    const postsList = document.getElementById('postsList');
    if (!postsList) return;
    
    postsList.addEventListener('dblclick', function(e) {
        // 帖子图片点击
        let imageItem = e.target.closest('.post-image-item');
        if (imageItem) {
            e.stopPropagation();
            const imageSrc = imageItem.getAttribute('data-image-src');
            const postCard = imageItem.closest('.post-card');
            const allImages = Array.from(postCard.querySelectorAll('.post-image-item')).map(item => item.getAttribute('data-image-src'));
            const currentIndex = allImages.indexOf(imageSrc);
            
            if (currentIndex !== -1) {
                openImageViewer(allImages, currentIndex);
            }
            return;
        }
        
        // 评论图片点击
        imageItem = e.target.closest('.comment-image-item');
        if (imageItem) {
            e.stopPropagation();
            const imageSrc = imageItem.getAttribute('data-image-src');
            const commentItem = imageItem.closest('.comment-item');
            const allImages = Array.from(commentItem.querySelectorAll('.comment-image-item')).map(item => item.getAttribute('data-image-src'));
            const currentIndex = allImages.indexOf(imageSrc);
            
            if (currentIndex !== -1) {
                openImageViewer(allImages, currentIndex);
            }
            return;
        }
        
        // 帖子卡片点击（打开详情）
        if (e.target.closest('.post-card') && 
            !e.target.closest('.post-like-btn') &&
            !e.target.closest('.post-comment-btn') &&
            !e.target.closest('.expand-content-btn') &&
            !e.target.closest('.fake-input') &&
            !e.target.closest('.post-image-item') &&
            !e.target.closest('.action-btn') &&
            !e.target.closest('.expand-comments-btn') &&
            !e.target.closest('.comment-like-btn')) {
            
            const postCard = e.target.closest('.post-card');
            showPostDetail(postCard);
        }
    });
}

// 显示帖子详情
function showPostDetail(postCard) {
    const pid = postCard.getAttribute('data-pid');
    const pname = postCard.getAttribute('data-pname');
    const portrait = postCard.getAttribute('data-portrait');
    const pdate = postCard.getAttribute('data-pdate');
    const device = postCard.getAttribute('data-device');
    const content = postCard.getAttribute('data-content');
    const likes = postCard.getAttribute('data-likes');
    
    // 获取帖子图片
    const images = [];
    const imageItems = postCard.querySelectorAll('.post-image-item');
    imageItems.forEach(item => {
        images.push(item.getAttribute('data-image-src'));
    });
    
    // 获取评论数据
    const commentItems = postCard.querySelectorAll('.comment-item');
    const comments = [];
    commentItems.forEach(comment => {
        const cid = comment.getAttribute('data-cid');
        const comContent = comment.querySelector('.comment-content').innerHTML;
        const comPname = comment.querySelector('.comment-username').textContent;
        const comPortrait = comment.querySelector('.comment-avatar').src;
        const comDate = comment.querySelector('.comment-date').textContent.split(' ')[0];
        const clikes = comment.querySelector('.comment-like-count').textContent;
        
        // 获取评论图片
        const comImages = [];
        const comImageItems = comment.querySelectorAll('.comment-image-item');
        comImageItems.forEach(item => {
            comImages.push(item.getAttribute('data-image-src'));
        });
        
        comments.push({
            cid,
            content: comContent,
            pname: comPname,
            portrait: comPortrait,
            date: comDate,
            likes: clikes,
            images: comImages
        });
    });
    
    const detailContent = document.getElementById('postDetailContent');
    const postDetailAvatar = document.getElementById('postDetailAvatar');
    
    // 设置用户头像
    if (userInfo) {
        postDetailAvatar.src = userInfo.portrait || 'default-avatar.png';
        postDetailAvatar.style.display = 'block';
    } else {
        postDetailAvatar.style.display = 'none';
    }
    
    // 构建详情HTML
    let html = `
        <div class="post-card" style="box-shadow: none; cursor: default;">
            <div class="post-header">
                <img src="${portrait || 'default-avatar.png'}" alt="用户头像" class="post-avatar">
                <div class="post-user-info">
                    <div class="post-username">
                        ${pname}
                        ${pname.includes('段游') || pname.includes('段长安') ? 
                            '<span class="post-badge badge-auth"><i class="fas fa-check-circle"></i> 权威认证</span>' : ''}
                    </div>
                    <div class="post-date">
                        ${pdate}
                        ${device ? `<span class="post-device">${device}</span>` : ''}
                    </div>
                    <div class="post-badges">
                        ${likes >= 18 ? 
                            '<span class="post-badge badge-spark"><i class="fas fa-fire"></i> 火花</span>' : ''}
                    </div>
                </div>
            </div>
            <div class="post-content" style="max-height: none;">
                ${content.replace(/\n/g, '<br>')}
            </div>
    `;
    
    // 添加图片
    if (images && images.length > 0) {
        html += `<div class="post-images-container">`;
        images.forEach((image, index) => {
            const imageClass = images.length === 1 ? 'single' : 'multiple';
            html += `
                <div class="post-image-item ${imageClass}" data-image-src="${image}" data-index="${index}">
                    <img src="${image}" alt="帖子图片" class="post-image">
                </div>
            `;
        });
        html += `</div>`;
    }
    
    // 添加伪输入框
    html += `
        <div class="fake-input" data-pid="${pid}">
            <i class="far fa-comment"></i>
            <span>写下你的评论...</span>
        </div>
        
        <div class="post-actions">
            <button class="action-btn post-like-btn ${likedPosts.includes(pid) ? 'liked' : ''}" data-type="post" data-id="${pid}">
                <i class="${likedPosts.includes(pid) ? 'fas' : 'far'} fa-heart action-icon"></i>
                <span class="like-count">${likes}</span> 
            </button>
            <button class="action-btn post-comment-btn" data-pid="${pid}">
                <i class="far fa-comment action-icon"></i>
                <span class="comment-count">${comments.length}</span>
            </button>
        </div>
    `;
    
    // 添加评论
    if (comments.length > 0) {
        html += `<div class="comments-container">`;
        html += `<div class="comments-list">`;
        
        comments.forEach(comment => {
            const isLiked = likedComments.includes(comment.cid);
            html += `
                <div class="comment-item" data-cid="${comment.cid}">
                    <img src="${comment.portrait}" alt="评论用户头像" class="comment-avatar">
                    <div class="comment-content-wrap">
                        <div class="comment-header">
                            <div class="comment-username">${comment.pname}</div>
                            <div class="comment-date">${comment.date}</div>
                        </div>
                        <div class="comment-content">${comment.content}</div>
            `;
            
            // 评论图片
            if (comment.images && comment.images.length > 0) {
                html += `<div class="comment-images-container">`;
                comment.images.forEach(image => {
                    html += `
                        <div class="comment-image-item" data-image-src="${image}">
                            <img src="${image}" alt="评论图片" class="comment-image">
                        </div>
                    `;
                });
                html += `</div>`;
            }
            
            html += `
                        <div class="comment-actions">
                            <button class="comment-like-btn ${isLiked ? 'liked' : ''}" data-type="comment" data-id="${comment.cid}">
                                <i class="${isLiked ? 'fas' : 'far'} fa-heart"></i>
                                <span class="comment-like-count">${comment.likes}</span>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += `</div></div>`;
    }
    
    html += `</div>`;
    
    detailContent.innerHTML = html;
    
    // 重新绑定详情中的事件
    bindDetailEvents(pid);
    
    // 显示模态框
    showModal('postDetailModal');
}

// 绑定详情中的事件
function bindDetailEvents(pid) {
    const detailContent = document.getElementById('postDetailContent');
    
    // 点赞按钮
    const likeBtns = detailContent.querySelectorAll('.post-like-btn, .comment-like-btn');
    likeBtns.forEach(btn => {
        btn.addEventListener('click', handleLikeClick);
    });
    
    // 评论按钮
    const commentBtn = detailContent.querySelector('.post-comment-btn');
    if (commentBtn) {
        commentBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (!userInfo) {
                showModal('loginModal');
                return;
            }
            document.getElementById('commentPid').value = pid;
            document.getElementById('commentPname').value = userInfo.pname;
            document.getElementById('commentPortrait').value = userInfo.portrait || '';
            showModal('commentModal');
        });
    }
    
    // 伪输入框
    const fakeInput = detailContent.querySelector('.fake-input');
    if (fakeInput) {
        fakeInput.addEventListener('click', function() {
            if (!userInfo) {
                showModal('loginModal');
                return;
            }
            document.getElementById('commentPid').value = pid;
            document.getElementById('commentPname').value = userInfo.pname;
            document.getElementById('commentPortrait').value = userInfo.portrait || '';
            showModal('commentModal');
        });
    }
    
    // 图片点击
    const imageItems = detailContent.querySelectorAll('.post-image-item, .comment-image-item');
    imageItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.stopPropagation();
            const imageSrc = this.getAttribute('data-image-src');
            const container = this.closest('.post-images-container, .comment-images-container');
            const allImages = Array.from(container.querySelectorAll('[data-image-src]'))
                .map(img => img.getAttribute('data-image-src'));
            const currentIndex = allImages.indexOf(imageSrc);
            
            if (currentIndex !== -1) {
                openImageViewer(allImages, currentIndex);
            }
        });
    });
}

// 初始化内容折叠功能
function initContentCollapse() {
    // 使用事件委托处理内容展开/折叠
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('expand-content-btn')) {
            e.stopPropagation();
            const pid = e.target.getAttribute('data-pid');
            const contentElement = document.getElementById(`postContent_${pid}`);
            if (contentElement) {
                contentElement.classList.remove('collapsed');
                contentElement.classList.add('expanded');
                e.target.style.display = 'none';
            }
        }
    });
}

// 初始化评论折叠功能
function initCommentsCollapse() {
    // 使用事件委托处理评论展开/折叠 + 评论回复
    document.addEventListener('click', function(e) {
        // 原有：展开评论按钮
        if (e.target.classList.contains('expand-comments-btn')) {
            e.preventDefault();
            e.stopPropagation();
            handleExpandComments(e.target);
            return;
        }

        // ========== 新增：点击评论区域 触发回复 ==========
        const commentItem = e.target.closest('.comment-item');
        if (commentItem) {
            e.stopPropagation();
            // 1. 获取被回复的用户名
            const unameEl = commentItem.querySelector('.comment-username');
            if (!unameEl) return;
            replyUserName = unameEl.textContent.trim();

            // 2. 获取当前帖子pid（向上找post-card）
            const postCard = commentItem.closest('.post-card');
            const pid = postCard ? postCard.dataset.pid : '';
            if (!pid) return;

            // 3. 未登录 → 弹登录框
            if (!userInfo) {
                showModal('loginModal');
                return;
            }

            // 4. 填充评论弹窗基础信息
            document.getElementById('commentPid').value = pid;
            document.getElementById('commentPname').value = userInfo.pname;
            document.getElementById('commentPortrait').value = userInfo.portrait || '';

            // 5. 打开评论弹窗
            showModal('commentModal');

            // 6. 自动在输入框头部添加 @用户名 + 空格
            const commentInput = document.getElementById('commentContent');
            commentInput.value = `@${replyUserName} `;
            // 光标定位到末尾，方便继续输入
            commentInput.focus();
            return;
        }
    });
}

// 处理评论展开
function handleExpandComments(expandBtn) {
    const pid = expandBtn.getAttribute('data-pid');
    const isLoaded = expandBtn.getAttribute('data-loaded') === 'true';
    
    // 如果已经加载过，直接展开
    if (isLoaded) {
        const commentsList = document.getElementById(`comments_${pid}`);
        if (commentsList) {
            commentsList.classList.remove('collapsed');
            expandBtn.style.display = 'none';
        }
        return;
    }
    
    // 显示加载状态
    expandBtn.disabled = true;
    expandBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 加载中...';
    
    // 获取所有评论
    fetch('core.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_post_comments&pid=${encodeURIComponent(pid)}`
    })
    .then(response => response.json())
    .then(result => {
        if (result.status === 'success') {
            const comments = result.comments;
            const commentsList = document.getElementById(`comments_${pid}`);
            
            if (commentsList && comments.length > 0) {
                // 清空当前显示的评论
                commentsList.innerHTML = '';
                
                // 添加所有评论
                comments.forEach(comment => {
                    const commentElement = createCommentElement(comment);
                    commentsList.appendChild(commentElement);
                });
                
                // 移除折叠类，显示所有评论
                commentsList.classList.remove('collapsed');
                
                // 标记为已加载
                expandBtn.setAttribute('data-loaded', 'true');
                
                // 隐藏展开按钮
                expandBtn.style.display = 'none';
                
                // 重新绑定事件
                bindCommentEvents(pid);
                
                showGlobalTip('评论加载完成', 'success');
            }
        } else {
            expandBtn.disabled = false;
            expandBtn.innerHTML = '展开剩余评论';
            showGlobalTip(result.msg || '加载失败', 'error');
        }
    })
    .catch(error => {
        console.error('加载评论失败:', error);
        expandBtn.disabled = false;
        expandBtn.innerHTML = '展开剩余评论';
        showGlobalTip('网络错误，请重试', 'error');
    });
}

// 创建评论元素
function createCommentElement(comment) {
    const commentDiv = document.createElement('div');
    commentDiv.className = 'comment-item';
    commentDiv.setAttribute('data-cid', comment.com_cid);
    
    const comDevice = comment.com_device || '';
    const comImages = comment.com_images || [];
    const isLiked = likedComments.includes(comment.com_cid);
    
    // 处理评论内容中的关键词
    let comContent = escapeHtml(comment.com_content || '');
    comContent = comContent.replace(/#([^#]+)#/g, '<span class="keyword" data-keyword="$1">#$1#</span>');
    
    // 生成图片HTML
    let imagesHTML = '';
    if (comImages.length > 0) {
        imagesHTML = '<div class="comment-images-container">';
        comImages.forEach(image => {
            imagesHTML += `
                <div class="comment-image-item" data-image-src="${image}">
                    <img src="${image}" alt="评论图片" class="comment-image">
                </div>`;
        });
        imagesHTML += '</div>';
    }
    
    // 生成设备HTML
    let deviceHTML = '';
    if (comDevice) {
        deviceHTML = `<span class="comment-device">${comDevice}</span>`;
    }
    
    commentDiv.innerHTML = `
        <img src="${comment.com_portrait || 'default-avatar.png'}" alt="评论用户头像" class="comment-avatar">
        <div class="comment-content-wrap">
            <div class="comment-header">
                <div class="comment-username">${comment.com_pname}</div>
                <div class="comment-date">
                    ${comment.com_date}
                    ${deviceHTML}
                </div>
            </div>
            <div class="comment-content">
                ${comContent.replace(/\n/g, '<br>')}
            </div>
            ${imagesHTML}
            <div class="comment-actions">
                <button class="comment-like-btn ${isLiked ? 'liked' : ''}" data-type="comment" data-id="${comment.com_cid}">
                    <i class="${isLiked ? 'fas' : 'far'} fa-heart"></i>
                    <span class="comment-like-count">${comment.clikes || 0}</span>
                </button>
            </div>
        </div>`;
    
    return commentDiv;
}

// HTML转义函数
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// 重新绑定评论相关事件
function bindCommentEvents(pid) {
    const commentsList = document.getElementById(`comments_${pid}`);
    if (!commentsList) return;
    
    // 绑定点赞事件
    const likeBtns = commentsList.querySelectorAll('.comment-like-btn');
    likeBtns.forEach(btn => {
        btn.addEventListener('click', handleLikeClick);
    });
    
    // 绑定图片点击事件
    const imageItems = commentsList.querySelectorAll('.comment-image-item');
    imageItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.stopPropagation();
            const imageSrc = this.getAttribute('data-image-src');
            const allImages = Array.from(this.parentElement.querySelectorAll('.comment-image-item'))
                .map(img => img.getAttribute('data-image-src'));
            const currentIndex = allImages.indexOf(imageSrc);
            
            if (currentIndex !== -1) {
                openImageViewer(allImages, currentIndex);
            }
        });
    });
}

// 关键词搜索功能
function searchByKeyword(keyword) {
    const searchBox = document.getElementById('postSearch');
    searchBox.value = `#${keyword}#`;
    
    // 触发搜索
    performSearch(searchBox.value);
    
    // 滚动到搜索框位置
    searchBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    
    // 显示提示
    showGlobalTip(`正在搜索关键词: #${keyword}#`, 'info');
}

// 初始化图片上传功能
function initImageUpload() {
    // 发帖图片上传
    const postUploadPlaceholder = document.getElementById('postUploadPlaceholder');
    const postImagesInput = document.getElementById('postImages');
    const postImagePreview = document.getElementById('postImagePreview');
    const postImageCount = document.getElementById('postImageCount');
    
    // 评论图片上传
    const commentUploadPlaceholder = document.getElementById('commentUploadPlaceholder');
    const commentImagesInput = document.getElementById('commentImages');
    const commentImagePreview = document.getElementById('commentImagePreview');
    const commentImageCount = document.getElementById('commentImageCount');
    
    // 初始化发帖图片上传
    if (postUploadPlaceholder && postImagesInput) {
        initSingleImageUpload(postUploadPlaceholder, postImagesInput, postImagePreview, postImageCount, 'post');
    }
    
    // 初始化评论图片上传
    if (commentUploadPlaceholder && commentImagesInput) {
        initSingleImageUpload(commentUploadPlaceholder, commentImagesInput, commentImagePreview, commentImageCount, 'comment');
    }
}

// 初始化单个图片上传功能
function initSingleImageUpload(uploadPlaceholder, imagesInput, previewContainer, countElement, type) {
    let selectedFiles = [];
    
    // 点击加号打开文件选择
    uploadPlaceholder.addEventListener('click', function() {
        imagesInput.click();
    });
    
    // 文件选择变化事件
    imagesInput.addEventListener('change', async function(e) {
        let files = Array.from(e.target.files);

        // 验证文件
        const validation = validateFiles(files);
        if (!validation.valid) {
            showGlobalTip(validation.message, 'error');
            imagesInput.value = '';
            return;
        }

        // 计算还可以上传多少张图片
        const remainingSlots = uploadLimits.maxFiles - selectedFiles.length;
        let filesToAdd = files.slice(0, remainingSlots);

        if (filesToAdd.length < files.length) {
            showGlobalTip(`最多只能上传${uploadLimits.maxFiles}张图片，已自动选择前${remainingSlots}张`, 'warning');
        }

        // 转换图片为WEBP格式（异步）
        const convertibleTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/bmp'];
        const needsConversion = filesToAdd.some(f => convertibleTypes.includes(f.type.toLowerCase()));

        if (needsConversion) {
            showGlobalTip('正在优化图片格式...', 'info');
            try {
                const convertedFiles = [];
                for (const file of filesToAdd) {
                    const converted = await convertToWebP(file);
                    convertedFiles.push(converted);
                }
                filesToAdd = convertedFiles;
            } catch (err) {
                console.error('Conversion error:', err);
            }
        }

        // 检查是否有大文件
        const hasLargeFile = filesToAdd.some(file => file.size > 1 * 1024 * 1024);
        if (hasLargeFile && type === 'post') {
            const compressTip = document.getElementById('compressTip');
            if (compressTip) compressTip.style.display = 'flex';
        }

        // 添加到已选文件列表
        selectedFiles.push(...filesToAdd);

        // 更新文件输入（只保留最后4张）
        if (selectedFiles.length > uploadLimits.maxFiles) {
            selectedFiles = selectedFiles.slice(-uploadLimits.maxFiles);
        }

        // 更新显示
        updateImagePreview(selectedFiles, previewContainer, countElement, uploadLimits.maxFiles);
        updateFileInput(imagesInput, selectedFiles);
    });
    
    // 更新图片预览
    function updateImagePreview(files, container, countElement, maxFiles) {
        container.innerHTML = '';
        
        files.forEach((file, index) => {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const previewItem = document.createElement('div');
                previewItem.className = 'image-preview-item';
                
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'preview-image';
                img.alt = '预览图片';
                
                const removeBtn = document.createElement('div');
                removeBtn.className = 'remove-image';
                removeBtn.innerHTML = '×';
                removeBtn.title = '移除图片';
                
                removeBtn.addEventListener('click', function(ev) {
                    ev.stopPropagation();
                    selectedFiles.splice(index, 1);
                    updateImagePreview(selectedFiles, container, countElement, maxFiles);
                    updateFileInput(imagesInput, selectedFiles);
                    
                    if (type === 'post') {
                        const stillHasLargeFile = selectedFiles.some(file => file.size > 1 * 1024 * 1024);
                        if (!stillHasLargeFile) {
                            const compressTip = document.getElementById('compressTip');
                            if (compressTip) compressTip.style.display = 'none';
                        }
                    }
                });
                
                previewItem.appendChild(img);
                previewItem.appendChild(removeBtn);
                container.appendChild(previewItem);
            };
            
            reader.readAsDataURL(file);
        });
        
        // 更新计数
        if (countElement) {
            countElement.textContent = `${selectedFiles.length}/${maxFiles} 张图片`;
        }
        
        // 如果已选满，隐藏上传按钮
        if (selectedFiles.length >= maxFiles) {
            uploadPlaceholder.style.display = 'none';
        } else {
            uploadPlaceholder.style.display = 'flex';
        }
    }
    
    // 更新文件输入
    function updateFileInput(inputElement, files) {
        const dataTransfer = new DataTransfer();
        files.forEach(file => dataTransfer.items.add(file));
        inputElement.files = dataTransfer.files;
    }
}

// 验证文件函数
function validateFiles(files) {
    let totalSize = 0;
    
    // 检查文件数量
    if (files.length > uploadLimits.maxFiles) {
        return {
            valid: false,
            message: `最多只能选择${uploadLimits.maxFiles}张图片`
        };
    }
    
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        
        // 检查文件类型
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
        if (!allowedTypes.includes(file.type)) {
            return {
                valid: false,
                message: `文件 "${file.name}" 不是支持的图片格式。支持格式：JPG, PNG, GIF, WEBP, BMP`
            };
        }
        
        // 检查文件大小
        if (file.size > uploadLimits.maxSingleSize) {
            const sizeInMB = (file.size / (1024 * 1024)).toFixed(2);
            return {
                valid: false,
                message: `图片 "${file.name}" 过大 (${sizeInMB}MB)！每张图片不能超过5MB`
            };
        }
        
        totalSize += file.size;
    }
    
    // 检查总文件大小
    if (totalSize > uploadLimits.maxTotalSize) {
        const totalSizeInMB = (totalSize / (1024 * 1024)).toFixed(2);
        return {
            valid: false,
            message: `所有图片总大小(${totalSizeInMB}MB)超过20MB限制，请减少图片数量或压缩图片`
        };
    }
    
    return { valid: true };
}

// 初始化图片查看器
function initImageViewer() {
    const imageViewerClose = document.getElementById('imageViewerClose');
    const prevImageBtn = document.getElementById('prevImageBtn');
    const nextImageBtn = document.getElementById('nextImageBtn');
    const viewerImage = document.getElementById('viewerImage');
    const imageInfo = document.getElementById('imageInfo');
    const imageViewerHint = document.getElementById('imageViewerHint');
    
    if (!imageViewerClose || !viewerImage) return;
    
    // 关闭查看器
    imageViewerClose.addEventListener('click', function() {
        hideModal('imageViewerModal');
    });
    
    // 双击关闭
    viewerImage.addEventListener('dblclick', function() {
        hideModal('imageViewerModal');
    });
    
    // 上一张图片
    if (prevImageBtn) {
        prevImageBtn.addEventListener('click', function() {
            if (currentViewerImages.length > 0) {
                currentViewerIndex = (currentViewerIndex - 1 + currentViewerImages.length) % currentViewerImages.length;
                updateViewerImage();
            }
        });
    }
    
    // 下一张图片
    if (nextImageBtn) {
        nextImageBtn.addEventListener('click', function() {
            if (currentViewerImages.length > 0) {
                currentViewerIndex = (currentViewerIndex + 1) % currentViewerImages.length;
                updateViewerImage();
            }
        });
    }
    
    // 触摸滑动切换图片
    let touchStartX = 0;
    let touchEndX = 0;
    
    viewerImage.addEventListener('touchstart', function(e) {
        touchStartX = e.changedTouches[0].screenX;
    });
    
    viewerImage.addEventListener('touchend', function(e) {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    });
    
    function handleSwipe() {
        const threshold = 50;
        const diff = touchEndX - touchStartX;
        
        if (Math.abs(diff) > threshold) {
            if (diff > 0) {
                // 向右滑动，显示上一张
                if (prevImageBtn) prevImageBtn.click();
            } else {
                // 向左滑动，显示下一张
                if (nextImageBtn) nextImageBtn.click();
            }
        }
    }
    
    // 键盘导航
    document.addEventListener('keydown', function(e) {
        const viewerModal = document.getElementById('imageViewerModal');
        if (viewerModal && viewerModal.classList.contains('show')) {
            if (e.key === 'Escape') {
                hideModal('imageViewerModal');
            } else if (e.key === 'ArrowLeft') {
                if (prevImageBtn) prevImageBtn.click();
            } else if (e.key === 'ArrowRight') {
                if (nextImageBtn) nextImageBtn.click();
            }
        }
    });
    
    // 更新查看器图片
    function updateViewerImage() {
        if (currentViewerImages.length > 0 && currentViewerIndex >= 0 && currentViewerIndex < currentViewerImages.length) {
            viewerImage.src = currentViewerImages[currentViewerIndex];
            if (imageInfo) imageInfo.textContent = `${currentViewerIndex + 1} / ${currentViewerImages.length}`;
            
            // 更新提示
            if (imageViewerHint) {
                if (currentViewerImages.length > 1) {
                    imageViewerHint.textContent = '提示：双击图片关闭 | 左右滑动切换图片';
                } else {
                    imageViewerHint.textContent = '提示：双击图片关闭';
                }
            }
        }
    }
}

// 打开图片查看器
function openImageViewer(images, startIndex = 0) {
    if (!images || images.length === 0) return;
    
    currentViewerImages = images;
    currentViewerIndex = startIndex;
    
    const viewerImage = document.getElementById('viewerImage');
    const imageInfo = document.getElementById('imageInfo');
    const prevImageBtn = document.getElementById('prevImageBtn');
    const nextImageBtn = document.getElementById('nextImageBtn');
    const imageViewerHint = document.getElementById('imageViewerHint');
    
    if (!viewerImage) return;
    
    // 更新图片和信息
    viewerImage.src = currentViewerImages[currentViewerIndex];
    if (imageInfo) imageInfo.textContent = `${currentViewerIndex + 1} / ${currentViewerImages.length}`;
    
    // 显示/隐藏导航按钮
    if (currentViewerImages.length <= 1) {
        if (prevImageBtn) prevImageBtn.style.display = 'none';
        if (nextImageBtn) nextImageBtn.style.display = 'none';
        if (imageViewerHint) imageViewerHint.textContent = '提示：双击图片关闭';
    } else {
        if (prevImageBtn) prevImageBtn.style.display = 'flex';
        if (nextImageBtn) nextImageBtn.style.display = 'flex';
        if (imageViewerHint) imageViewerHint.textContent = '提示：双击图片关闭 | 左右滑动切换图片';
    }
    
    // 显示模态框
    showModal('imageViewerModal');
}

// 检查上次登录ID
function checkLastLoginId() {
    const lastLoginId = localStorage.getItem('lastLoginId');
    const loginIdInput = document.getElementById('loginId');
    const idMemoryTip = document.getElementById('idMemoryTip');
    
    if (lastLoginId && !userInfo && loginIdInput) {
        loginIdInput.value = lastLoginId;
        loginIdInput.classList.add('highlight-id');
        
        if (idMemoryTip) {
            idMemoryTip.innerHTML = `<i class="fas fa-history" style="color: #4a90e2;"></i> 检测到您的QQ：${lastLoginId}`;
            idMemoryTip.style.color = '#4a90e2';
            idMemoryTip.style.fontWeight = 'bold';
        }
        
        setTimeout(() => {
            loginIdInput.classList.remove('highlight-id');
        }, 3000);
        //页面加载时如果检测到本地保存的上次登录 ID，会自动填充到登录输入框并添加高亮样式；3 秒后自动移除高亮样式，恢复输入框默认外观。
    }
}

// 初始化用户信息
function initUserInfo() {
    const userAvatar = document.getElementById('userAvatar');
    const panelPname = document.getElementById('panelPname');
    const panelGender = document.getElementById('panelGender');
    const panelId = document.getElementById('panelId');
    const bottomLoginPrompt = document.getElementById('bottomLoginPrompt');
    const postDetailAvatar = document.getElementById('postDetailAvatar');

    if (userInfo) {
        if (userAvatar) {
            userAvatar.src = userInfo.portrait || 'default-avatar.png';
            userAvatar.style.display = 'block';
        }
        if (panelPname) panelPname.textContent = userInfo.pname;
        if (panelGender) panelGender.textContent = userInfo.gender;
        if (panelId) panelId.textContent = userInfo.id;
        if (bottomLoginPrompt) bottomLoginPrompt.style.display = 'none';
        
        // 设置帖子详情头像
        if (postDetailAvatar) {
            postDetailAvatar.src = userInfo.portrait || 'default-avatar.png';
            postDetailAvatar.style.display = 'block';
        }
        checkBannedStatus(userInfo.id);
    } else {
        if (userAvatar) userAvatar.style.display = 'none';
        if (bottomLoginPrompt) bottomLoginPrompt.style.display = 'flex';
        if (postDetailAvatar) postDetailAvatar.style.display = 'none';
    }

    // 头像点击显示/隐藏用户信息面板
    if (userAvatar) {
        userAvatar.addEventListener('click', function(e) {
            e.stopPropagation();
            const panel = document.getElementById('userInfoPanel');
            if (panel) panel.classList.toggle('show');
        });
    }
    
    // 帖子详情头像点击显示/隐藏用户信息面板
    if (postDetailAvatar) {
        postDetailAvatar.addEventListener('click', function(e) {
            e.stopPropagation();
            const panel = document.getElementById('userInfoPanel');
            if (panel) panel.classList.toggle('show');
        });
    }

    // 点击页面其他区域隐藏用户信息面板
    document.addEventListener('click', function() {
        const panel = document.getElementById('userInfoPanel');
        if (panel) panel.classList.remove('show');
    });

    // 退出登录
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function() {
            localStorage.removeItem('userInfo');
            localStorage.removeItem('lastLoginId');
            localStorage.removeItem('likedPosts');
            localStorage.removeItem('likedComments');
            location.reload();
        });
    }
    
    // 复制ID按钮事件
    const copyPanelIdBtn = document.getElementById('copyPanelIdBtn');
    if (copyPanelIdBtn) {
        copyPanelIdBtn.addEventListener('click', function() {
            const id = document.getElementById('panelId').textContent;
            copyToClipboard(id);
            showGlobalTip('ID已复制到剪贴板', 'success');
        });
    }
    
    // 底部登录提示点击事件
    if (bottomLoginPrompt) {
        bottomLoginPrompt.addEventListener('click', function() {
            showModal('loginModal');
        });
    }
}

// 初始化搜索功能
function initSearch() {
    const searchBox = document.getElementById('postSearch');
    if (!searchBox) return;
    
    // 输入事件 - 实时搜索
    searchBox.addEventListener('input', function() {
        const keyword = this.value.trim();
        performSearch(keyword);
        
        // 防抖获取搜索建议
        clearTimeout(searchSuggestionTimer);
        if (keyword.length > 0) {
            searchSuggestionTimer = setTimeout(() => {
                fetchSearchSuggestions(keyword);
            }, 300);
            //用户停止输入 0.3 秒后，再请求后端获取搜索建议；减少接口请求频率，做输入防抖。
        } else {
            hideSearchSuggestions();
        }
    });
    
    // 聚焦事件 - 如果有内容则显示建议
    searchBox.addEventListener('focus', function() {
        const keyword = this.value.trim();
        if (keyword.length > 0) {
            fetchSearchSuggestions(keyword);
        }
    });
    
    // 键盘事件
    searchBox.addEventListener('keydown', function(e) {
        const suggestionsList = document.getElementById('suggestionsList');
        const suggestionItems = suggestionsList ? suggestionsList.querySelectorAll('.suggestion-item') : [];
        let activeIndex = -1;
        
        // 找到当前激活的建议
        suggestionItems.forEach((item, index) => {
            if (item.classList.contains('active')) {
                activeIndex = index;
            }
        });
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (suggestionItems.length > 0) {
                suggestionItems.forEach(item => item.classList.remove('active'));
                activeIndex = (activeIndex + 1) % suggestionItems.length;
                suggestionItems[activeIndex].classList.add('active');
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (suggestionItems.length > 0) {
                suggestionItems.forEach(item => item.classList.remove('active'));
                activeIndex = activeIndex <= 0 ? suggestionItems.length - 1 : activeIndex - 1;
                suggestionItems[activeIndex].classList.add('active');
            }
        } else if (e.key === 'Enter') {
            if (activeIndex >= 0 && suggestionItems[activeIndex]) {
                e.preventDefault();
                suggestionItems[activeIndex].click();
            } else {
                hideSearchSuggestions();
            }
        } else if (e.key === 'Escape') {
            hideSearchSuggestions();
        }
    });
}

// 获取搜索建议
function fetchSearchSuggestions(keyword) {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'core.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                const result = JSON.parse(xhr.responseText);
                if (result.status === 'success') {
                    displaySearchSuggestions(result.suggestions, keyword);
                }
            } catch (e) {
                console.error('解析搜索建议失败:', e);
            }
        }
    };
    
    xhr.send(`action=get_search_suggestions&keyword=${encodeURIComponent(keyword)}`);
}

// 显示搜索建议
function displaySearchSuggestions(suggestions, keyword) {
    const searchSuggestions = document.getElementById('searchSuggestions');
    const suggestionsList = document.getElementById('suggestionsList');
    
    if (!searchSuggestions || !suggestionsList) return;
    
    if (suggestions.length === 0) {
        hideSearchSuggestions();
        return;
    }
    
    suggestionsList.innerHTML = '';
    
    suggestions.forEach((suggestion, index) => {
        const item = document.createElement('div');
        item.className = 'suggestion-item';
        if (index === 0) item.classList.add('active');
        
        let icon = 'fa-comment';
        if (suggestion.type === 'tag') icon = 'fa-hashtag';
        if (suggestion.type === 'content') icon = 'fa-file-alt';
        
        // 高亮匹配的关键词
        let text = suggestion.text;
        const keywordLower = keyword.toLowerCase();
        const pos = text.toLowerCase().indexOf(keywordLower);
        if (pos >= 0) {
            text = text.substring(0, pos) + 
                   `<strong>${text.substring(pos, pos + keyword.length)}</strong>` + 
                   text.substring(pos + keyword.length);
        }
        
        item.innerHTML = `
            <i class="fas ${icon}"></i>
            <span class="suggestion-text">${text}</span>
        `;
        
        item.addEventListener('click', function() {
            const searchBox = document.getElementById('postSearch');
            if (suggestion.type === 'tag') {
                searchBox.value = suggestion.text;
            } else {
                searchBox.value = keyword;
            }
            performSearch(searchBox.value);
            hideSearchSuggestions();
        });
        
        suggestionsList.appendChild(item);
    });
    
    searchSuggestions.style.display = 'block';
}

// 隐藏搜索建议
function hideSearchSuggestions() {
    const searchSuggestions = document.getElementById('searchSuggestions');
    if (searchSuggestions) {
        searchSuggestions.style.display = 'none';
    }
}

// 执行搜索
function performSearch(keyword) {
    if (!postsLoaded) return;
    
    const postCards = document.querySelectorAll('.post-card');
    const keywordLower = keyword.toLowerCase();
    
    if (keywordLower === '') {
        // 显示所有帖子
        postCards.forEach(card => {
            card.style.display = 'block';
        });
        return;
    }
    
    let visibleCount = 0;
    
    postCards.forEach(card => {
        const content = card.querySelector('.post-content').textContent.toLowerCase();
        const comments = card.querySelectorAll('.comment-content');
        let commentText = '';
        comments.forEach(comment => {
            commentText += comment.textContent.toLowerCase() + ' ';
        });
        
        const allText = content + ' ' + commentText;
        if (allText.includes(keywordLower)) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    // 如果没有匹配的帖子，显示提示
    if (visibleCount === 0 && keywordLower !== '') {
        // 只在用户停止输入后显示提示
        clearTimeout(window.searchTipTimer);
        window.searchTipTimer = setTimeout(() => {
            showGlobalTip(`未找到包含"${keyword}"的帖子`, 'info');
        }, 500);
    }
}

// 初始化模态框事件
function initModalEvents() {
    // 登录模态框关闭
    const loginModalClose = document.getElementById('loginModalClose');
    if (loginModalClose) {
        loginModalClose.addEventListener('click', function() {
            hideModal('loginModal');
            const loginTip = document.getElementById('loginTip');
            const loginId = document.getElementById('loginId');
            const loginPwd = document.getElementById('loginPwd');
            const loginSubmitBtn = document.getElementById('loginSubmitBtn');
            const loginSuccessId = document.getElementById('loginSuccessId');
            const registerBtn = document.getElementById('registerBtn');
            const idMemoryTip = document.getElementById('idMemoryTip');
            
            if (loginTip) loginTip.textContent = '';
            if (loginId) {
                loginId.value = '';
                loginId.style.display = 'block';
            }
            if (loginPwd) {
                loginPwd.value = '';
                loginPwd.style.display = 'block';
            }
            if (loginSubmitBtn) {
                loginSubmitBtn.disabled = false;
                loginSubmitBtn.textContent = '登录';
                loginSubmitBtn.style.display = 'block';
            }
            if (loginSuccessId) loginSuccessId.style.display = 'none';
            if (registerBtn) registerBtn.style.display = 'flex';
            if (idMemoryTip) idMemoryTip.style.display = 'flex';
            
            submitLock.login = false;
        });
    }

    // 发帖模态框关闭
    const postModalClose = document.getElementById('postModalClose');
    if (postModalClose) {
        postModalClose.addEventListener('click', function() {
            hideModal('postModal');
            const postTip = document.getElementById('postTip');
            const postContent = document.getElementById('postContent');
            const postSubmitBtn = document.getElementById('postSubmitBtn');
            const postImagePreview = document.getElementById('postImagePreview');
            const postImageCount = document.getElementById('postImageCount');
            const postUploadPlaceholder = document.getElementById('postUploadPlaceholder');
            const postImages = document.getElementById('postImages');
            const postUploadProgress = document.getElementById('postUploadProgress');
            const postUploadProgressBar = document.getElementById('postUploadProgressBar');
            const compressTip = document.getElementById('compressTip');
            const postDeviceOptions = document.getElementById('postDeviceOptions');
            const postDeviceHeader = document.getElementById('postDeviceHeader');
            
            if (postTip) postTip.textContent = '';
            if (postContent) postContent.value = '';
            if (postSubmitBtn) {
                postSubmitBtn.disabled = false;
                postSubmitBtn.textContent = '发布';
            }
            if (postImagePreview) postImagePreview.innerHTML = '';
            if (postImageCount) postImageCount.textContent = `0/${uploadLimits.maxFiles} 张图片`;
            if (postUploadPlaceholder) postUploadPlaceholder.style.display = 'flex';
            if (postImages) postImages.value = '';
            if (postUploadProgress) postUploadProgress.style.display = 'none';
            if (postUploadProgressBar) postUploadProgressBar.style.width = '0%';
            if (compressTip) compressTip.style.display = 'none';
            if (postDeviceOptions) postDeviceOptions.classList.remove('expanded');
            if (postDeviceHeader) postDeviceHeader.classList.remove('expanded');
            
            submitLock.post = false;
        });
    }

    // 评论模态框关闭
    const commentModalClose = document.getElementById('commentModalClose');
    if (commentModalClose) {
        commentModalClose.addEventListener('click', function() {
            hideModal('commentModal');
            const commentTip = document.getElementById('commentTip');
            const commentContent = document.getElementById('commentContent');
            const commentSubmitBtn = document.getElementById('commentSubmitBtn');
            const commentImagePreview = document.getElementById('commentImagePreview');
            const commentImageCount = document.getElementById('commentImageCount');
            const commentUploadPlaceholder = document.getElementById('commentUploadPlaceholder');
            const commentImages = document.getElementById('commentImages');
            const commentUploadProgress = document.getElementById('commentUploadProgress');
            const commentUploadProgressBar = document.getElementById('commentUploadProgressBar');
            const commentDeviceOptions = document.getElementById('commentDeviceOptions');
            const commentDeviceHeader = document.getElementById('commentDeviceHeader');
            
            if (commentTip) commentTip.textContent = '';
            if (commentContent) commentContent.value = '';
            if (commentSubmitBtn) {
                commentSubmitBtn.disabled = false;
                commentSubmitBtn.textContent = '发布评论';
            }
            if (commentImagePreview) commentImagePreview.innerHTML = '';
            if (commentImageCount) commentImageCount.textContent = `0/${uploadLimits.maxFiles} 张图片`;
            if (commentUploadPlaceholder) commentUploadPlaceholder.style.display = 'flex';
            if (commentImages) commentImages.value = '';
            if (commentUploadProgress) commentUploadProgress.style.display = 'none';
            if (commentUploadProgressBar) commentUploadProgressBar.style.width = '0%';
            if (commentDeviceOptions) commentDeviceOptions.classList.remove('expanded');
            if (commentDeviceHeader) commentDeviceHeader.classList.remove('expanded');
            
            replyUserName = ''; 
            
            submitLock.comment = false;
        });
    }

    // 帖子详情模态框关闭（点击遮罩层）
    const postDetailModal = document.getElementById('postDetailModal');
    if (postDetailModal) {
        postDetailModal.addEventListener('click', function(e) {
            if (e.target === this) {
                hideModal('postDetailModal');
            }
        });
    }

    // 发帖建议标签点击事件
    const suggestionTags = document.querySelectorAll('.suggestion-tag');
    suggestionTags.forEach(tag => {
        tag.addEventListener('click', function() {
            const tagText = this.getAttribute('data-tag');
            const postContent = document.getElementById('postContent');
            if (!postContent) return;
            
            const currentValue = postContent.value;
            
            if (currentValue.includes(tagText)) {
                // 如果已经包含该标签，则移除
                postContent.value = currentValue.replace(tagText + ' ', '').replace(' ' + tagText, '').replace(tagText, '');
            } else {
                // 否则添加标签
                postContent.value = currentValue + (currentValue ? ' ' : '') + tagText;
            }
            
            // 聚焦到输入框
            postContent.focus();
        });
    });

    // 登录表单提交
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (submitLock.login) {
                showGlobalTip('正在登录中，请稍候...', 'info');
                return;
            }
            
            const loginId = document.getElementById('loginId');
            const loginPwd = document.getElementById('loginPwd');
            const loginTip = document.getElementById('loginTip');
            const loginSubmitBtn = document.getElementById('loginSubmitBtn');
            
            if (!loginId || !loginPwd) return;
            
            const id = loginId.value.trim();
            const password = loginPwd.value.trim();

            if (!id || !password) {
                if (loginTip) {
                    loginTip.textContent = '账号和密码不能为空';
                    loginTip.classList.remove('success-tip');
                }
                return;
            }

            submitLock.login = true;
            loginSubmitBtn.disabled = true;
            loginSubmitBtn.innerHTML = '<span class="spinner"></span> 登录中';

            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'core.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.timeout = 15000;
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    setTimeout(() => {
                        submitLock.login = false;
                    }, 2000);
                    //登录请求发起后，2 秒内锁定登录按钮，防止用户连续重复点击登录、重复提交请求；2 秒后解除登录提交锁。
                    if (xhr.status === 200) {
                        try {
                            const res = JSON.parse(xhr.responseText);
                            if (res.status === 'success') {
                                const displayUserId = document.getElementById('displayUserId');
                                const loginSuccessId = document.getElementById('loginSuccessId');
                                const idMemoryTip = document.getElementById('idMemoryTip');
                                const registerBtn = document.getElementById('registerBtn');
                                
                                if (displayUserId) displayUserId.textContent = id;
                                if (loginSuccessId) loginSuccessId.style.display = 'block';
                                
                                loginId.style.display = 'none';
                                loginPwd.style.display = 'none';
                                if (idMemoryTip) idMemoryTip.style.display = 'none';
                                loginSubmitBtn.style.display = 'none';
                                if (registerBtn) registerBtn.style.display = 'none';
                                
                                if (loginTip) {
                                    loginTip.textContent = '';
                                    loginTip.classList.add('success-tip');
                                }
                                
                                localStorage.setItem('userInfo', JSON.stringify(res.data));
                                localStorage.setItem('lastLoginId', id);
                                
                                showGlobalTip(`登录成功！您的QQ是：${id}，请牢记`, 'success');
                                
                                setTimeout(() => {
                                    location.reload();
                                }, 1000);
                                //登录成功后展示成功提示 + 账号信息，停留 1 秒，1秒后自动刷新整个页面，加载登录后的用户状态；登录弹窗依靠页面刷新被动关闭。
                            } else {
                                loginSubmitBtn.disabled = false;
                                loginSubmitBtn.textContent = '登录';
                                if (loginTip) {
                                    loginTip.textContent = res.msg;
                                    loginTip.classList.remove('success-tip');
                                }
                            }
                        } catch (e) {
                            loginSubmitBtn.disabled = false;
                            loginSubmitBtn.textContent = '登录';
                            if (loginTip) {
                                loginTip.textContent = '服务器响应异常，请稍后重试';
                                loginTip.classList.remove('success-tip');
                            }
                        }
                    } else {
                        loginSubmitBtn.disabled = false;
                        loginSubmitBtn.textContent = '登录';
                        if (loginTip) {
                            loginTip.textContent = '网络错误，请稍后重试';
                            loginTip.classList.remove('success-tip');
                        }
                    }
                }
            };
            
            xhr.ontimeout = function() {
                submitLock.login = false;
                loginSubmitBtn.disabled = false;
                loginSubmitBtn.textContent = '登录';
                if (loginTip) {
                    loginTip.textContent = '网络超时，请稍后重试';
                    loginTip.classList.remove('success-tip');
                }
            };
            
            xhr.onerror = function() {
                submitLock.login = false;
                loginSubmitBtn.disabled = false;
                loginSubmitBtn.textContent = '登录';
                if (loginTip) {
                    loginTip.textContent = '网络连接失败，请检查网络';
                    loginTip.classList.remove('success-tip');
                }
            };
            
            xhr.send(`action=login&id=${encodeURIComponent(id)}&password=${encodeURIComponent(password)}`);
        });
    }

    // 复制ID功能
    const copyIdBtn = document.getElementById('copyIdBtn');
    if (copyIdBtn) {
        copyIdBtn.addEventListener('click', function() {
            const displayUserId = document.getElementById('displayUserId');
            if (displayUserId) {
                const id = displayUserId.textContent;
                copyToClipboard(id);
                showGlobalTip('ID已复制到剪贴板', 'success');
            }
        });
    }

    // 发帖表单提交
    const postForm = document.getElementById('postForm');
    if (postForm) {
        postForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (submitLock.post) {
                showGlobalTip('正在发布中，请稍候...', 'info');
                return;
            }
            
            const postContent = document.getElementById('postContent');
            const postPname = document.getElementById('postPname');
            const postPortrait = document.getElementById('postPortrait');
            const postDevice = document.getElementById('postDevice');
            const postDeviceNameValue = document.getElementById('postDeviceNameValue');
            const postTip = document.getElementById('postTip');
            const postSubmitBtn = document.getElementById('postSubmitBtn');
            const postImagesInput = document.getElementById('postImages');
            
            if (!postContent || !postPname || !postPortrait || !postDevice) return;
            
            const content = postContent.value.trim();
            const pname = postPname.value;
            const portrait = postPortrait.value;
            const device = postDevice.value;
            const deviceName = postDeviceNameValue ? postDeviceNameValue.value : '';

            if (!content) {
                if (postTip) {
                    postTip.textContent = '帖子内容不能为空';
                    postTip.classList.remove('success-tip');
                }
                return;
            }

            if (postImagesInput && postImagesInput.files.length > uploadLimits.maxFiles) {
                if (postTip) {
                    postTip.textContent = `最多只能上传${uploadLimits.maxFiles}张图片`;
                    postTip.classList.remove('success-tip');
                }
                return;
            }

            if (postImagesInput && postImagesInput.files.length > 0) {
                const validation = validateFiles(Array.from(postImagesInput.files));
                if (!validation.valid) {
                    if (postTip) {
                        postTip.textContent = validation.message;
                        postTip.classList.remove('success-tip');
                    }
                    return;
                }
            }

            submitLock.post = true;
            postSubmitBtn.disabled = true;
            postSubmitBtn.innerHTML = '<span class="spinner"></span> 发布中';

            const progressBar = document.getElementById('postUploadProgress');
            const progressBarInner = document.getElementById('postUploadProgressBar');
            if (progressBar) progressBar.style.display = 'block';
            if (progressBarInner) progressBarInner.style.width = '0%';

            const formData = new FormData();
            formData.append('action', 'publish_post');
            formData.append('content', content);
            formData.append('pname', pname);
            formData.append('portrait', portrait);
            formData.append('device', device);
            formData.append('device_name', deviceName);
            
            if (postImagesInput) {
                const postImages = postImagesInput.files;
                for (let i = 0; i < postImages.length; i++) {
                    formData.append('post_images[]', postImages[i]);
                }
            }

            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'core.php', true);
            xhr.timeout = 60000;
            
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    if (progressBarInner) progressBarInner.style.width = percent + '%';
                    
                    if (percent < 100) {
                        postSubmitBtn.innerHTML = `<span class="spinner"></span> 上传中 ${percent}%`;
                    }
                }
            });
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    setTimeout(() => {
                        submitLock.post = false;
                    }, 2000);
                    
                    if (xhr.status === 200) {
                        try {
                            const res = JSON.parse(xhr.responseText);
                            if (postTip) postTip.textContent = res.msg;
                            if (res.status === 'success') {
                                if (postTip) postTip.classList.add('success-tip');
                                showGlobalTip('发布成功！', 'success');
                                
                                // 20260611新增：清除上次浏览位置，刷新后回到顶部
                                sessionStorage.removeItem('lastViewedPostId');

                                setTimeout(() => {
                                    location.reload();
                                }, 300);
                                //控制动态发布后几秒执行页面刷新
                            } else {
                                if (postTip) postTip.classList.remove('success-tip');
                                let errorMsg = res.msg;
                                if (res.msg.includes('size') || res.msg.includes('大') || res.msg.includes('MB')) {
                                    errorMsg += '。建议：1.压缩图片 2.减少图片数量 3.单张图片不超过5MB';
                                }
                                showGlobalTip(errorMsg, 'error');
                                postSubmitBtn.disabled = false;
                                postSubmitBtn.textContent = '发布';
                            }
                        } catch (e) {
                            if (postTip) {
                                postTip.textContent = '服务器响应异常，请稍后重试';
                                postTip.classList.remove('success-tip');
                            }
                            showGlobalTip('服务器响应异常，请稍后重试', 'error');
                            postSubmitBtn.disabled = false;
                            postSubmitBtn.textContent = '发布';
                        }
                    } else {
                        let errorMsg = '网络错误，请稍后重试';
                        if (xhr.status === 413) {
                            errorMsg = '文件太大！服务器拒绝了上传请求。建议压缩图片或减少数量';
                        } else if (xhr.status === 0) {
                            errorMsg = '网络连接失败或请求超时。请检查网络连接后重试';
                        } else if (xhr.status === 500) {
                            errorMsg = '服务器内部错误，请稍后重试';
                        }
                        
                        if (postTip) {
                            postTip.textContent = errorMsg;
                            postTip.classList.remove('success-tip');
                        }
                        showGlobalTip(errorMsg, 'error');
                        postSubmitBtn.disabled = false;
                        postSubmitBtn.textContent = '发布';
                    }
                    
                    if (progressBar) progressBar.style.display = 'none';
                }
            };
            
            xhr.ontimeout = function() {
                submitLock.post = false;
                postSubmitBtn.disabled = false;
                postSubmitBtn.textContent = '发布';
                if (progressBar) progressBar.style.display = 'none';
                showGlobalTip('上传超时（60秒），可能是图片太大或网络慢。建议压缩图片再试', 'error');
            };
            
            xhr.onerror = function() {
                submitLock.post = false;
                postSubmitBtn.disabled = false;
                postSubmitBtn.textContent = '发布';
                if (progressBar) progressBar.style.display = 'none';
                showGlobalTip('网络错误，可能是图片太大导致。建议：1.压缩图片 2.使用WiFi网络 3.分多次上传', 'error');
            };
            
            xhr.send(formData);
        });
    }

    // 评论表单提交
    const commentForm = document.getElementById('commentForm');
    if (commentForm) {
        commentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (submitLock.comment) {
                showGlobalTip('正在评论中，请稍候...', 'info');
                return;
            }
            
            const commentContent = document.getElementById('commentContent');
            const commentPid = document.getElementById('commentPid');
            const commentPname = document.getElementById('commentPname');
            const commentPortrait = document.getElementById('commentPortrait');
            const commentDevice = document.getElementById('commentDevice');
            const commentDeviceNameValue = document.getElementById('commentDeviceNameValue');
            const commentTip = document.getElementById('commentTip');
            const commentSubmitBtn = document.getElementById('commentSubmitBtn');
            const commentImagesInput = document.getElementById('commentImages');
            
            if (!commentContent || !commentPid || !commentPname) return;
            
            const comContent = commentContent.value.trim();
            const pid = commentPid.value;
            const pname = commentPname.value;
            const portrait = commentPortrait ? commentPortrait.value : '';
            const device = commentDevice ? commentDevice.value : 'show';
            const deviceName = commentDeviceNameValue ? commentDeviceNameValue.value : '';

            if (!comContent || !pid) {
                if (commentTip) {
                    commentTip.textContent = '评论内容不能为空';
                    commentTip.classList.remove('success-tip');
                }
                return;
            }

            if (commentImagesInput && commentImagesInput.files.length > uploadLimits.maxFiles) {
                if (commentTip) {
                    commentTip.textContent = `最多只能上传${uploadLimits.maxFiles}张图片`;
                    commentTip.classList.remove('success-tip');
                }
                return;
            }

            if (commentImagesInput && commentImagesInput.files.length > 0) {
                const validation = validateFiles(Array.from(commentImagesInput.files));
                if (!validation.valid) {
                    if (commentTip) {
                        commentTip.textContent = validation.message;
                        commentTip.classList.remove('success-tip');
                    }
                    return;
                }
            }

            submitLock.comment = true;
            commentSubmitBtn.disabled = true;
            commentSubmitBtn.innerHTML = '<span class="spinner"></span> 发布中';

            const progressBar = document.getElementById('commentUploadProgress');
            const progressBarInner = document.getElementById('commentUploadProgressBar');
            if (progressBar) progressBar.style.display = 'block';
            if (progressBarInner) progressBarInner.style.width = '0%';

            const formData = new FormData();
            formData.append('action', 'publish_comment');
            formData.append('com_content', comContent);
            formData.append('pid', pid);
            formData.append('pname', pname);
            formData.append('portrait', portrait);
            formData.append('device', device);
            formData.append('device_name', deviceName);
            
            if (commentImagesInput) {
                const commentImages = commentImagesInput.files;
                for (let i = 0; i < commentImages.length; i++) {
                    formData.append('comment_images[]', commentImages[i]);
                }
            }

            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'core.php', true);
            xhr.timeout = 60000;
            
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    if (progressBarInner) progressBarInner.style.width = percent + '%';
                    
                    if (percent < 100) {
                        commentSubmitBtn.innerHTML = `<span class="spinner"></span> 上传中 ${percent}%`;
                    }
                }
            });
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    setTimeout(() => {
                        submitLock.comment = false;
                    }, 2000);
                    
                    if (xhr.status === 200) {
                        try {
                            const res = JSON.parse(xhr.responseText);
                            if (commentTip) commentTip.textContent = res.msg;
                            if (res.status === 'success') {
                                if (commentTip) commentTip.classList.add('success-tip');
                                showGlobalTip('评论成功！', 'success');
                                // 不再清除ID，保持位置记忆
                                setTimeout(() => {
                                    location.reload();
                                }, 300);
                                //控制评论发布后几秒执行页面刷新
                            } else {
                                if (commentTip) commentTip.classList.remove('success-tip');
                                showGlobalTip(res.msg, 'error');
                                commentSubmitBtn.disabled = false;
                                commentSubmitBtn.textContent = '发布评论';
                            }
                        } catch (e) {
                            if (commentTip) {
                                commentTip.textContent = '服务器响应异常，请稍后重试';
                                commentTip.classList.remove('success-tip');
                            }
                            showGlobalTip('服务器响应异常，请稍后重试', 'error');
                            commentSubmitBtn.disabled = false;
                            commentSubmitBtn.textContent = '发布评论';
                        }
                    } else {
                        let errorMsg = '网络错误，请稍后重试';
                        if (xhr.status === 413) {
                            errorMsg = '评论图片太大！服务器拒绝了上传请求';
                        }
                        
                        if (commentTip) {
                            commentTip.textContent = errorMsg;
                            commentTip.classList.remove('success-tip');
                        }
                        showGlobalTip(errorMsg, 'error');
                        commentSubmitBtn.disabled = false;
                        commentSubmitBtn.textContent = '发布评论';
                    }
                    
                    if (progressBar) progressBar.style.display = 'none';
                }
            };
            
            xhr.ontimeout = function() {
                submitLock.comment = false;
                commentSubmitBtn.disabled = false;
                commentSubmitBtn.textContent = '发布评论';
                if (progressBar) progressBar.style.display = 'none';
                showGlobalTip('评论上传超时，请稍后重试', 'error');
            };
            
            xhr.onerror = function() {
                submitLock.comment = false;
                commentSubmitBtn.disabled = false;
                commentSubmitBtn.textContent = '发布评论';
                if (progressBar) progressBar.style.display = 'none';
                showGlobalTip('网络错误，请稍后重试', 'error');
            };
            
            xhr.send(formData);
        });
    }
    
    // 注册按钮防重复点击
    const registerBtn = document.getElementById('registerBtn');
    if (registerBtn) {
        registerBtn.addEventListener('click', function(e) {
            if (window.location.href.includes('register.php')) return;
            
            if (submitLock.register) {
                e.preventDefault();
                showGlobalTip('正在跳转中，请稍候...', 'info');
                return;
            }
            
            submitLock.register = true;
            const originalText = this.innerHTML;
            this.innerHTML = '<span class="spinner"></span> 跳转中...';
            this.style.opacity = '0.7';
            
            setTimeout(() => {
                submitLock.register = false;
                this.innerHTML = originalText;
                this.style.opacity = '1';
            }, 1000);
            //控制登录模态框点击注册按钮后几秒后执行跳转
        });
    }
}

// 复制到剪贴板函数
function copyToClipboard(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    
    try {
        document.execCommand('copy');
        return true;
    } catch (err) {
        console.error('复制失败:', err);
        return false;
    } finally {
        document.body.removeChild(textarea);
    }
}

// 初始化标签切换事件
function initTabEvents() {
    const latestTab = document.getElementById('latestTab');
    const type1Tab = document.getElementById('type1Tab');
    const type2Tab = document.getElementById('type2Tab');
    const type3Tab = document.getElementById('type3Tab');
    const type4Tab = document.getElementById('type4Tab');
    const type5Tab = document.getElementById('type5Tab');
    const refreshBtn = document.getElementById('refreshBtn');
    const currentTabText = document.getElementById('currentTab');
    
    if (!latestTab || !type1Tab || !type2Tab || !type3Tab || !type4Tab || !type5Tab) return;
    
    // 最新标签点击
    latestTab.addEventListener('click', function() {
        if (currentTab === 'latest') {
            refreshPosts();
            return;
        }
        
        switchTab('latest');
    });
    
    // 标签点击
    type1Tab.addEventListener('click', function() {
        if (currentTab === 'type1') {
            refreshPosts();
            return;
        }
        
        switchTab('type1');
    });
    
    // 标签点击
    type2Tab.addEventListener('click', function() {
        if (currentTab === 'type2') {
            refreshPosts();
            return;
        }
        
        switchTab('type2');
    });
    
    // 标签点击
    type3Tab.addEventListener('click', function() {
        if (currentTab === 'type3') {
            refreshPosts();
            return;
        }
        
        switchTab('type3');
    });
    
    // 标签点击
    type4Tab.addEventListener('click', function() {
        if (currentTab === 'type4') {
            refreshPosts();
            return;
        }
        
        switchTab('type4');
    });

        // 标签点击
    type5Tab.addEventListener('click', function() {
        if (currentTab === 'type5') {
            refreshPosts();
            return;
        }
        
        switchTab('type5');
    });
    
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            refreshPosts();
        });
    }
    
    function switchTab(newTab) {
        currentTab = newTab;
        updateTabUI();
        sessionStorage.removeItem('lastViewedPostId'); // 切换标签时清除旧位置
        loadPosts(1, postsPerPage, currentTab, true);
    }
    
    function updateTabUI() {
        latestTab.classList.toggle('active', currentTab === 'latest');
        type1Tab.classList.toggle('active', currentTab === 'type1');
        type2Tab.classList.toggle('active', currentTab === 'type2');
        type3Tab.classList.toggle('active', currentTab === 'type3');
        type4Tab.classList.toggle('active', currentTab === 'type4');
        type5Tab.classList.toggle('active', currentTab === 'type5');
        if (currentTabText) {
            currentTabText.textContent = currentTab === 'latest' ? '最新' : 
                                        currentTab === 'type1' ? '交友' : 
                                        currentTab === 'type2' ? 'emo' : 
                                        currentTab === 'type3' ? '游戏' :
                                        currentTab === 'type4' ? '日常' : 
                                        currentTab === 'type5' ? '表白' : '';
        }
    }
    
    function refreshPosts() {
        const now = Date.now();
        if (now - lastRefreshTime < 3000) {
            showGlobalTip('刷新太频繁了，请稍后再试', 'info');
            return;
        }
        
        lastRefreshTime = now;
        loadPosts(1, postsPerPage, currentTab, true);
        showGlobalTip('刷新成功', 'success');
    }
}

// 初始化按钮事件
function initButtonEvents() {
    // 发帖按钮
    const publishPostBtn = document.getElementById('publishPostBtn');
    if (publishPostBtn) {
        publishPostBtn.addEventListener('click', function() {
            if (!userInfo) {
                showModal('loginModal');
                return;
            }
            const postPname = document.getElementById('postPname');
            const postPortrait = document.getElementById('postPortrait');
            if (postPname) postPname.value = userInfo.pname;
            if (postPortrait) postPortrait.value = userInfo.portrait || '';
            showModal('postModal');
        });
    }

    // 置顶按钮
    const topIcon = document.getElementById('topIcon');
    if (topIcon) {
        topIcon.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
}

// 点赞点击处理
function handleLikeClick(e) {
    e.stopPropagation();
    
    if (!userInfo) {
        showModal('loginModal');
        return;
    }
    
    if (submitLock.like) {
        showGlobalTip('正在处理中，请稍候...', 'info');
        return;
    }
    
    const type = this.getAttribute('data-type');
    const id = this.getAttribute('data-id');
    const heartIcon = this.querySelector('.fa-heart');
    const countSpan = this.querySelector('.like-count, .comment-like-count');
    let currentCount = parseInt(countSpan.textContent) || 0;
    
    // 判断当前是否已点赞
    const isLiked = heartIcon.classList.contains('fas');
    const action = isLiked ? 'remove' : 'add';
    
    // 先更新前端状态
    if (!isLiked) {
        heartIcon.classList.remove('far');
        heartIcon.classList.add('fas');
        countSpan.textContent = currentCount + 1;
        this.classList.add('liked');
        
        // 保存到本地记录
        if (type === 'post' && !likedPosts.includes(id)) {
            likedPosts.push(id);
            localStorage.setItem('likedPosts', JSON.stringify(likedPosts));
        } else if (type === 'comment' && !likedComments.includes(id)) {
            likedComments.push(id);
            localStorage.setItem('likedComments', JSON.stringify(likedComments));
        }
    } else {
        heartIcon.classList.remove('fas');
        heartIcon.classList.add('far');
        countSpan.textContent = Math.max(0, currentCount - 1);
        this.classList.remove('liked');
        
        // 从本地记录移除
        if (type === 'post') {
            likedPosts = likedPosts.filter(pid => pid !== id);
            localStorage.setItem('likedPosts', JSON.stringify(likedPosts));
        } else if (type === 'comment') {
            likedComments = likedComments.filter(cid => cid !== id);
            localStorage.setItem('likedComments', JSON.stringify(likedComments));
        }
    }
    
    submitLock.like = true;
    
    // 添加动画效果
    this.classList.add('liked');
    setTimeout(() => this.classList.remove('liked'), 500);
    
    // 发送点赞请求
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'core.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.timeout = 10000;
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            submitLock.like = false;
            
            if (xhr.status === 200) {
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res.status !== 'success') {
                        // 如果请求失败，回退状态
                        if (isLiked) {
                            heartIcon.classList.remove('far');
                            heartIcon.classList.add('fas');
                            countSpan.textContent = currentCount;
                        } else {
                            heartIcon.classList.remove('fas');
                            heartIcon.classList.add('far');
                            countSpan.textContent = currentCount;
                        }
                        showGlobalTip(res.msg, 'error');
                    } else {
                        showGlobalTip(action === 'add' ? '点赞成功！' : '已取消点赞', 'success');
                    }
                } catch (e) {
                    console.error('解析点赞响应失败:', e);
                }
            } else {
                // 网络错误，回退状态
                if (isLiked) {
                    heartIcon.classList.remove('far');
                    heartIcon.classList.add('fas');
                    countSpan.textContent = currentCount;
                } else {
                    heartIcon.classList.remove('fas');
                    heartIcon.classList.add('far');
                    countSpan.textContent = currentCount;
                }
                showGlobalTip('网络错误，请稍后重试', 'error');
            }
        }
    };
    
    xhr.ontimeout = function() {
        submitLock.like = false;
        if (isLiked) {
            heartIcon.classList.remove('far');
            heartIcon.classList.add('fas');
            countSpan.textContent = currentCount;
        } else {
            heartIcon.classList.remove('fas');
            heartIcon.classList.add('far');
            countSpan.textContent = currentCount;
        }
        showGlobalTip('点赞超时，请稍后重试', 'error');
    };
    
    xhr.send(`action=like&type=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}&like_action=${action}`);
}

// 显示全局提示
function showGlobalTip(message, type = 'info') {
    const tip = document.getElementById('globalTip');
    if (!tip) return;
    
    tip.textContent = message;
    tip.className = 'global-tip ' + type;
    tip.classList.add('show');
    
    let duration = 3000;
    if (type === 'success') duration = 4000;
    if (type === 'error') duration = 5000;
    if (type === 'warning') duration = 4500;
    if (message.includes('ID')) duration = 5000;
    
    if (tip.timeoutId) clearTimeout(tip.timeoutId);
    
    tip.timeoutId = setTimeout(() => {
        tip.classList.remove('show');
    }, duration);
}

// 显示模态框
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.add('show');
}

// 隐藏模态框
function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.remove('show');
}

// 处理链接点击
function handleLinkClick(linkElement) {
    const href = linkElement.getAttribute('href');
    const fallback = linkElement.getAttribute('data-fallback');
    
    window.location.href = href;
    
    setTimeout(function() {
        if (window.location.href.indexOf(href) === -1 && fallback) {
            window.location.href = fallback;
        }
    }, 3000);
    
    return false;
}

// ==================== 图片转WEBP功能 ====================

// 将文件转换为WEBP格式（使用Canvas）
async function convertToWebP(file) {
    return new Promise((resolve, reject) => {
        // 如果已经是WEBP或GIF，直接返回原文件
        const convertibleTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/bmp'];
        if (!convertibleTypes.includes(file.type.toLowerCase())) {
            resolve(file);
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            const img = new Image();
            img.onload = function() {
                // 计算缩放后的尺寸
                let width = img.width;
                let height = img.height;
                const maxSize = 1920;

                if (width > maxSize || height > maxSize) {
                    const ratio = Math.min(maxSize / width, maxSize / height);
                    width = Math.floor(width * ratio);
                    height = Math.floor(height * ratio);
                }

                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);

                canvas.toBlob(function(blob) {
                    if (blob) {
                        const newFileName = file.name.replace(/\.[^.]+$/, '') + '.webp';
                        const webpFile = new File([blob], newFileName, {
                            type: 'image/webp',
                            lastModified: file.lastModified
                        });
                        resolve(webpFile);
                    } else {
                        resolve(file);
                    }
                }, 'image/webp', 0.85);
            };
            img.onerror = function() {
                resolve(file);
            };
            img.src = e.target.result;
        };
        reader.onerror = function() {
            resolve(file);
        };
        reader.readAsDataURL(file);
    });
}
// 对外暴露：完整唤起发帖弹窗（复刻原 publishPostBtn 所有逻辑）
window.callPublishModal = function() {
    // 复用全局登录信息
    if (!userInfo) {
        showModal('loginModal');
        return;
    }
    // 给发帖表单赋值（昵称、头像）
    const postPname = document.getElementById('postPname');
    const postPortrait = document.getElementById('postPortrait');
    if (postPname) postPname.value = userInfo.pname;
    if (postPortrait) postPortrait.value = userInfo.portrait || '';
    // 最终打开发帖模态框
    showModal('postModal');
};
// 检查用户是否被列入黑名单
function checkBannedStatus(userId) {
    if (!userId) return;
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'core.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                const res = JSON.parse(xhr.responseText);
                if (res.status === 'banned') {
                    // 弹出浏览器默认弹窗
                    alert(res.msg);
                    // 强制退出：清除所有本地存储
                    localStorage.removeItem('userInfo');
                    localStorage.removeItem('lastLoginId');
                    localStorage.removeItem('likedPosts');
                    localStorage.removeItem('likedComments');
                    // 重置按钮和提示
    loginSubmitBtn.disabled = false;
    loginSubmitBtn.textContent = '登录';
    if (loginTip) {
        loginTip.textContent = '登录被限制，请查看提示';
        loginTip.classList.remove('success-tip');
    }
    // 不刷新页面，停留在登录框
    return;
                }
            } catch (e) {
                console.error('黑名单检查失败:', e);
            }
        }
    };
    xhr.send(`action=check_banned&id=${encodeURIComponent(userId)}`);
}