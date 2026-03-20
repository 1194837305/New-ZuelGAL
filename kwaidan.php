<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>【推荐】试了Project: Zuelphoria的demo，我必须说点什么 - 专栏</title>
    <style>
        /* === 基础暗黑UI变量 === */
        :root {
            --bg-color: #0f0f11;
            --box-bg: #18181c;
            --text-main: #d3d3d3;
            --text-muted: #777;
            --primary: #e50914; 
        }
        
        body {
            margin: 0; padding: 0;
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: 'Helvetica Neue', Helvetica, 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
            line-height: 1.8;
        }

        /* 顶部伪造导航栏 + 返回按钮 */
        .fake-nav {
            height: 50px; background: var(--box-bg);
            border-bottom: 1px solid #222;
            display: flex; align-items: center; padding: 0 20px;
            font-size: 14px; color: var(--text-muted);
            box-shadow: 0 2px 10px rgba(0,0,0,0.5);
            position: sticky; top: 0; z-index: 100;
        }
        .back-btn {
            color: var(--primary); text-decoration: none; font-weight: bold;
            margin-right: 20px; display: flex; align-items: center; gap: 5px;
            transition: 0.3s;
        }
        .back-btn:hover { color: #fff; text-shadow: 0 0 8px var(--primary); }
        .fake-nav span { margin-right: 15px; cursor: default; }

        /* 文章主体容器 */
        .article-container {
            max-width: 700px; margin: 40px auto; padding: 0 20px;
        }

        /* 标题与元数据 */
        .title { font-size: 28px; color: #fff; margin-bottom: 15px; font-weight: bold; line-height: 1.4; }
        .meta-data {
            display: flex; align-items: center; gap: 20px;
            font-size: 13px; color: var(--text-muted);
            margin-bottom: 40px; padding-bottom: 20px;
            border-bottom: 1px solid #2a2a2e;
        }
        .meta-data .author { color: #5c92c2; font-weight: bold; }
        
        /* 正文排版 */
        .content p { font-size: 16px; margin-bottom: 20px; text-align: justify; color: #ccc; }
        
        /* === 核心：深渊涂黑文本区 (绝对修复版) === */
        .abyss-text {
            background-color: #000;
            padding: 20px;
            border-radius: 4px;
            margin-top: 30px;
            cursor: text;
        }
        
        /* 强行覆盖段落颜色，让文字与背景融为纯黑一体 */
        .content .abyss-text p { 
            color: #000 !important; 
            background-color: #000 !important;
            margin-bottom: 15px; 
        }
        .content .abyss-text p:last-child { margin-bottom: 0; }

        /* 重点魔法：用户鼠标选中/高亮滑动时的效果 */
        .content .abyss-text p::selection, .abyss-text::selection {
            background-color: var(--primary) !important; /* 选中背景变血红 */
            color: #fff !important; /* 文字变白，显形！ */
        }
        /* 兼容火狐浏览器 */
        .content .abyss-text p::-moz-selection, .abyss-text::-moz-selection {
            background-color: var(--primary) !important;
            color: #fff !important;
        }

        /* 伪造的互动数据和评论区 */
        .interaction-bar {
            margin-top: 50px; padding: 20px 0; border-top: 1px dashed #333;
            display: flex; gap: 30px; color: var(--text-muted); font-size: 14px;
        }
        .comment-section { margin-top: 40px; }
        .comment-title { font-size: 18px; color: #fff; margin-bottom: 20px; border-left: 4px solid var(--text-muted); padding-left: 10px; }
        .comment-item { display: flex; gap: 15px; margin-bottom: 20px; border-bottom: 1px solid #222; padding-bottom: 15px; }
        .avatar { width: 40px; height: 40px; border-radius: 50%; background: #333; flex-shrink: 0; }
        .comment-body { flex: 1; }
        .comment-user { font-size: 13px; color: #5c92c2; margin-bottom: 5px; }
        .comment-text { font-size: 14px; color: #bbb; }
        .comment-time { font-size: 12px; color: #666; margin-top: 8px; }

    </style>
</head>
<body>

    <?php include_once "player.php"; ?>

    <div class="fake-nav">
        <a href="/" class="back-btn">⬅ 返回枢纽</a>
        <span>ZuelGal 创作枢纽</span> > <span>游戏评测</span> > <span>正文</span>
    </div>

    <main id="main-content">
        <div id="pjax-container">


    <div class="article-container">
        <h1 class="title">【推荐】试了Project: Zuelphoria的demo，我必须说点什么</h1>
        <div class="meta-data">
            <span class="author">稿主: 211.69.161.30</span>
            <span>发布于: 2018-09-15 03:14</span>
            <span>👁 23 阅读</span>
        </div>

        <div class="content">
            <p>上周群主在群里发了Project的demo，让我们几个帮忙测试。说实话本来没抱太高期望，同人社团做游戏嘛，能有个七八分像样就不错了。但打开之后玩了四十多分钟，我愣是没缓过神。</p>
            <p>妈呀，这完成度也太高了。</p>
            <p>开场是一段雨夜动画，镜头从窗外推进一间教室。bgm是钢琴曲，低音压得很沉，配合雨声，氛围一下就起来了。男主是个大三学生，因为学生工作活动需要，经常晚上留在文波楼的某个办公室里被指导老师压榨。某天雨夜里他在门口遇见一个女生，女生穿着白裙子，头发湿漉漉的，问他有没有伞。男主说没有，女生笑了一下，说那你半夜在这里做什么。男主说这话该我问你。</p>
            <p>就这么认识了。</p>
            <p>女生自称是已经毕业的师姐，说偶尔回来看看。两个人渐渐熟悉起来，一起熬夜、一起去小吃街吃宵夜、一起在天台看夜景。师姐话不多，但每次开口都让人觉得她知道些什么。有一次男主问她为什么总来文波楼，她说“等人”。问等谁，她笑笑没说话。</p>
            <p>人设真的讨喜。师姐是那种冷淡里带点温柔的类型，立绘精细到能看清睫毛，眨眼的小动作做得特别自然。共通线大概二十分钟，日常互动写得很有生活感——在走廊里擦肩而过时她会轻轻点个头，下雨天她会说“下次记得带伞”，有一次男主熬夜太晚趴在桌上睡着了，醒来发现身上披着一件外套，师姐坐在旁边看书，见他醒了说“走吧，食堂开门了”。</p>
            <p>那种暧昧的氛围，写得恰到好处。</p>
            <p>随着剧情推进，不对劲的地方慢慢浮现。师姐似乎知道很多关于男主的事——他以前住哪间宿舍、他养过什么宠物、他小时候有个好朋友溺水死了。男主问她怎么知道的，她说“你以前告诉过我啊”。但男主完全不记得自己说过。</p>
            <p>后来男主无意间听说一件事。文波楼几年前有个女生失踪了，就在这栋楼里，一直没找到。据说那天晚上她和朋友在教室复习，中途说去洗手间，就再也没回来。监控只拍到她走进走廊尽头，然后就消失了。</p>
            <p>男主越想越觉得不对劲，找机会问师姐认不认识这个人。师姐沉默了很久，说“她是我室友”。</p>
            <p>男主愣住了。师姐继续说，失踪那年她们大三，住同一间宿舍。那天晚上她也在文波楼，在另一间教室复习，后来她听说了这件事，一直不敢相信，铁箕山派出所给出的结果也是找不到，后来学校把这事私下处理了。毕业之后她留在这边工作，偶尔回来看看，总觉得有一天会再见到她。</p>
            <p>男主说怎么可能，人失踪了就是失踪了。师姐说，那你怎么解释——每次我走到那条走廊尽头，都能听见有人在叫我？</p>
            <p>demo在这里结束。</p>
            <p>通关之后我立刻在群里发消息问这是什么展开，那个失踪的女生到底去哪了。群主隔了很久回了一句：正式版会有多结局，每条线揭晓一部分真相。</p>
            <p>我说这剧本写得绝了，悬疑感和恋爱线结合得太好，比很多商业作都强。特别是师姐那种“你知道些什么但你不说”的感觉，太抓人了。群主发了个笑脸，说谢谢，这个项目他做了两年，剧本改过十几版，查了很多当年的真实事件资料。我问他参考了哪些作品，他说了很多，但说着说着突然加了一句，“其实最想做的不是那种推理，是想做一个你玩完之后分不清游戏和现实的故事”。</p>
            <p>我当时没太听懂。现在回想起来，他那个语气有点奇怪。</p>
            <p>总之强烈推荐群里还没试的人找群主要demo玩一下。这玩意儿放Steam上绝对能火。我已经开始期待正式版了。</p>

            <div class="abyss-text" title="■■■■■">
                <p>我特么真不知道该怎么说。</p>
                <p>demo我后来又玩了几遍。不是因为研究剧情，是因为睡不着。</p>
                <p>第一遍通关那天晚上我失眠，躺在床上脑子里全是那个失踪女生的故事。凌晨三点多的时候我突然想起一件事——我大二那年有段时间经常熬夜，有几次凌晨三四点从办公室出来，确实听见走廊尽头有人在说话。听不清说什么，以为是保安聊天，没在意。但现在想起来，那个点哪来的保安？</p>
                <p>后来我又开了二周目。这次玩的时候发现一些之前没注意的细节。游戏里有个场景是男主翻看师姐的手机，相册里有几张老照片。我第一次玩的时候没仔细看，这次一张一张翻过去，翻到最后一张的时候愣住了。</p>
                <p>那张照片是一间教室，黑板上写着字。我凑近了看，黑板上写的是“2015年秋 招新”。那是我大一时参加同好会线下聚会的教室。我记得那天来了十几个人，还有人拍了合影。但那张合影不在我手机里，也不在群相册里。</p>
                <p>我把截图发给群主，问他这张照片哪来的。他没回。</p>
                <p>三周目的时候我发现游戏里又多了一个新东西。男主手机里有一条未读消息，点开之后是一个对话框，对方头像是黑的。对话框里只有一句话：“你也在等吗？”</p>
                <p>我盯着那句话看了很久。不是因为害怕，是因为那个对话框的格式——和QQ的一模一样。</p>
                <p>我截了图，发在群里问有没有人知道这是什么。没人回。第二天那个截图在我相册里消失了。</p>
                <p>上周末我去文波楼机房找一个朋友。走到三楼的时候突然想上厕所，就往走廊尽头的厕所走。路过那间教室——就是游戏里照片上的那间——门开着一条缝。我鬼使神差地往里看了一眼。</p>
                <p>空的。只有桌椅。但黑板上真的有字。</p>
                <p>写的是：“你来了。”</p>
                <p>我当时没当回事，觉得可能是哪个学生写的。上完厕所出来，又经过那间教室，门已经关上了。我没多想就走了。</p>
                <p>那天晚上回去，我打开电脑，发现桌面上多了一个文件夹。名字就是那个demo的。我没动它，直接睡了。</p>
                <p>第二天醒来，文件夹还在。我点开，里面多了一张截图。截图里是文波楼三楼的走廊，空无一人。但仔细看，走廊尽头那间教室的门——就是昨天开着缝的那间——门口站着一个人。</p>
                <p>穿着白裙子。</p>
                <p>我看不清脸，但那个身形，和游戏里的师姐一模一样。</p>
                <p>我把图片放大。放大到最大。那个人的脸——不是脸，是五官挤在一起的那种扭曲，像被人用力揉过一样。但她嘴角弯着，在笑。</p>
                <p>我盯着那张脸看了很久。然后发现了一个细节。她站的不是门口。是门里面。门是关着的。她从门里面探出来半张脸，那种探出来的方式，不是人能做到的。</p>
                <p>我把文件夹删了。清空回收站。重启。</p>
                <p>它又回来了。里面多了一张截图，截的是我昨晚睡觉的样子：侧躺着，眼睛闭着，拍摄角度是从门缝里拍的。</p>
                <p>我住的宿舍，门关着。</p>
                <p>后来我找群主问那个失踪女生的原型到底是谁。他回了一句：“你见到她了？”</p>
                <p>我说什么意思。他说：“她以前也帮我们测试过。”</p>
                <p>我问她现在在哪。他说：“应该还在文波楼吧。她一直没走。”</p>
                <p>我问他这话什么意思。他没回。</p>
                <p>再后来，群主也很少在群里说话了。有人问他在哪，不回。有人私聊他，显示已读但不回。</p>
                <p>上周有人在学校里见过他。说他站在文波楼前面一直往上看。问他看什么，他说“等人”。问等谁，他笑笑没说话。</p>
                <p>那个人说，群主笑起来的样子，嘴角弯的角度，看着有点眼熟。</p>
                <p>像那张截图里师姐的笑。</p>
                <p>我不知道群主还在不在群里。反正没人再和他说话了。</p>
                <p>昨天晚上我又醒了。不是做梦醒的，是听见有人在叫我的名字。</p>
                <p>声音很轻，像从很远的地方传来。从走廊那边。</p>
                <p>我没出去，躺在床上听着，一动不敢动。今天早上我打开电脑，那个文件夹还在。</p>
                <p>里面多了一张截图。截的是文波楼三楼的走廊。镜头正对着那间教室的门。门开着一道缝。</p>
                <p>缝里有一只眼睛。</p>
                <p>在往外面看。</p>
                <p>在看着我。</p>
                <p>我不知道那个眼睛是谁的。但我知道一件事：那个demo的测试群里，一开始有七个人。现在还在说话的，只剩三个。另外四个人去哪了，没人知道。有人说是毕业了，有人说是退群了。</p>
                <p>但他们的QQ头像还在线。每天都在线。凌晨三点准时上线。</p>
                <p>我刚才又看了一眼那个文件夹。截图又多了。第五张。拍的是我现在的样子——坐在这里打字，屏幕的光映在脸上。拍摄角度还是从那道门缝。</p>
                <p>我回头看了一眼我房间的门。关着的。</p>
                <p>但我突然想起来一件事。游戏里师姐说过一句话：</p>
                <p>“你以为她在门外敲门，其实她早就进来了。”</p>
                <p>我不知道这句话什么意思。但刚才打字的时候，我一直觉得脖子后面有点凉。</p>
                <p>像有什么东西，站在我身后，在看我的屏幕。</p>
                <p>我写完这些就退群了。如果有人也在玩那个demo，趁早别玩了。如果已经玩过，就别再查那些失踪的人去哪了。</p>
                <p>有些问题，知道了答案，就走不掉了。</p>
                <p>还有一件事。</p>
                <p>我刚才数了一下群里的人数。还是二十几个。但突然想起来，最开始那个失踪的女生，她的名字叫什么来着？</p>
                <p>我记不起来了。</p>
                <p>但刚才那张截图里，那间教室的黑板上，除了“你来了”，下面还有一行小字。</p>
                <p>写的是：“第五个”。</p>
                <p>我是第几个来着？</p>
            </div>
        </div>

        <div class="interaction-bar">
            <span>👍 8 赞同</span>
            <span>⭐ 5 收藏</span>
            <span>🔗 分享</span>
        </div>

        <div class="comment-section">
            <h3 class="comment-title">评论 (4)</h3>
            
            <div class="comment-item">
                <div class="avatar" style="background-color: #5c92c2;"></div>
                <div class="comment-body">
                    <div class="comment-user">董暗珠</div>
                    <div class="comment-text">听你描述感觉氛围做得超神啊！求群主发个链接，我想体验一下师姐的压迫感。</div>
                    <div class="comment-time">2018-09-15 08:22</div>
                </div>
            </div>

            <div class="comment-item">
                <div class="avatar" style="background-color: #8b0000;"></div>
                <div class="comment-body">
                    <div class="comment-user">旮旯高手绿佛安</div>
                    <div class="comment-text">文波楼那个失踪案我大一的时候好像听辅导员隐晦地提过，原来是真的？？我以为是学校编出来吓唬人不让熬夜的。</div>
                    <div class="comment-time">2018-09-15 11:04</div>
                </div>
            </div>

            <div class="comment-item" style="opacity: 0.5;">
                <div class="avatar" style="background-color: #111;"></div>
                <div class="comment-body">
                    <div class="comment-user">已注销用户</div>
                    <div class="comment-text">该评论已被系统折叠或删除。</div>
                    <div class="comment-time">2018-09-16 03:00</div>
                </div>
            </div>
            
            <div style="margin-top: 30px; display: flex; gap: 15px;">
                <div class="avatar"></div>
                <div style="flex:1; height: 40px; background: #1a1a1a; border: 1px solid #333; border-radius: 4px; padding: 10px; color: #555; cursor: not-allowed;">
                    请先登录后发表评论...
                </div>
            </div>
        </div>
    </div>

</body>
</html>