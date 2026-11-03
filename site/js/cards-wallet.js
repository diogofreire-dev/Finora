// site/js/cards-wallet.js — carteira 3D com os cartões ativos do utilizador (página Cartões).
// Modelo: design/finora-wallet.blend → assets/3d/finora-wallet.glb (carteira + um cartão-modelo sem cor).
// Cada cartão é uma cópia do modelo pintada com a cor do banco; o nome é desenhado numa textura.
import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';

const stage  = document.getElementById('walletStage');
const canvas = document.getElementById('walletCanvas');
const dataEl = document.getElementById('walletData');
const calm   = matchMedia('(prefers-reduced-motion: reduce)');

// Medidas do cartão (as mesmas do Blender)
const CARD_W = 8.56, CARD_H = 5.398, CARD_T = 0.09;
// Blender (x, y, z) → glTF/three (x, z, -y)
const b2t = (x, y, z) => new THREE.Vector3(x, z, -y);

function hasWebGL() {
  try { return !!document.createElement('canvas').getContext('webgl2'); } catch (e) { return false; }
}

// Texto claro ou escuro conforme a cor do cartão
function inkFor(hex) {
  const c = new THREE.Color(hex);
  const lum = 0.2126 * c.r + 0.7152 * c.g + 0.0722 * c.b;   // c já está em espaço linear
  return lum > 0.35 ? '#191c1f' : '#f3e7c8';
}

// Faixa de texto no topo visível do cartão: nome à esquerda, banco à direita.
// fillText só desenha texto — um nome com HTML aparece tal e qual, nunca é interpretado.
function labelTexture(card, ink) {
  const W = 1024, H = 96;
  const cv = document.createElement('canvas'); cv.width = W; cv.height = H;
  const g = cv.getContext('2d');
  g.fillStyle = ink; g.textBaseline = 'middle';
  g.font = '600 50px Figtree, system-ui, sans-serif';
  g.fillText(card.name, 8, H / 2, card.bank ? W * 0.68 : W - 16);
  if (card.bank) {
    g.globalAlpha = 0.72; g.textAlign = 'right';
    g.font = '700 40px Figtree, system-ui, sans-serif';
    g.fillText(card.bank, W - 8, H / 2, W * 0.28);
  }
  const tex = new THREE.CanvasTexture(cv);
  tex.colorSpace = THREE.SRGBColorSpace; tex.anisotropy = 4;
  return { tex, aspect: H / W };
}

