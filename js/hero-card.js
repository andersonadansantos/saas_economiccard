/* ==========================================================================
   Economic Card - Hero: Cartão 3D Three.js (versão aprimorada)
   Referência: https://github.com/img2threejs/img2threejs
   A imagem img/ativado.png é projetada na face frontal. O restante do cartão
   é procedural: borda com bevel, verso, faixa magnética, sombra e partículas.
   ========================================================================== */

import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';

const container = document.getElementById('hero-card-3d');
if (!container) throw new Error('Container #hero-card-3d não encontrado');

const W = container.clientWidth;
const H = container.clientHeight;
if (W === 0 || H === 0) throw new Error('Container sem dimensões');

const cardWidth = 2.6;
const cardHeight = cardWidth / 1.78;
const cornerRadius = 0.09;
const thickness = 0.05;
const bevelSize = 0.01;

const textureLoader = new THREE.TextureLoader();
const frontTexture = textureLoader.load('img/ativado.png');
frontTexture.colorSpace = THREE.SRGBColorSpace;
frontTexture.anisotropy = 8;

// ---------------------------------------------------------------- Cena
const scene = new THREE.Scene();

const camera = new THREE.PerspectiveCamera(32, W / H, 0.1, 100);
camera.position.set(0, 0.3, 5.2);

const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
renderer.setSize(W, H);
renderer.toneMapping = THREE.ACESFilmicToneMapping;
renderer.toneMappingExposure = 1.1;
renderer.outputColorSpace = THREE.SRGBColorSpace;
container.appendChild(renderer.domElement);

// ------------------------------------------------- Ambiente (reflexos)
const pmrem = new THREE.PMREMGenerator(renderer);
scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;

// ------------------------------------------------- Iluminação
const keyLight = new THREE.DirectionalLight(0xffffff, 2.4);
keyLight.position.set(3, 4, 5);
keyLight.castShadow = true;
keyLight.shadow.mapSize.set(2048, 2048);
keyLight.shadow.camera.left = -3;
keyLight.shadow.camera.right = 3;
keyLight.shadow.camera.top = 3;
keyLight.shadow.camera.bottom = -3;
keyLight.shadow.camera.near = 1;
keyLight.shadow.camera.far = 12;
keyLight.shadow.bias = -0.0005;
keyLight.shadow.radius = 6;
scene.add(keyLight);

// Piso que recebe a sombra do cartão
const shadowFloor = new THREE.Mesh(
    new THREE.PlaneGeometry(10, 8),
    new THREE.ShadowMaterial({ opacity: 0.34 })
);
shadowFloor.rotation.x = -Math.PI / 2;
shadowFloor.position.y = -0.72;
shadowFloor.receiveShadow = true;
scene.add(shadowFloor);

const fillLight = new THREE.DirectionalLight(0x8a3f98, 0.55);
fillLight.position.set(-3, -1, 2);
scene.add(fillLight);

const rimLight = new THREE.DirectionalLight(0xbaf55b, 0.45);
rimLight.position.set(-4, -2, -3);
scene.add(rimLight);

const warmLight = new THREE.PointLight(0xffdcc6, 0.6, 8);
warmLight.position.set(0, -1.5, 3.2);
scene.add(warmLight);

// ------------------------------------------------- Sombra suave (blob)
function radialTexture(inner, mid, outer) {
    const c = document.createElement('canvas');
    c.width = 256;
    c.height = 256;
    const ctx = c.getContext('2d');
    const g = ctx.createRadialGradient(128, 128, 8, 128, 128, 120);
    g.addColorStop(0, inner);
    g.addColorStop(0.55, mid);
    g.addColorStop(1, outer);
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, 256, 256);
    return new THREE.CanvasTexture(c);
}

