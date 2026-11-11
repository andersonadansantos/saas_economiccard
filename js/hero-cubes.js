/* ==========================================================================
   Economic Card - Hero 3D Cubes
   Renderiza os cubos de porcentagem com acabamento premium via Three.js.
   Os cubos 4 e 5 (profundidade) usam camadas próprias com blur intenso.
   Se o WebGL/Three.js falhar, os cubos em CSS permanecem como fallback.
   ========================================================================== */

(function () {
    'use strict';

    const section = document.querySelector('main > section');
    if (!section) return;

    const markers = Array.from(section.querySelectorAll('.hero-cube'));
    if (markers.length !== 5) return;

    const DEPTH_CLASS = 'hero-cube--4';
    const FAR_CLASS = 'hero-cube--5';
    const depthMarkers = markers.filter(function (el) { return el.classList.contains(DEPTH_CLASS); });
    const farMarkers = markers.filter(function (el) { return el.classList.contains(FAR_CLASS); });
    const mainMarkers = markers.filter(function (el) {
        return !el.classList.contains(DEPTH_CLASS) && !el.classList.contains(FAR_CLASS);
    });
    if (!mainMarkers.length || !depthMarkers.length || !farMarkers.length) return;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const debugBuffer = new URLSearchParams(window.location.search).has('debugcubes');

    // Movimento de cada cubo (mantém os valores originais dos cubos 1, 2 e 3).
    const MOTION = {
        'hero-cube--1': { amp: 15, speed: 0.36, tilt: 0.23, phase: 0.4, rx: -0.3, ry: 0.62, rz: 0.06 },
        'hero-cube--2': { amp: 15, speed: 0.46, tilt: 0.29, phase: 3.6, rx: -0.34, ry: 0.55, rz: 0.03 },
        'hero-cube--3': { amp: 9, speed: 0.55, tilt: 0.42, phase: 1.9, rx: -0.18, ry: -0.5, rz: -0.05 },
        // Cubos de profundidade: levitação quase imperceptível.
        'hero-cube--4': { amp: 4, speed: 0.14, tilt: 0.08, phase: 5.1, rx: -0.12, ry: -0.38, rz: 0.02, motionScale: 0.6 },
        'hero-cube--5': { amp: 3, speed: 0.11, tilt: 0.06, phase: 2.7, rx: -0.2, ry: 0.36, rz: 0.04, motionScale: 0.5 }
    };

    function motionFor(el) {
        for (const key in MOTION) {
            if (el.classList.contains(key)) return MOTION[key];
        }
        return MOTION['hero-cube--1'];
    }

    function hasWebGL() {
        try {
            const probe = document.createElement('canvas');
            return !!(window.WebGLRenderingContext && (probe.getContext('webgl2') || probe.getContext('webgl')));
        } catch (error) {
            return false;
        }
    }

    if (!hasWebGL()) return;

    Promise.all([
        import('three'),
        import('three/addons/geometries/RoundedBoxGeometry.js'),
        import('three/addons/environments/RoomEnvironment.js')
    ]).then(function (modules) {
        init(modules[0], modules[1].RoundedBoxGeometry, modules[2].RoomEnvironment);
    }).catch(function () {
        /* Mantém o fallback em CSS. */
    });

    function createFaceTexture(THREE, renderer) {
        const size = 512;
        const face = document.createElement('canvas');
        face.width = size;
        face.height = size;

        const ctx = face.getContext('2d');

        // Base roxa com leve gradiente vertical (material premium).
        const base = ctx.createLinearGradient(0, 0, size * 0.18, size);
        base.addColorStop(0, '#bb67c4');
        base.addColorStop(0.45, '#9d47a6');
        base.addColorStop(1, '#7e2e88');
        ctx.fillStyle = base;
        ctx.fillRect(0, 0, size, size);

        // Brilho suave no canto superior esquerdo (acabamento acetinado).
        const sheen = ctx.createRadialGradient(
            size * 0.3, size * 0.14, size * 0.02,
            size * 0.3, size * 0.14, size * 0.95
        );
        sheen.addColorStop(0, 'rgba(255, 255, 255, 0.3)');
        sheen.addColorStop(0.38, 'rgba(255, 255, 255, 0.08)');
        sheen.addColorStop(1, 'rgba(255, 255, 255, 0)');
        ctx.fillStyle = sheen;
        ctx.fillRect(0, 0, size, size);

        // Símbolo de porcentagem branco.
        ctx.font = '800 ' + Math.round(size * 0.56) + 'px "Inter", "Helvetica Neue", Arial, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillStyle = '#ffffff';
        ctx.shadowColor = 'rgba(40, 5, 47, 0.5)';
        ctx.shadowBlur = size * 0.035;
        ctx.shadowOffsetY = size * 0.015;
        ctx.fillText('%', size / 2, size * 0.53);
        ctx.shadowColor = 'transparent';
        ctx.shadowBlur = 0;
        ctx.shadowOffsetY = 0;

        // Vinheta discreta nas bordas para dar profundidade.
        const vignette = ctx.createRadialGradient(
            size / 2, size / 2, size * 0.3,
            size / 2, size / 2, size * 0.78
        );
        vignette.addColorStop(0, 'rgba(28, 3, 34, 0)');
        vignette.addColorStop(1, 'rgba(28, 3, 34, 0.18)');
        ctx.fillStyle = vignette;
        ctx.fillRect(0, 0, size, size);

        const texture = new THREE.CanvasTexture(face);
        texture.colorSpace = THREE.SRGBColorSpace;
        texture.anisotropy = renderer.capabilities.getMaxAnisotropy();
        return texture;
    }

    function init(THREE, RoundedBoxGeometry, RoomEnvironment) {
        const layer = section.querySelector('.hero-cubes') || section;

        function createStage(config) {
            const renderer = new THREE.WebGLRenderer({
                alpha: true,
                antialias: true,
                powerPreference: 'high-performance',
                preserveDrawingBuffer: debugBuffer
            });
            renderer.setClearColor(0x000000, 0);
            renderer.setPixelRatio(config.pixelRatio);
            renderer.outputColorSpace = THREE.SRGBColorSpace;
            renderer.toneMapping = THREE.ACESFilmicToneMapping;
            renderer.toneMappingExposure = 1.0;
            renderer.autoClear = false;

            const canvas = renderer.domElement;
            canvas.className = config.canvasClass;
            canvas.setAttribute('aria-hidden', 'true');
            layer.insertBefore(canvas, layer.firstChild);

            const scene = new THREE.Scene();
            const camera = new THREE.PerspectiveCamera(26, 1, 1, 100000);

            // Ambiente de estúdio para reflexos sutis (PMREM).
            if (config.environment !== false) {
                const pmrem = new THREE.PMREMGenerator(renderer);
                const envTarget = pmrem.fromScene(new RoomEnvironment(), 0.04);
                scene.environment = envTarget.texture;
                pmrem.dispose();
            }

            scene.add(new THREE.HemisphereLight(0xffffff, 0x4a1054, 0.5));

            const keyLight = new THREE.DirectionalLight(0xffffff, 1.9);
            keyLight.position.set(-0.65, 1, 0.9);
            scene.add(keyLight);

            const rimLight = new THREE.DirectionalLight(0xe9d2ff, 1.2);
            rimLight.position.set(0.95, -0.2, 0.5);
            scene.add(rimLight);

            const fillLight = new THREE.DirectionalLight(0xffffff, 0.5);
            fillLight.position.set(0.75, 0.65, 0.35);
            scene.add(fillLight);

            const frontFill = new THREE.DirectionalLight(0xffffff, 0.35);
            frontFill.position.set(0, -0.3, 1);
            scene.add(frontFill);

            const texture = createFaceTexture(THREE, renderer);

            const geometry = new RoundedBoxGeometry(1, 1, 1, 6, 0.13);
            const material = new THREE.MeshPhysicalMaterial({
                map: texture,
                color: 0xffffff,
                roughness: 0.32,
                metalness: 0.0,
                clearcoat: 1.0,
                clearcoatRoughness: 0.18,
                emissive: 0x320a3a,
                emissiveIntensity: 0.35,
                envMapIntensity: 0.8
            });

            const cubes = config.markers.map(function (el) {
                const mesh = new THREE.Mesh(geometry, material);
                scene.add(mesh);
                return {
                    el: el,
                    motion: motionFor(el),
                    mesh: mesh,
                    baseX: 0,
                    baseY: 0,
                    side: 1
                };
            });

            let viewW = 0;
            let viewH = 0;
            let canvasLeft = 0;
            let canvasTop = 0;

            function resize() {
                const w = Math.max(1, section.clientWidth);
                const h = Math.max(1, section.clientHeight);
                if (w === viewW && h === viewH) return;

                viewW = w;
                viewH = h;
                renderer.setSize(w, h, false);

                camera.aspect = w / h;
                const halfFov = (camera.fov * Math.PI) / 360;
                camera.position.set(0, 0, (h / 2) / Math.tan(halfFov));
                camera.updateProjectionMatrix();
            }

            function measure() {
                const rect = canvas.getBoundingClientRect();
                canvasLeft = rect.left;
                canvasTop = rect.top;

                cubes.forEach(function (cube) {
                    const r = cube.el.getBoundingClientRect();
                    cube.side = Math.max(24, r.width);
                    cube.baseX = r.left - canvasLeft + r.width / 2 - viewW / 2;
                    cube.baseY = viewH / 2 - (r.top - canvasTop + r.height / 2);
                    cube.mesh.scale.setScalar(cube.side);
                });
            }

            function draw(now) {
                resize();
                measure();

                const t = now / 1000;
                const motion = reduceMotion.matches ? 0.35 : 1;

                cubes.forEach(function (cube) {
                    const m = cube.motion;
                    const scale = (m.motionScale || 1) * motion;
                    const floatY = Math.sin(t * m.speed + m.phase) * m.amp * scale;
                    cube.mesh.position.set(cube.baseX, cube.baseY + floatY, 0);
                    cube.mesh.rotation.set(
                        m.rx + Math.sin(t * m.tilt + m.phase) * 0.09 * scale,
                        m.ry + Math.cos(t * m.tilt * 0.8 + m.phase) * 0.14 * scale,
                        m.rz + Math.sin(t * m.tilt * 0.6 + m.phase) * 0.05 * scale
                    );
                });

                renderer.setScissorTest(false);
                renderer.clear();
                renderer.setScissorTest(true);

                cubes.forEach(function (cube) {
                    const size = cube.side * 1.75;
                    const centerX = cube.baseX + viewW / 2;
                    const centerY = viewH / 2 - cube.baseY;
                    const x0 = Math.max(0, Math.floor(centerX - size / 2));
                    const x1 = Math.min(viewW, Math.ceil(centerX + size / 2));
                    const y0 = Math.max(0, Math.floor(centerY - size / 2));
                    const y1 = Math.min(viewH, Math.ceil(centerY + size / 2));

                    if (x1 <= x0 || y1 <= y0) return;

                    renderer.setScissor(x0, viewH - y1, x1 - x0, y1 - y0);
                    renderer.render(scene, camera);
                });

                renderer.setScissorTest(false);
            }

            return { draw: draw, resize: resize, measure: measure };
        }

        const pixelRatio = Math.min(window.devicePixelRatio || 1, 2);

        const stages = [
            {
                stage: createStage({
                    markers: mainMarkers,
                    canvasClass: 'hero-cubes-canvas',
                    pixelRatio: pixelRatio
                }),
                minInterval: 0
            },
            {
                stage: createStage({
                    markers: depthMarkers,
                    canvasClass: 'hero-cubes-canvas hero-cubes-canvas--depth',
                    pixelRatio: Math.min(pixelRatio, 1)
                }),
                minInterval: 66
            },
            {
                stage: createStage({
                    markers: farMarkers,
                    canvasClass: 'hero-cubes-canvas hero-cubes-canvas--far',
                    pixelRatio: 0.85
                }),
                minInterval: 100
            }
        ];

        const lastDraw = stages.map(function () { return 0; });

        function drawAll(now) {
            stages.forEach(function (item, index) {
                // Camadas desfocadas são quase estáticas: desenham em taxa menor.
                if (item.minInterval && now - lastDraw[index] < item.minInterval) return;
                item.stage.draw(now);
                lastDraw[index] = now;
            });
        }

        function syncAll() {
            stages.forEach(function (item) {
                item.stage.resize();
                item.stage.measure();
            });
        }

        function loop(now) {
            drawAll(now);
            window.requestAnimationFrame(loop);
        }

        syncAll();
        drawAll(performance.now());
        section.classList.add('hero--3d');
        window.requestAnimationFrame(loop);

        if (debugBuffer) {
            // Hook de depuração (apenas com ?debugcubes=1) para validar o render.
            window.__heroCubes = {
                draw: function () {
                    syncAll();
                    stages.forEach(function (item) {
                        item.stage.draw(performance.now());
                    });
                }
            };
        }

        if ('ResizeObserver' in window) {
            new ResizeObserver(syncAll).observe(section);
        } else {
            window.addEventListener('resize', function () {
                stages.forEach(function (item) { item.stage.resize(); });
            });
        }
    }
})();
