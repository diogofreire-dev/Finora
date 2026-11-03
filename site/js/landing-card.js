// site/js/landing-card.js — cartão Finora em 3D na página inicial.
// Modelo feito no Blender (design/finora-card.blend) e exportado para assets/3d/finora-card.glb.
// Enquanto carrega (ou se não houver WebGL) fica visível a imagem assets/3d/finora-card.webp.
import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';

const stage  = document.getElementById('cardStage');
const canvas = document.getElementById('cardCanvas');
const calm   = matchMedia('(prefers-reduced-motion: reduce)');

function hasWebGL() {
  try { return !!document.createElement('canvas').getContext('webgl2'); } catch (e) { return false; }
}

async function start() {
  if (!stage || !hasWebGL()) return;

  const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true, powerPreference: 'low-power' });
  renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.05;

  const scene = new THREE.Scene();
  const pmrem = new THREE.PMREMGenerator(renderer);
  scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;

  const key = new THREE.DirectionalLight(0xffffff, 1.6);
  key.position.set(-4, 6, 8);
  scene.add(key);

  const camera = new THREE.PerspectiveCamera(28, 1, 0.1, 100);
  camera.position.set(0, 0, 16.5);

  // O cartão foi modelado deitado (frente para cima). Fica de pé, virado para a câmara.
  const gltf = await new GLTFLoader().loadAsync('assets/3d/finora-card.glb');
  const card = gltf.scene;
  card.rotation.x = Math.PI / 2;
  const holder = new THREE.Group();   // rotação controlada pelo utilizador
  holder.add(card);
  scene.add(holder);

  // Inclinação de repouso: ligeiramente de lado, como pousado no ar
  const REST = { x: -0.22, y: -0.42 };
  const state = { x: REST.x, y: REST.y, vx: 0, vy: 0, dragging: false, lastX: 0, lastY: 0, idle: 0, hover: { x: 0, y: 0 } };

  function resize() {
    const r = stage.getBoundingClientRect();
    renderer.setSize(r.width, r.height, false);
    camera.aspect = r.width / r.height;
    // Em ecrãs estreitos afasta a câmara para o cartão caber inteiro
    camera.position.z = camera.aspect < 1.2 ? 19 : 16.5;
    camera.updateProjectionMatrix();
  }
  new ResizeObserver(resize).observe(stage);
  resize();

  // ── Interação: arrastar (rato ou dedo) e setas ─────────
  canvas.addEventListener('pointerdown', (e) => {
    state.dragging = true; state.lastX = e.clientX; state.lastY = e.clientY;
    state.vx = state.vy = 0;
    canvas.setPointerCapture(e.pointerId);
    stage.classList.add('was-touched');
  });
  canvas.addEventListener('pointermove', (e) => {
    const r = stage.getBoundingClientRect();
    state.hover.x = ((e.clientX - r.left) / r.width - 0.5) * 2;
    state.hover.y = ((e.clientY - r.top) / r.height - 0.5) * 2;
    if (!state.dragging) return;
    const dx = e.clientX - state.lastX, dy = e.clientY - state.lastY;
    state.lastX = e.clientX; state.lastY = e.clientY;
    state.y += dx * 0.012; state.x += dy * 0.008;
    // Velocidade para a inércia ao largar, com limite (um puxão rápido não dá dez voltas)
    const clamp = (v, m) => Math.max(-m, Math.min(m, v));
    state.vy = clamp(dx * 0.012, 0.25); state.vx = clamp(dy * 0.008, 0.08);
  });
  const release = () => { state.dragging = false; state.idle = 0; };
  canvas.addEventListener('pointerup', release);
  canvas.addEventListener('pointercancel', release);
  canvas.addEventListener('pointerleave', () => { state.hover.x = state.hover.y = 0; });
  canvas.addEventListener('keydown', (e) => {
    const step = { ArrowLeft: [0, -0.35], ArrowRight: [0, 0.35], ArrowUp: [-0.2, 0], ArrowDown: [0.2, 0] }[e.key];
    if (!step) return;
    e.preventDefault();
    state.vx += step[0] * 0.25; state.vy += step[1] * 0.25; state.idle = 0;
    stage.classList.add('was-touched');
  });

  // ── Animação ───────────────────────────────────────────
  let visible = true, running = false, t = 0;
  const clock = new THREE.Clock();

  function frame() {
    if (!visible || document.hidden) { running = false; return; }
    running = true;
    const dt = Math.min(clock.getDelta(), 0.05);
    t += dt;

    if (!state.dragging) {
      // Inércia depois de largar
      state.y += state.vy; state.x += state.vx;
      state.vy *= 0.94; state.vx *= 0.9;
      state.idle += dt;
      // Passado um bocado volta devagar à frente mais próxima (múltiplos de 2π)
      if (state.idle > 1.2 && Math.abs(state.vy) < 0.002) {
        const turns = Math.round((state.y - REST.y) / (Math.PI * 2));
        const targetY = REST.y + turns * Math.PI * 2 + state.hover.x * 0.18;
        const targetX = REST.x + state.hover.y * 0.12;
        state.y += (targetY - state.y) * 0.04;
        state.x += (targetX - state.x) * 0.05;
      }
    }
    state.x = Math.max(-1.1, Math.min(0.8, state.x));

    // Flutuar suave (desligado com "reduzir movimento")
    const bob = calm.matches ? 0 : Math.sin(t * 1.1) * 0.12;
    const sway = calm.matches ? 0 : Math.sin(t * 0.6) * 0.05;
    holder.rotation.set(state.x + sway * 0.5, state.y + sway, Math.sin(t * 0.5) * (calm.matches ? 0 : 0.02));
    holder.position.y = bob;

    renderer.render(scene, camera);
    requestAnimationFrame(frame);
  }
  function wake() { if (!running && visible && !document.hidden) { clock.getDelta(); requestAnimationFrame(frame); } }

  new IntersectionObserver(([entry]) => { visible = entry.isIntersecting; wake(); }).observe(stage);
  document.addEventListener('visibilitychange', wake);

  renderer.render(scene, camera);
  stage.classList.add('is-live');
  wake();
}

// Só depois de a página estar pronta, para o 3D não atrasar o texto
const go = () => start().catch((err) => console.warn('[finora 3D] fica a imagem:', err));
if (document.readyState === 'complete') go(); else addEventListener('load', go, { once: true });
