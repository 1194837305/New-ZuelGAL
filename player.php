<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aplayer/dist/APlayer.min.css">
<script src="https://cdn.jsdelivr.net/npm/aplayer/dist/APlayer.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/meting@2.0.1/dist/Meting.min.js"></script>

<style>
    /* === 展开状态：精致暗红浮窗 === */
    .aplayer.aplayer-fixed {
        bottom: 30px !important;
        right: 30px !important;
        left: auto !important;
        width: 380px !important;
        background: rgba(10, 10, 12, 0.95) !important;
        backdrop-filter: blur(20px);
        border: 1px solid #c9171e !important; 
        border-radius: 12px !important;
        z-index: 100000 !important;
        box-shadow: 0 10px 50px rgba(0,0,0,0.8) !important;
        transition: all 0.3s ease-in-out;
    }

    /* === 关键修复：收缩状态彻底消灭红线 === */
    .aplayer.aplayer-fixed.aplayer-narrow {
        width: 66px !important;
        border: none !important;
        background: transparent !important;
    }

    .aplayer.aplayer-fixed.aplayer-narrow .aplayer-body {
        border: 2px solid rgba(201, 23, 30, 0.8) !important; 
        border-radius: 50% !important;
        background: #0a0a0c !important;
        box-shadow: 0 0 15px rgba(201, 23, 30, 0.4) !important;
    }

    .aplayer * { color: #e0e0e0 !important; }
    .aplayer .aplayer-list { background: #121214 !important; max-height: 250px !important; }
</style>

<meting-js
    id="624730068"
    server="netease"
    type="playlist"
    fixed="true"
    list-folded="true"
    theme="#c9171e"
    order="list"
    loop="all"
    preload="auto">
</meting-js>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let playerInstance = null;

        // 【关键逻辑】等待 MetingJS 把 APlayer 实例化
        const checkPlayer = setInterval(() => {
            const mjs = document.querySelector('meting-js');
            if (mjs && mjs.aplayer) {
                playerInstance = mjs.aplayer;
                clearInterval(checkPlayer);
                console.log("ZuelGal Player: 核心驱动已就绪");
                
                // 每秒记忆一次进度
                playerInstance.on('timeupdate', () => {
                    localStorage.setItem('zuelgal_index', playerInstance.list.index);
                    localStorage.setItem('zuelgal_time', playerInstance.audio.currentTime);
                });
            }
        }, 500);

        // 绑定到 Age Gate 的 YES 按钮
        const btnYes = document.getElementById('btn-yes');
        if (btnYes) {
            btnYes.addEventListener('click', () => {
                if (!playerInstance) return;

                // 读取记忆
                const savedIndex = localStorage.getItem('zuelgal_index');
                const savedTime = localStorage.getItem('zuelgal_time');

                if (savedIndex !== null) {
                    playerInstance.list.switch(parseInt(savedIndex));
                }

                // 强制尝试播放
                setTimeout(() => {
                    if (savedTime) playerInstance.seek(parseFloat(savedTime));
                    playerInstance.play();
                }, 800);
            });
        }
    });
</script>