const shadowBlob = new THREE.Mesh(
    new THREE.PlaneGeometry(3.6, 1.9),
    new THREE.MeshBasicMaterial({
        map: radialTexture('rgba(49,20,64,0.28)', 'rgba(49,20,64,0.1)', 'rgba(49,20,64,0)'),
        transparent: true,
        depthWrite: false,
        depthTest: false,
    })
);
shadowBlob.rotation.x = -Math.PI / 2;
shadowBlob.position.y = -0.72;
shadowBlob.renderOrder = -1;
scene.add(shadowBlob);

// ------------------------------------------------- Grupo do cartão
const cardGroup = new THREE.Group();

function roundedRectShape(w, h, r) {
    const shape = new THREE.Shape();
    const x = -w / 2;
    const y = -h / 2;
    shape.moveTo(x + r, y);
    shape.lineTo(x + w - r, y);
    shape.quadraticCurveTo(x + w, y, x + w, y + r);
    shape.lineTo(x + w, y + h - r);
    shape.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
    shape.lineTo(x + r, y + h);
    shape.quadraticCurveTo(x, y + h, x, y + h - r);
    shape.lineTo(x, y + r);
    shape.quadraticCurveTo(x, y, x + r, y);
    return shape;
}

const cardShape = roundedRectShape(cardWidth, cardHeight, cornerRadius);

// Corpo: extrusão com bevel (borda arredondada brilhante)
const body = new THREE.Mesh(
    new THREE.ExtrudeGeometry(cardShape, {
        depth: thickness,
        bevelEnabled: true,
        bevelThickness: 0.012,
        bevelSize: bevelSize,
        bevelSegments: 6,
        curveSegments: 48,
    }),
    new THREE.MeshPhysicalMaterial({
        color: 0xdad5d0,
        roughness: 0.22,
        metalness: 0.35,
        clearcoat: 0.6,
        clearcoatRoughness: 0.2,
        envMapIntensity: 1.1,
    })
);
body.geometry.computeBoundingBox();
body.geometry.translate(0, 0, -body.geometry.boundingBox.min.z - (body.geometry.boundingBox.getSize(new THREE.Vector3()).z) / 2);
body.geometry.computeBoundingBox();
body.castShadow = true;
body.receiveShadow = false;
cardGroup.add(body);

const bb = body.geometry.boundingBox;

// Frente: plano reto com a proporção exata da imagem (1.78) -> sem corte/distorção
const front = new THREE.Mesh(
    new THREE.PlaneGeometry(cardWidth, cardHeight),
    new THREE.MeshPhysicalMaterial({
        map: frontTexture,
        roughness: 0.32,
        metalness: 0.02,
        clearcoat: 1.0,
        clearcoatRoughness: 0.12,
        envMapIntensity: 1.25,
    })
);
front.position.z = bb.max.z + 0.004;
cardGroup.add(front);

// Verso
const back = new THREE.Mesh(
    new THREE.PlaneGeometry(cardWidth, cardHeight),
    new THREE.MeshPhysicalMaterial({
        color: 0xf2ece7,
        roughness: 0.5,
        metalness: 0.02,
        clearcoat: 0.4,
        clearcoatRoughness: 0.3,
    })
);
back.position.z = bb.min.z - 0.004;
back.rotation.y = Math.PI;
cardGroup.add(back);

// Faixa magnética (verso)
const magStrip = new THREE.Mesh(
    new THREE.PlaneGeometry(cardWidth * 0.82, 0.16),
    new THREE.MeshStandardMaterial({ color: 0x1e1e1e, roughness: 0.55, metalness: 0.15 })
);
magStrip.position.z = bb.min.z - 0.01;
magStrip.position.y = cardHeight * 0.31;
cardGroup.add(magStrip);

// Faixa de assinatura (verso)
const signBand = new THREE.Mesh(
    new THREE.PlaneGeometry(cardWidth * 0.82, 0.26),
    new THREE.MeshStandardMaterial({ color: 0xd2cbc4, roughness: 0.6 })
);
signBand.position.z = bb.min.z - 0.008;
signBand.position.y = cardHeight * 0.12;
cardGroup.add(signBand);