async function start() {
  const cards = JSON.parse(dataEl?.textContent || '[]');
  if (!stage || !cards.length || !hasWebGL()) return;
  stage.classList.add('is-on');
  try { await document.fonts.load('600 50px Figtree'); } catch (e) {}

  const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true, powerPreference: 'low-power' });
  renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 0.95;

  const scene = new THREE.Scene();
  const pmrem = new THREE.PMREMGenerator(renderer);
  scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
  // Ambiente mais fraco: a sala branca refletida "lavava" as cores dos bancos
  scene.environmentIntensity = 0.45;
  const key = new THREE.DirectionalLight(0xffffff, 1.6);
  key.position.set(-5, 8, 10); scene.add(key);

  const camera = new THREE.PerspectiveCamera(30, 1, 0.1, 100);

  const gltf = await new GLTFLoader().loadAsync('../assets/3d/finora-wallet.glb');
  const walletRoot = gltf.scene.getObjectByName('WalletRoot') || gltf.scene;
  const template = gltf.scene.getObjectByName('Card_Finora');
  template.parent.remove(template);

  // A carteira foi modelada deitada; fica de pé, virada para a câmara
  const holder = new THREE.Group();
  const upright = new THREE.Group(); upright.rotation.x = Math.PI / 2;
  upright.add(gltf.scene); holder.add(upright); scene.add(holder);

  // Cartões em escada: o primeiro da lista fica à frente (mais visível)
  const n = cards.length, tilt = [-1.5, 1.2, -0.8, 1.4, -1.0];
  const picks = [];   // malhas que respondem ao rato
  const slots = cards.map((card, k) => {
    const i = n - 1 - k;                         // 0 = mais atrás
    const obj = template.clone(true);
    obj.traverse((m) => {
      if (m.isMesh && m.material && m.material.name === 'CardBody') {
        m.material = m.material.clone();
        m.material.color.set(card.color);        // a cor vem em sRGB; o three converte
        m.material.metalness = 0.08;             // plástico acetinado, para a cor do banco ler-se bem
        m.material.roughness = 0.38;
      }
    });
    const ink = inkFor(card.color);
    const { tex, aspect } = labelTexture(card, ink);
    const lw = CARD_W - 0.9;
    const label = new THREE.Mesh(new THREE.PlaneGeometry(lw, lw * aspect),
                                 new THREE.MeshBasicMaterial({ map: tex, transparent: true, toneMapped: false }));
    label.rotation.x = -Math.PI / 2;            // a face do cartão aponta para +Y no glTF
    label.position.copy(b2t(0, CARD_H / 2 - 0.42, CARD_T / 2 + 0.003));
    obj.add(label);

    const base = { x: -0.1 + i * 0.06, y: -0.5 + (n - 1 - i) * 0.75, z: 0.16 + i * 0.08 };
    obj.position.copy(b2t(base.x, base.y, base.z));
    obj.rotation.y = THREE.MathUtils.degToRad(tilt[i % tilt.length]);
    walletRoot.add(obj);
    obj.traverse((m) => { if (m.isMesh) { m.userData.slot = k; picks.push(m); } });
    return { card, obj, base, lift: 0 };
  });

  // Enquadramento: da base da carteira ao topo do cartão mais alto
  const topY = -0.5 + (n - 1) * 0.75 + CARD_H / 2;
  const midY = (topY + -3.3) / 2, spanY = topY + 3.3;
  holder.position.y = -midY * 0.92;
  const REST = { x: -0.28, y: -0.22 };
  const state = { x: REST.x, y: REST.y, dragging: false, moved: 0, lastX: 0, lastY: 0, hover: -1 };

  function resize() {
    const r = stage.getBoundingClientRect();
    renderer.setSize(r.width, r.height, false);
    camera.aspect = r.width / r.height;
    const needH = spanY * 1.04 + 1.5, needW = 10.8;
    const fov = THREE.MathUtils.degToRad(camera.fov);
    const dH = needH / 2 / Math.tan(fov / 2);
    const dW = needW / 2 / Math.tan(fov / 2) / camera.aspect;
    camera.position.set(0, 0, Math.max(dH, dW));
    camera.updateProjectionMatrix();
  }
  new ResizeObserver(resize).observe(stage);
  resize();

  // ── Rato / dedo ───────────────────────────────────────
  const ray = new THREE.Raycaster(), ndc = new THREE.Vector2();
  function hit(e) {
    const r = canvas.getBoundingClientRect();
    ndc.set(((e.clientX - r.left) / r.width) * 2 - 1, -((e.clientY - r.top) / r.height) * 2 + 1);
    ray.setFromCamera(ndc, camera);
    const h = ray.intersectObjects(picks, false)[0];
    return h ? h.object.userData.slot : -1;
  }
  function pick(k) {
    const el = document.getElementById('card-' + cards[k].id);
    if (!el) return;
    el.scrollIntoView({ behavior: calm.matches ? 'auto' : 'smooth', block: 'center' });
    el.focus({ preventScroll: true });
    el.classList.add('is-picked');
    setTimeout(() => el.classList.remove('is-picked'), 1600);
  }
  canvas.addEventListener('pointerdown', (e) => {
    state.dragging = true; state.moved = 0; state.lastX = e.clientX; state.lastY = e.clientY;
    canvas.setPointerCapture(e.pointerId);
  });
  canvas.addEventListener('pointermove', (e) => {
    if (state.dragging) {
      const dx = e.clientX - state.lastX, dy = e.clientY - state.lastY;
      state.lastX = e.clientX; state.lastY = e.clientY; state.moved += Math.abs(dx) + Math.abs(dy);
      state.y = THREE.MathUtils.clamp(state.y + dx * 0.006, REST.y - 0.6, REST.y + 0.6);
      state.x = THREE.MathUtils.clamp(state.x + dy * 0.004, REST.x - 0.25, REST.x + 0.25);
    }
    state.hover = e.pointerType === 'mouse' ? hit(e) : -1;
    canvas.classList.toggle('is-pointing', state.hover >= 0);
  });
  canvas.addEventListener('pointerup', (e) => {
    state.dragging = false;
    if (state.moved < 8) { const k = hit(e); if (k >= 0) pick(k); }   // toque curto = escolher
  });
  canvas.addEventListener('pointercancel', () => { state.dragging = false; });
  canvas.addEventListener('pointerleave', () => { state.hover = -1; canvas.classList.remove('is-pointing'); });

  // ── Animação ───────────────────────────────────────────
  let visible = true, running = false, t = 0;
  const clock = new THREE.Clock();
  function frame() {
    if (!visible || document.hidden) { running = false; return; }
    running = true;
    const dt = Math.min(clock.getDelta(), 0.05); t += dt;
    if (!state.dragging) {                       // volta devagar à posição de repouso
      state.x += (REST.x - state.x) * 0.04; state.y += (REST.y - state.y) * 0.04;
    }
    const sway = calm.matches ? 0 : Math.sin(t * 0.7) * 0.04;
    holder.rotation.set(state.x, state.y + sway, 0);
    // O cartão por baixo do rato sobe para fora do bolso
    slots.forEach((s, k) => {
      s.lift += ((state.hover === k ? 1.5 : 0) - s.lift) * (calm.matches ? 1 : 0.15);
      s.obj.position.copy(b2t(s.base.x, s.base.y + s.lift, s.base.z));
    });
    renderer.render(scene, camera);
    requestAnimationFrame(frame);
  }
  function wake() { if (!running && visible && !document.hidden) { clock.getDelta(); requestAnimationFrame(frame); } }
  new IntersectionObserver(([en]) => { visible = en.isIntersecting; wake(); }).observe(stage);
  document.addEventListener('visibilitychange', wake);

  renderer.render(scene, camera);
  stage.classList.add('is-live');
  wake();
}

const go = () => start().catch((err) => {
  console.warn('[finora 3D] carteira indisponível:', err);
  stage?.classList.remove('is-on');           // sem 3D, fica só a lista
});
if (document.readyState === 'complete') go(); else addEventListener('load', go, { once: true });
