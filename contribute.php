<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
$allowed_types = ['text', 'pdf', 'bilibili'];
$type = isset($_GET['type']) && in_array($_GET['type'], $allowed_types) ? $_GET['type'] : 'text';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>创作中心 - ZuelGal</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;700;900&display=swap" rel="stylesheet">
    
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">

    <style>
        :root { --primary: #c9171e; --bg-dark: #0a0a0c; }
        body { background-color: var(--bg-dark); color: #e0e0e0; font-family: 'Noto Serif SC', serif; margin: 0; }
        .editor-container { max-width: 900px; margin: 50px auto; padding: 40px; background: rgba(20, 20, 25, 0.9); border: 1px solid #333; border-radius: 12px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--primary); padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { margin: 0; font-size: 24px; color: #fff; }
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; margin-bottom: 10px; color: #aaa; font-size: 14px; }
        .form-control { width: 100%; padding: 15px; background: #111; border: 1px solid #444; color: #fff; border-radius: 6px; box-sizing: border-box; font-size: 16px; }
        .form-control:focus { border-color: var(--primary); outline: none; }
        .btn-submit { background: var(--primary); color: #fff; border: none; padding: 15px 40px; font-size: 16px; font-weight: bold; border-radius: 6px; cursor: pointer; transition: 0.3s; width: 100%; margin-top: 20px;}
        .btn-submit:hover { filter: brightness(1.2); }
        .back-link { color: #888; text-decoration: none; font-size: 14px; }
        .dynamic-section { display: none; }
        .dynamic-section.active { display: block; }

        /* 定制 Quill.js 的暗黑风格 */
        .ql-toolbar.ql-snow { border: 1px solid #444; background: #1a1a1c; border-radius: 6px 6px 0 0; }
        .ql-container.ql-snow { border: 1px solid #444; border-top: none; border-radius: 0 0 6px 6px; background: #111; height: 500px; font-size: 16px; font-family: inherit;}
        .ql-snow .ql-stroke { stroke: #ccc; }
        .ql-snow .ql-fill { fill: #ccc; }
        .ql-snow .ql-picker { color: #ccc; }
        .ql-editor { line-height: 1.8; }
        .ql-editor img { border-radius: 8px; max-width: 100%; box-shadow: 0 4px 15px rgba(0,0,0,0.5); }
    </style>
</head>
<body>

<?php 
    // 无论你在哪个子文件夹，这行代码都能精准定位到根目录的 player.php
    include_once $_SERVER['DOCUMENT_ROOT'] . "/player.php"; 
?>

<div class="editor-container">
    <div class="header">
        <h1><?php 
            if($type === 'text') echo '📝 撰写专栏文章';
            if($type === 'pdf') echo '📄 上传 PDF 档案';
            if($type === 'bilibili') echo '📺 导入 B站 专栏';
        ?></h1>
        <a href="index.php" class="back-link">← 返回资料仓库</a>
    </div>

    <form id="submission-form" onsubmit="handleSubmit(event)">
        <input type="hidden" id="post-type" value="<?php echo $type; ?>">
        
        <div class="form-group">
            <label>档案标题 <span style="color:var(--primary);">*</span></label>
            <input type="text" id="post-title" class="form-control" placeholder="请输入引人入胜的标题..." required>
        </div>

        <div class="form-group">
            <label>内容摘要 (将显示在列表页)</label>
            <textarea id="post-summary" class="form-control" rows="2" placeholder="简短的一两句话概括内容..."></textarea>
        </div>
        
        <div class="form-group">
            <label>档案封面 (推荐比例 16:9，不传则使用默认暗黑壁纸)</label>
            <div style="display: flex; gap: 15px; align-items: flex-start;">
                <div id="cover-preview" style="width: 160px; height: 90px; background: #1a1a1c; border: 1px dashed #444; border-radius: 6px; background-size: cover; background-position: center; display: flex; align-items: center; justify-content: center; color: #555; font-size: 12px;">暂无封面</div>
                <div>
                    <button type="button" style="background: #333; color: #fff; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; transition: 0.3s;" onmouseover="this.style.background='#c9171e'" onmouseout="this.style.background='#333'" onclick="document.getElementById('cover-upload-input').click()">🖼️ 上传封面图</button>
                    <input type="file" id="cover-upload-input" accept="image/png, image/jpeg, image/webp" style="display: none;" onchange="uploadCoverImage(this)">
                    <input type="hidden" id="post-cover" value="">
                    <div style="font-size: 12px; color: #888; margin-top: 10px;">支持 JPG / PNG / WEBP，最大 5MB。</div>
                </div>
            </div>
        </div>
        

        <div id="section-bilibili" class="dynamic-section <?php echo $type === 'bilibili' ? 'active' : ''; ?>">
            <div class="form-group">
                <label>B站 链接 <span style="color:var(--primary);">*</span></label>
                <input type="url" id="bili-url" class="form-control" placeholder="https://www.bilibili.com/read/cv...">
            </div>
        </div>

        <div id="section-pdf" class="dynamic-section <?php echo $type === 'pdf' ? 'active' : ''; ?>">
            <div class="form-group">
                <label>选择 PDF 文件 (Max: 20MB) <span style="color:var(--primary);">*</span></label>
                <input type="file" id="pdf-file" class="form-control" accept="application/pdf">
            </div>
        </div>

        <div id="section-text" class="dynamic-section <?php echo $type === 'text' ? 'active' : ''; ?>">
            <div class="form-group">
                <label>正文内容 <span style="color:var(--primary);">*</span></label>
                <div id="editor-container"></div>
            </div>
        </div>

        <button type="submit" class="btn-submit" id="submit-btn">🚀 提交审核</button>
    </form>
</div>

<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
    let quill = null;

    // 只有在 text 模式下才初始化编辑器，节省资源
    if (document.getElementById('post-type').value === 'text') {
        quill = new Quill('#editor-container', {
            theme: 'snow',
            placeholder: '开始撰写你的神作解析...',
            modules: {
                toolbar: {
                    container: [
                        [{ 'header': [1, 2, 3, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ 'color': [] }, { 'background': [] }],
                        ['blockquote', 'code-block'],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        ['link', 'image', 'video'],
                        ['clean']
                    ],
                    // 核心：拦截默认的 image 处理逻辑
                    handlers: {
                        image: imageHandler
                    }
                }
            }
        });
    }

    // 自定义图片上传逻辑
    function imageHandler() {
        const input = document.createElement('input');
        input.setAttribute('type', 'file');
        input.setAttribute('accept', 'image/png, image/jpeg, image/gif, image/webp');
        input.click();

        input.onchange = async () => {
            const file = input.files[0];
            if (/^image\//.test(file.type)) {
                const formData = new FormData();
                formData.append('image', file);

                try {
                    // 调用专门的图片上传接口
                    const response = await fetch('api_upload_image.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();

                    if (result.success) {
                        // 获取当前光标位置并插入图片 URL
                        const range = quill.getSelection();
                        quill.insertEmbed(range.index, 'image', result.url);
                    } else {
                        alert('图片上传失败: ' + result.message);
                    }
                } catch (error) {
                    alert('网络错误，图片上传失败！');
                }
            } else {
                alert('只能上传图片文件！');
            }
        };
    }
    
    // 增加：白嫖我们自己的图片上传 API
    async function uploadCoverImage(input) {
        if (!input.files || input.files.length === 0) return;
        const file = input.files[0];
        
        // 视觉反馈
        const previewBlock = document.getElementById('cover-preview');
        previewBlock.innerText = "上传中...";

        const formData = new FormData();
        formData.append('image', file);

        try {
            // 直接调用之前写好的富文本图片上传接口！
            const response = await fetch('api_upload_image.php', { method: 'POST', body: formData });
            const result = await response.json();

            if (result.success) {
                // 上传成功，拿到 URL
                document.getElementById('post-cover').value = result.url;
                previewBlock.innerText = "";
                previewBlock.style.backgroundImage = `url(${result.url})`;
            } else {
                previewBlock.innerText = "上传失败";
                alert('封面上传失败: ' + result.message);
            }
        } catch (error) {
            previewBlock.innerText = "网络错误";
            alert('网络异常，封面上传失败');
        }
    }
    
    // 表单整体提交逻辑
    async function handleSubmit(e) {
        e.preventDefault();
        const btn = document.getElementById('submit-btn');
        const type = document.getElementById('post-type').value;
        const title = document.getElementById('post-title').value;
        const summary = document.getElementById('post-summary').value;

        if (!title.trim()) return alert("标题不能为空！");

        const formData = new FormData();
        formData.append('type', type);
        formData.append('title', title);
        formData.append('summary', summary);
        formData.append('cover_url', document.getElementById('post-cover').value);

        if (type === 'bilibili') {
            const url = document.getElementById('bili-url').value;
            if (!url) return alert("请输入B站链接！");
            formData.append('content', url);
        } else if (type === 'pdf') {
            const fileInput = document.getElementById('pdf-file');
            if (fileInput.files.length === 0) return alert("请上传 PDF！");
            formData.append('pdf_file', fileInput.files[0]);
        } else if (type === 'text') {
            // 获取编辑器内的纯 HTML 结构
            const content = quill.root.innerHTML;
            if (content === '<p><br></p>') return alert("正文内容不能为空！");
            formData.append('content', content);
        }

        btn.disabled = true; btn.innerText = "上传与处理中...";

        try {
            const response = await fetch('api_submit_article.php', { method: 'POST', body: formData });
            const result = await response.json();
            
            if (result.status === 'success') {
                alert('🎉 档案提交成功！等待管理员在后台审核后即可公开。');
                window.location.href = 'index.php'; 
            } else {
                alert('❌ 失败: ' + result.message);
                btn.disabled = false; btn.innerText = "🚀 提交审核";
            }
        } catch (error) {
            alert('🌐 服务器异常，请稍后再试');
            btn.disabled = false; btn.innerText = "🚀 提交审核";
        }
    }
</script>
</body>
</html>