// CVC (verso)
const cvcLine = new THREE.Mesh(
    new THREE.PlaneGeometry(0.4, 0.045),
    new THREE.MeshStandardMaterial({ color: 0xa29a93, roughness: 0.5 })
);
cvcLine.position.z = bb.min.z - 0.012;
cvcLine.position.y = cardHeight * -0.1;
cvcLine.position.x = cardWidth * 0.34;
cardGroup.add(cvcLine);

scene.add(cardGroup);

// ------------------------------------------------- Partículas flutuantes
function makeParticles(color, count, size) {
    const positions = new Float32Array(count * 3);
    for (let i = 0; i < count; i++) {
        positions[i * 3] = (Math.random() - 0.5) * 6;
        positions[i * 3 + 1] = (Math.random() - 0.5) * 3.6;
        positions[i * 3 + 2] = (Math.random() - 0.5) * 3;
    }
    const geo = new THREE.BufferGeometry();
    geo.setAttribute('position', new THREE.BufferAttribute(positions, 3));
    const mat = new THREE.PointsMaterial({
        color,
        size,
        transparent: true,
        opacity: 0.75,
        depthWrite: false,
        map: radialTexture('rgba(255,255,255,1)', 'rgba(255,255,255,0.35)', 'rgba(255,255,255,0)'),
        blending: THREE.AdditiveBlending,
    });
    return new THREE.Points(geo, mat);
}

const particlesLilac = makeParticles(0xc58be0, 26, 0.045);
const particlesLime = makeParticles(0xbaf55b, 18, 0.035);
scene.add(particlesLilac, particlesLime);

// ------------------------------------------------- Controles de órbita
const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;
controls.dampingFactor = 0.07;
controls.enableZoom = false;
controls.enablePan = false;
controls.autoRotate = true;
controls.autoRotateSpeed = 1.6;
controls.minPolarAngle = Math.PI / 2 - 0.45;
controls.maxPolarAngle = Math.PI / 2 + 0.35;
controls.minAzimuthAngle = -1.0;
controls.maxAzimuthAngle = 1.0;
controls.target.set(0, 0, 0);

// ------------------------------------------------- Animação
const clock = new THREE.Clock();
let elapsed = 0;
let mouseX = 0;
let mouseY = 0;

container.addEventListener('pointermove', (e) => {
    const rect = container.getBoundingClientRect();
    mouseX = ((e.clientX - rect.left) / rect.width - 0.5) * 2;
    mouseY = ((e.clientY - rect.top) / rect.height - 0.5) * 2;
});

function animate() {
    requestAnimationFrame(animate);
    const delta = clock.getDelta();
    elapsed += delta;

    // Flutuação + inclinação em resposta ao mouse
    const bob = Math.sin(elapsed * 1.1) * 0.09;
    cardGroup.position.y = bob;
    cardGroup.rotation.y += (mouseX * 0.22 - cardGroup.rotation.y) * 0.05;
    cardGroup.rotation.x += (-mouseY * 0.14 - cardGroup.rotation.x) * 0.05;
    cardGroup.rotation.z = Math.sin(elapsed * 0.5) * 0.01;

    // Sombra acompanha a flutuação
    shadowBlob.scale.set(1 - bob * 0.5, 1, 1 - bob * 1.6);
    shadowBlob.material.opacity = 0.5 - bob * 0.6;

    // Partículas sobem suavemente
    const pl = particlesLilac.geometry.attributes.position;
    for (let i = 0; i < pl.count; i++) {
        pl.array[i * 3 + 1] += delta * 0.08;
        if (pl.array[i * 3 + 1] > 1.8) pl.array[i * 3 + 1] = -1.8;
    }
    pl.needsUpdate = true;

    controls.update();
    renderer.render(scene, camera);
}

animate();

// ------------------------------------------------- Responsivo
function resize() {
    const w = container.clientWidth;
    const h = container.clientHeight;
    renderer.setSize(w, h);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
}
window.addEventListener('resize', resize);