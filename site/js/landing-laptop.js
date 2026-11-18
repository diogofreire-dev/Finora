// site/js/landing-laptop.js — portátil em 3D na página inicial que abre e mostra o painel da Finora.
// Modelo feito no Blender (design/finora-laptop.blend) e exportado para assets/3d/finora-laptop.glb.
// O ecrã do modelo vem vazio: aqui recebe a captura do painel (clara ou escura, conforme o tema).
// Sem WebGL fica a imagem assets/3d/finora-laptop.webp.
import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';

const stage  = document.getElementById('laptopStage');
const canvas = document.getElementById('laptopCanvas');
const calm   = matchMedia('(prefers-reduced-motion: reduce)');

const LID_CLOSED = 1.535;   // tampa pousada sobre o teclado
const LID_OPEN   = -0.2;    // aberta, um pouco inclinada para trás
const OPEN_TIME  = 1.8;     // segundos

function hasWebGL() {
  try { return !!document.createElement('canvas').getContext('webgl2'); } catch (e) { return false; }
}
const ease = (x) => x < 0.5 ? 4 * x * x * x : 1 - Math.pow(-2 * x + 2, 3) / 2;

async function start() {
  if (!stage || !hasWebGL()) return;
  stage.classList.add('is-3d');

  const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true, powerPreference: 'low-power' });
  renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.0;

  const scene = new THREE.Scene();
  const pmrem = new THREE.PMREMGenerator(renderer);
  scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
  const key = new THREE.DirectionalLight(0xffffff, 1.4);
  key.position.set(-5, 8, 6);
  scene.add(key);
  const rim = new THREE.DirectionalLight(0xffe2b0, 0.8);
  rim.position.set(6, 3, -4);
  scene.add(rim);

  const camera = new THREE.PerspectiveCamera(26, 1, 0.1, 100);
  const look = new THREE.Vector3(0.12, 0.72, 0);

  const loader = new THREE.TextureLoader();
  const loadTex = (theme) => loader.loadAsync(`assets/3d/laptop-ecra-${theme}.webp`).then((t) => {
    t.colorSpace = THREE.SRGBColorSpace;
    t.flipY = false;                     // as UVs do glTF já vêm com o eixo V invertido
    t.anisotropy = renderer.capabilities.getMaxAnisotropy();
    return t;
  });
  const themeNow = () => document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
  const textures = {};
  const [gltf] = await Promise.all([
    new GLTFLoader().loadAsync('assets/3d/finora-laptop.glb'),
    loadTex(themeNow()).then((t) => { textures[themeNow()] = t; }),
  ]);

  const laptop = gltf.scene;
  const holder = new THREE.Group();
  holder.add(laptop);
  scene.add(holder);
  const lid = laptop.getObjectByName('Tampa');
  const screen = laptop.getObjectByName('Ecra');
  // O ecrã não reage à luz: mostra a imagem tal como é, e começa apagado
  const screenMat = new THREE.MeshBasicMaterial({ map: textures[themeNow()], toneMapped: false, color: 0x000000 });
  screen.material = screenMat;

  // Trocar a imagem do ecrã quando se muda o tema na página
  new MutationObserver(async () => {
    const th = themeNow();
    if (!textures[th]) textures[th] = await loadTex(th);
    screenMat.map = textures[th];
    screenMat.needsUpdate = true;
    draw();
  }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

  function resize() {
    const r = stage.getBoundingClientRect();
    renderer.setSize(r.width, r.height, false);
    camera.aspect = r.width / r.height;
    // Em palcos largos a altura manda; nos estreitos (telemóvel) aproxima até o portátil encher a largura
    const tan = Math.tan(THREE.MathUtils.degToRad(camera.fov / 2));
    const dist = camera.aspect >= 1.45 ? 8.8 : Math.max(7.2, 2.35 / (tan * camera.aspect));
    camera.position.set(look.x, dist * 0.30, dist);
    camera.lookAt(look);
    camera.updateProjectionMatrix();
    draw();
  }

  // Estado: a tampa abre uma vez, quando metade da secção está à vista
  const REST_Y = -0.32;
  let opening = -1;            // -1 = ainda fechado; depois, segundos desde que começou a abrir
  let pointer = { x: 0, y: 0 }, tilt = { x: 0, y: 0 };
  let visible = false, running = false;
  const clock = new THREE.Clock();

  function pose(p) {
    const k = ease(Math.min(1, Math.max(0, p)));
    lid.rotation.x = LID_CLOSED + (LID_OPEN - LID_CLOSED) * k;
    // O ecrã acende já com a tampa quase aberta
    const glow = Math.min(1, Math.max(0, (p - 0.55) / 0.45));
    screenMat.color.setScalar(glow);
    // Enquanto abre, o portátil roda um pouco para ficar de frente
    holder.rotation.y = REST_Y - 0.35 * (1 - k) + tilt.y;
    holder.rotation.x = tilt.x;
  }
  function draw() { renderer.render(scene, camera); }

  function frame() {
    if (!visible || document.hidden) { running = false; return; }
    running = true;
    const dt = Math.min(clock.getDelta(), 0.05);
    if (opening >= 0 && opening < OPEN_TIME) opening += dt;
    // Segue o rato devagar (só depois de aberto)
    const done = opening >= OPEN_TIME;
    const tx = done && !calm.matches ? pointer.y * 0.05 : 0;
    const ty = done && !calm.matches ? pointer.x * 0.12 : 0;
    tilt.x += (tx - tilt.x) * 0.06;
    tilt.y += (ty - tilt.y) * 0.06;
    pose(opening < 0 ? 0 : opening / OPEN_TIME);
    draw();
    const settled = done && Math.abs(tx - tilt.x) < 1e-4 && Math.abs(ty - tilt.y) < 1e-4;
    if (settled) { running = false; return; }   // parado: não gasta bateria
    requestAnimationFrame(frame);
  }
  function wake() { if (!running && visible && !document.hidden) { clock.getDelta(); requestAnimationFrame(frame); } }

  stage.addEventListener('pointermove', (e) => {
    const r = stage.getBoundingClientRect();
    pointer.x = ((e.clientX - r.left) / r.width - 0.5) * 2;
    pointer.y = ((e.clientY - r.top) / r.height - 0.5) * 2;
    wake();
  });
  stage.addEventListener('pointerleave', () => { pointer.x = pointer.y = 0; wake(); });

  new IntersectionObserver(([entry]) => {
    visible = entry.isIntersecting;
    if (entry.intersectionRatio >= 0.45 && opening < 0) opening = calm.matches ? OPEN_TIME : 0;
    wake();
  }, { threshold: [0, 0.45] }).observe(stage);
  document.addEventListener('visibilitychange', wake);

  new ResizeObserver(resize).observe(stage);
  resize();
  pose(0);
  draw();
  stage.classList.add('is-live');
}

// Só depois de a página estar pronta, para o 3D não atrasar o resto
const go = () => start().catch((err) => {
  console.warn('[finora 3D] fica a imagem:', err);
  stage?.classList.remove('is-3d');
});
if (document.readyState === 'complete') go(); else addEventListener('load', go, { once: true });
