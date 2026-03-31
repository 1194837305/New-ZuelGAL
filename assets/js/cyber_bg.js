// === ZuelGal - Hero Level Background Shader Engine (v3.0) ===
(function() {
    const canvas = document.getElementById('hero-shader-canvas');
    if (!canvas) return;

    // 尝试开启 Antialias，提升边缘光滑度，符合 AE 美学
    const gl = canvas.getContext('webgl', { 
        failIfMajorPerformanceCaveat: true, 
        antialias: true, 
        alpha: true 
    }) || canvas.getContext('experimental-webgl');
    
    if (!gl) {
        console.warn("设备 WebGL 性能不足，已切换至静态模式。");
        return;
    }

    // 1. 顶点着色器
    const vsSource = `
        attribute vec2 position;
        void main() { gl_Position = vec4(position, 0.0, 1.0); }
    `;

    // 2. 🟢 片段着色器 (炸裂的特摄特效算法)
    const fsSource = `
        precision highp float;
        uniform float u_time;
        uniform vec2 u_resolution;
        uniform vec2 u_mouse;

        // 伪随机函数
        vec2 hash2d(vec2 p) {
            p = vec2(dot(p, vec2(127.1, 311.7)), dot(p, vec2(269.5, 183.3)));
            return -1.0 + 2.0 * fract(sin(p) * 43758.5453123);
        }

        // --- 🟢 核心美学算法：不规则能量碎块 (Voronoi) ---
        vec3 voronoiShards(vec2 p) {
            vec2 i = floor(p);
            vec2 f = fract(p);
            
            float minDist = 1.0;
            vec2 center;

            // 扫描九宫格，生成不规则质心
            for (int y = -1; y <= 1; y++) {
                for (int x = -1; x <= 1; x++) {
                    vec2 g = vec2(float(x), float(y));
                    // 这里加上时间动效，让碎块缓慢飘动
                    vec2 o = hash2d(i + g);
                    o = 0.5 + 0.5 * sin(u_time * 0.8 + o * 6.2831);
                    
                    vec2 r = g + o - f;
                    float d = dot(r, r); // 使用平方距离，计算更快，效果更尖锐

                    if (d < minDist) {
                        minDist = d;
                        center = o; // 记录最近碎块的“性格”
                    }
                }
            }
            // 返回最小距离、碎块ID、质心颜色ID
            return vec3(minDist, hash2d(i).x, hash2d(center).y);
        }

        void main() {
            // 归一化并居中坐标
            vec2 uv = gl_FragCoord.xy / u_resolution.xy;
            vec2 p = uv * 2.0 - 1.0;
            p.x *= u_resolution.x / u_resolution.y;

            // --- 🟢 鼠标交互：物理畸变“奇效” (AE Aesthetics) ---
            vec2 mouse = u_mouse * 2.0 - 1.0;
            mouse.x *= u_resolution.x / u_resolution.y;
            float mouseDist = length(p - mouse);
            
            // 剧烈的畸变强度 ( smoothstep 控制影响范围)
            float distortStr = smoothstep(0.7, 0.0, mouseDist) * 0.15; // 放大系数，让畸变更狠
            
            // 在鼠标周围产生红蓝色差 (Chromatic Aberration)
            // 分别采样 R、G、B 偏移量
            vec2 distortR = distortStr * (p - mouse);
            vec2 distortB = -distortStr * (p - mouse);

            // 基础底色 (极暗的褐)
            vec3 col = vec3(0.015, 0.005, 0.0);

            // --- 采样层：生成不规则碎块 (杂乱中的秩序) ---
            float shardGrid = 6.0; // 碎块密度
            vec3 shardR = voronoiShards(p * shardGrid + distortR); // 红色通道偏移采样
            vec3 shardB = voronoiShards(p * shardGrid + distortB); // 蓝色通道偏移采样
            
            // 提取碎块属性
            float dR = shardR.x; // R通道距离
            float dB = shardB.x; // B通道距离
            float hR = shardR.y; // R通道ID
            
            // --- 绿色通道 (不做偏移，作为畸变的对照基准) ---
            vec3 shardG = voronoiShards(p * shardGrid);
            float dG = shardG.x;

            // --- 🟢 视觉增强：色差与粒子爆闪 ---
            // 利用 dR, dG, dB 的差异产生色差效果
            float colorGlitch = smoothstep(0.1, 0.15, abs(dR - dB)) * smoothstep(0.5, 0.0, mouseDist);
            
            // 定义能量主题色
            vec3 crimson = vec3(0.9, 0.1, 0.15); // 主题赤红
            vec3 electricBlue = vec3(0.0, 0.8, 1.0); // 鼠标爆闪蓝
            
            // 渲染碎块主体 (带呼吸动效)
            float breathe = 0.6 + 0.4 * sin(u_time * 2.0 + hR * 6.28);
            col += crimson * smoothstep(0.1, 0.0, dG) * breathe * 0.5; // G通道作为主体

            // 渲染色差边缘 (鼠标畸变奇效的具体体现)
            col.r += crimson.r * smoothstep(0.08, 0.0, dR) * colorGlitch;
            col.b += electricBlue.b * smoothstep(0.08, 0.0, dB) * colorGlitch * 0.8;

            // 🟢 鼠标周围的“特摄爆点”
            float mouseSpot = smoothstep(0.12, 0.0, mouseDist);
            col += electricBlue * mouseSpot * (0.8 + 0.2 * sin(u_time * 20.0)); // 快速闪烁

            // 暗角压制 (Vignette)
            col *= smoothstep(1.8, 0.2, length(p));

            gl_FragColor = vec4(col, mix(0.1, 0.9, smoothstep(0.1,0.0,dG)));
        }
    `;

    // 编译与链接工具 (保持原样，无需修改)
    const program = (function() {
        const vs = gl.createShader(gl.VERTEX_SHADER);
        gl.shaderSource(vs, vsSource);
        gl.compileShader(vs);
        const fs = gl.createShader(gl.FRAGMENT_SHADER);
        gl.shaderSource(fs, fsSource);
        gl.compileShader(fs);
        const p = gl.createProgram();
        gl.attachShader(p, vs);
        gl.attachShader(p, fs);
        gl.linkProgram(p);
        gl.useProgram(p);
        gl.deleteShader(vs);
        gl.deleteShader(fs);
        return p;
    })();

    // 顶点数据
    const buffer = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1,-1, 1,-1, -1,1, -1,1, 1,-1, 1,1]), gl.STATIC_DRAW);
    const posAttr = gl.getAttribLocation(program, 'position');
    gl.enableVertexAttribArray(posAttr);
    gl.vertexAttribPointer(posAttr, 2, gl.FLOAT, false, 0, 0);

    const timeLoc = gl.getUniformLocation(program, 'u_time');
    const resLoc = gl.getUniformLocation(program, 'u_resolution');
    const mouseLoc = gl.getUniformLocation(program, 'u_mouse');

    // 核心新增：鼠标移动监听
    let mouseX = 0, mouseY = 0;
    window.addEventListener('mousemove', (e) => {
        mouseX = e.clientX;
        mouseY = canvas.height - e.clientY; // WebGL Y轴反转
    });

    function resize() {
        // 性能调优：手机端强制使用 1x 采样，电脑端使用高清采样
        const pixelRatio = window.innerWidth < 768 ? 1 : window.devicePixelRatio;
        canvas.width = window.innerWidth * pixelRatio;
        canvas.height = window.innerHeight * pixelRatio;
        gl.viewport(0, 0, canvas.width, canvas.height);
        gl.uniform2f(resLoc, canvas.width, canvas.height);
    }
    window.addEventListener('resize', resize);
    resize();

    let startTime = Date.now();
    function render() {
        const elapsed = (Date.now() - startTime) / 1000.0;
        gl.uniform1f(timeLoc, elapsed);
        // 🟢 将鼠标位置传给 GPU，开启奇效
        gl.uniform2f(mouseLoc, mouseX, mouseY); 
        gl.drawArrays(gl.TRIANGLES, 0, 6);
        requestAnimationFrame(render);
    }
    render();
})();