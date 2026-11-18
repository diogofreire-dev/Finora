// site/js/landing-laptop.js — portátil e telemóvel em 3D na página inicial.
// O portátil abre e mostra o painel; a seguir entra o telemóvel com uma fatura acabada de ler pelo QR.
// Modelo feito no Blender (design/finora-laptop.blend) e exportado para assets/3d/finora-laptop.glb.
// Os ecrãs do modelo vêm vazios: aqui recebem as capturas (claras ou escuras, em português ou inglês).
// Sem WebGL fica a imagem assets/3d/finora-laptop.webp.
import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';

const stage  = document.getElementById('laptopStage');
const canvas = document.getElementById('laptopCanvas');
const calm   = matchMedia('(prefers-reduced-motion: reduce)');

const LID_CLOSED = 1.535;   // tampa pousada sobre o teclado
const LID_OPEN   = -0.2;    // aberta, um pouco inclinada para trás
const LID_TIME   = 1.8;     // segundos a abrir a tampa
const PHONE_FROM = 1.25;    // o telemóvel começa a entrar quando a tampa está quase aberta
const PHONE_TIME = 1.0;
const TOTAL      = PHONE_FROM + PHONE_TIME + 0.4;

function hasWebGL() {
  try { return !!document.createElement('canvas').getContext('webgl2'); } catch (e) { return false; }
}
const ease  = (x) => x < 0.5 ? 4 * x * x * x : 1 - Math.pow(-2 * x + 2, 3) / 2;
const easeOut = (x) => 1 - Math.pow(1 - x, 3);
const clamp01 = (x) => Math.min(1, Math.max(0, x));

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
  const look = new THREE.Vector3(0.6, 0.72, 0);    // centro do conjunto portátil + telemóvel

  // ── Capturas dos ecrãs (claras ou escuras) ─────────────
  const loader = new THREE.TextureLoader();
  const themeNow = () => document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
  const files = { laptop: 'laptop-ecra', phone: 'telemovel-ecra' };
  // As capturas existem em português e em inglês: escolhe pela língua da página
  const langSfx = document.documentElement.lang.startsWith('en') ? '-en' : '';
  const cache = {};
  const loadTex = (which, theme) => {
    const k = which + theme;
    cache[k] ??= loader.loadAsync(`assets/3d/${files[which]}-${theme}${langSfx}.webp`).then((t) => {
      t.colorSpace = THREE.SRGBColorSpace;
      t.flipY = false;                   // as UVs do glTF já vêm com o eixo V invertido
      t.anisotropy = renderer.capabilities.getMaxAnisotropy();
      return t;
    });
    return cache[k];
  };

  const [gltf, texLaptop, texPhone] = await Promise.all([
    new GLTFLoader().loadAsync('assets/3d/finora-laptop.glb'),
    loadTex('laptop', themeNow()),
    loadTex('phone', themeNow()),
  ]);

  const model = gltf.scene;
  const holder = new THREE.Group();
  holder.add(model);
  scene.add(holder);
  const lid = model.getObjectByName('Tampa');
  const phone = model.getObjectByName('Telemovel');
  const phoneRest = { pos: phone.position.clone(), rotY: phone.rotation.y };

  // Os ecrãs não reagem à luz: mostram a imagem tal como é, e começam apagados
  const screenMat = (map) => new THREE.MeshBasicMaterial({ map, toneMapped: false, color: 0x000000 });
  const laptopScreen = screenMat(texLaptop);
  const phoneScreen = screenMat(texPhone);
  model.getObjectByName('Ecra').material = laptopScreen;
  model.getObjectByName('TelEcra').material = phoneScreen;

  // Trocar as imagens quando se muda o tema na página
  new MutationObserver(async () => {
    const th = themeNow();
    const [a, b] = await Promise.all([loadTex('laptop', th), loadTex('phone', th)]);
    laptopScreen.map = a; phoneScreen.map = b;
    laptopScreen.needsUpdate = phoneScreen.needsUpdate = true;
    draw();
  }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

  function resize() {
    const r = stage.getBoundingClientRect();
    renderer.setSize(r.width, r.height, false);
    camera.aspect = r.width / r.height;
    // Em palcos largos a altura manda; nos estreitos (telemóvel) aproxima até o conjunto encher a largura
    const tan = Math.tan(THREE.MathUtils.degToRad(camera.fov / 2));
    const dist = camera.aspect >= 1.45 ? 9.6 : Math.max(8, 2.85 / (tan * camera.aspect));
    camera.position.set(look.x, dist * 0.30, dist);
    camera.lookAt(look);
    camera.updateProjectionMatrix();
    draw();
  }

  // Estado: a animação corre uma vez, quando metade da secção está à vista
  const REST_Y = -0.32;
  let elapsed = -1;            // -1 = ainda não começou; depois, segundos desde o início
  const pointer = { x: 0, y: 0 }, tilt = { x: 0, y: 0 };
  let visible = false, running = false, t = 0;
  const clock = new THREE.Clock();

  function pose(s) {
    // Tampa
    const k = ease(clamp01(s / LID_TIME));
    lid.rotation.x = LID_CLOSED + (LID_OPEN - LID_CLOSED) * k;
    laptopScreen.color.setScalar(clamp01((s / LID_TIME - 0.55) / 0.45));
    holder.rotation.y = REST_Y - 0.35 * (1 - k) + tilt.y;
    holder.rotation.x = tilt.x;

    // Telemóvel: entra pela direita e de baixo, a rodar para a frente, e depois acende
    const p = clamp01((s - PHONE_FROM) / PHONE_TIME);
    const e = easeOut(p);
    phone.visible = p > 0;
    phone.position.set(phoneRest.pos.x + 1.4 * (1 - e), phoneRest.pos.y - 0.9 * (1 - e), phoneRest.pos.z + 0.6 * (1 - e));
    phone.rotation.y = phoneRest.rotY - 0.9 * (1 - e);
    // Flutua devagar depois de chegar (desligado com "reduzir movimento")
    if (p >= 1 && !calm.matches) phone.position.y += Math.sin(t * 1.2) * 0.035;
    phoneScreen.color.setScalar(clamp01((s - PHONE_FROM - PHONE_TIME * 0.5) / 0.45));
  }
  function draw() { renderer.render(scene, camera); }

  function frame() {
    if (!visible || document.hidden) { running = false; return; }
    running = true;
    const dt = Math.min(clock.getDelta(), 0.05);
    t += dt;
    if (elapsed >= 0 && elapsed < TOTAL) elapsed += dt;
    const done = elapsed >= TOTAL;
    // Segue o rato devagar (só depois de tudo aberto)
    const tx = done && !calm.matches ? pointer.y * 0.05 : 0;
    const ty = done && !calm.matches ? pointer.x * 0.12 : 0;
    tilt.x += (tx - tilt.x) * 0.06;
    tilt.y += (ty - tilt.y) * 0.06;
    pose(elapsed < 0 ? 0 : elapsed);
    draw();
    // Com "reduzir movimento", depois de aberto fica parado e não gasta bateria
    if (done && calm.matches) { running = false; return; }
    requestAnimationFrame(frame);
  }
  function wake() { if (!running && visible && !document.hidden) { clock.getDelta(); requestAnimationFrame(frame); } }

  stage.addEventListener('pointermove', (e) => {
    const r = stage.getBoundingClientRect();
    pointer.x = ((e.clientX - r.left) / r.width - 0.5) * 2;
    pointer.y = ((e.clientY - r.top) / r.height - 0.5) * 2;
  });
  stage.addEventListener('pointerleave', () => { pointer.x = pointer.y = 0; });

  new IntersectionObserver(([entry]) => {
    visible = entry.isIntersecting;
    if (entry.intersectionRatio >= 0.45 && elapsed < 0) elapsed = calm.matches ? TOTAL : 0;
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
