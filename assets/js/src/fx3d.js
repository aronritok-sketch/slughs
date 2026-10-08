/* ==========================================================================
   Slugshop – valódi 3D (three.js, WebGL)
   Forrás; a böngészőbe a tools/3d/build.mjs csomagolja → assets/js/fx3d.js

   Az FX Panthera modellje a gyári oldalnézetből (PDF) készül:
   - lapos részek (előagy, tok, markolat, tus, tustalp): a render körvonala kihúzva,
     letört élekkel, az oldalukon a gyári render textúrájával; az M-LOK és a tok
     nyílásai valódi átmenő lyukak,
   - hengeres részek (burkolat, cső, palack, tár, céltávcső): esztergált (lathe) testek,
   - fizikai alapú anyagok (eloxált alumínium, acél, karbon, gumi), stúdió-környezetfény
     tükröződéssel, lágy árnyék, ACES tónusleképezés.
   Két jelenet: SlugFX3D.rifles(el) – két fegyver egymásnak támasztva forog;
   SlugFX3D.panthera(el) – görgetésre 3D-ben szétszerelődik, kameramozgással, címkékkel.
   ========================================================================== */
import * as THREE from 'three';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';

const PX = 0.001;                     // 1 px a gyári renderen ≈ 1 mm
const D = () => window.PANTHERA && window.PANTHERA.model;
const reduce = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const clamp = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v));
const lerp = (a, b, t) => a + (b - a) * t;
const ease = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);
const easeOut = (t) => 1 - Math.pow(1 - t, 3);

let cleanups = [];
const on = (t, ev, fn, o) => { t.addEventListener(ev, fn, o); cleanups.push(() => t.removeEventListener(ev, fn, o)); };

function webglOK() {
  try { const c = document.createElement('canvas'); return !!(c.getContext('webgl2') || c.getContext('webgl')); } catch (e) { return false; }
}

/* ------------------------------------------------------------------ anyagok */
let MAT = null;
function canvasTex(w, h, draw, repeat) {
  const c = document.createElement('canvas'); c.width = w; c.height = h;
  draw(c.getContext('2d'), w, h);
  const t = new THREE.CanvasTexture(c);
  t.wrapS = t.wrapT = THREE.RepeatWrapping;
  if (repeat) t.repeat.set(repeat[0], repeat[1]);
  t.colorSpace = THREE.SRGBColorSpace;
  t.anisotropy = 8;
  return t;
}
function noise(ctx, w, h, base, amp) {
  const img = ctx.getImageData(0, 0, w, h);
  for (let i = 0; i < img.data.length; i += 4) {
    const n = base + (Math.random() - 0.5) * amp;
    img.data[i] = img.data[i + 1] = img.data[i + 2] = n; img.data[i + 3] = 255;
  }
  ctx.putImageData(img, 0, 0);
}
function materials() {
  if (MAT) return MAT;
  const m = D();
  // a gyári render textúra az oldallapokra
  const face = new THREE.TextureLoader().load(window.PANTHERA.render);
  face.colorSpace = THREE.SRGBColorSpace; face.anisotropy = 8;
  // karbon (twill 2×2) a palackra
  const carbon = canvasTex(256, 256, (ctx, w, h) => {
    ctx.fillStyle = '#121214'; ctx.fillRect(0, 0, w, h);
    const s = 16;
    for (let y = 0; y < h; y += s) for (let x = 0; x < w; x += s) {
      const k = ((x / s) + (y / s)) % 4 < 2;
      const g = ctx.createLinearGradient(x, y, k ? x + s : x, k ? y : y + s);
      g.addColorStop(0, '#1b1c20'); g.addColorStop(0.5, k ? '#3a3c44' : '#2a2b31'); g.addColorStop(1, '#141417');
      ctx.fillStyle = g; ctx.fillRect(x + 0.5, y + 0.5, s - 1, s - 1);
    }
  }, [6, 3]);
  // finom eloxált-fém szemcse (érdesség-térkép)
  const grain = canvasTex(256, 256, (ctx, w, h) => noise(ctx, w, h, 150, 70), [8, 2]);
  grain.colorSpace = THREE.NoColorSpace;
  // recézés a tárra és a tornyokra
  const knurl = canvasTex(128, 32, (ctx, w, h) => {
    ctx.fillStyle = '#202024'; ctx.fillRect(0, 0, w, h);
    ctx.strokeStyle = '#3a3b40'; ctx.lineWidth = 2;
    for (let x = -h; x < w + h; x += 6) { ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x + h, h); ctx.stroke(); ctx.beginPath(); ctx.moveTo(x + h, 0); ctx.lineTo(x, h); ctx.stroke(); }
  }, [8, 1]);
  MAT = {
    face: new THREE.MeshStandardMaterial({ map: face, color: 0x55565c, metalness: 0.62, roughness: 0.36, envMapIntensity: 1.1 }),
    faceRubber: new THREE.MeshStandardMaterial({ map: face, color: 0x4a4b50, metalness: 0.05, roughness: 0.82 }),
    side: new THREE.MeshStandardMaterial({ color: 0x1e1f23, metalness: 0.9, roughness: 0.3, roughnessMap: grain }),
    polymer: new THREE.MeshStandardMaterial({ color: 0x17181b, metalness: 0.1, roughness: 0.7 }),
    rubber: new THREE.MeshStandardMaterial({ color: 0x18181a, metalness: 0.0, roughness: 0.9 }),
    anod: new THREE.MeshPhysicalMaterial({ color: 0x1b1c20, metalness: 0.82, roughness: 0.3, roughnessMap: grain, clearcoat: 0.35, clearcoatRoughness: 0.25 }),
    steel: new THREE.MeshStandardMaterial({ color: 0x8a8e96, metalness: 1, roughness: 0.22 }),
    brass: new THREE.MeshStandardMaterial({ color: 0xb08d4a, metalness: 1, roughness: 0.3 }),
    carbon: new THREE.MeshPhysicalMaterial({ map: carbon, color: 0xffffff, metalness: 0.15, roughness: 0.38, clearcoat: 1, clearcoatRoughness: 0.06 }),
    knurl: new THREE.MeshStandardMaterial({ map: knurl, metalness: 0.75, roughness: 0.45 }),
    lens: new THREE.MeshPhysicalMaterial({ color: 0x0b1830, metalness: 0.9, roughness: 0.04, clearcoat: 1, clearcoatRoughness: 0, iridescence: 0.6, iridescenceIOR: 1.6 }),
    dark: new THREE.MeshStandardMaterial({ color: 0x050506, roughness: 1 }),
    w: m.w, h: m.h
  };
  return MAT;
}

/* ------------------------------------------------------------------ geometria */
// px → modell-koordináta (x jobbra, y fel, a burkolat tengelye y = 0)
const AXIS = 58;
const vx = (x) => x * PX;
const vy = (y) => (AXIS - y) * PX;

// textúrakoordináta: a gyári render a lapos oldalakra pontosan rávetítve
function uvGen() {
  const W = MAT.w * PX, H = MAT.h * PX, off = (MAT.h - AXIS) * PX;
  return {
    generateTopUV(g, v, a, b, c) {
      return [a, b, c].map((i) => new THREE.Vector2(v[i * 3] / W, (v[i * 3 + 1] + off) / H));
    },
    generateSideWallUV() { return [new THREE.Vector2(0, 0), new THREE.Vector2(0, 0), new THREE.Vector2(0, 0), new THREE.Vector2(0, 0)]; }
  };
}
function shapeFrom(s) {
  const sh = new THREE.Shape(s.outer.map(([x, y]) => new THREE.Vector2(vx(x), vy(y))));
  s.holes.forEach((h) => sh.holes.push(new THREE.Path(h.map(([x, y]) => new THREE.Vector2(vx(x), vy(y))))));
  return sh;
}
// kihúzott lapos rész, középre igazítva z-ben
function slab(shapes, depthPx, bevelPx, faceMat, sideMat, segs = 2) {
  const g = new THREE.ExtrudeGeometry(shapes, {
    depth: depthPx * PX, bevelEnabled: bevelPx > 0, bevelThickness: bevelPx * PX, bevelSize: bevelPx * PX * 0.8,
    bevelSegments: segs, curveSegments: 10, UVGenerator: uvGen()
  });
  g.translate(0, 0, -depthPx * PX / 2);
  g.computeVertexNormals();
  const mesh = new THREE.Mesh(g, [faceMat, sideMat]);
  mesh.castShadow = mesh.receiveShadow = true;
  return mesh;
}
// esztergált test az x tengely mentén: profil [[x_px, r_px], …]
function lathe(profile, mat, seg = 48) {
  const pts = profile.map(([x, r]) => new THREE.Vector2(Math.max(0.0001, r * PX), x * PX));
  const g = new THREE.LatheGeometry(pts, seg);
  g.rotateZ(-Math.PI / 2); // y tengely → x tengely
  const mesh = new THREE.Mesh(g, mat);
  mesh.castShadow = mesh.receiveShadow = true;
  return mesh;
}
function cyl(rPx, lenPx, mat, axis = 'z', seg = 40) {
  const g = new THREE.CylinderGeometry(rPx * PX, rPx * PX, lenPx * PX, seg);
  if (axis === 'z') g.rotateX(Math.PI / 2); else if (axis === 'x') g.rotateZ(Math.PI / 2);
  const mesh = new THREE.Mesh(g, mat); mesh.castShadow = mesh.receiveShadow = true; return mesh;
}
function part(name, label, ...objs) {
  const g = new THREE.Group(); g.name = name; g.userData.label = label;
  objs.forEach((o) => g.add(o)); return g;
}

/* A Panthera modellje: alkatrész-csoportok, a szétszereléshez külön mozgathatók */
function buildRifle({ scope = true } = {}) {
  const M = materials();
  const m = D();
  const R = m.round;
  const rifle = new THREE.Group();
  const parts = {};
  const add = (p) => { rifle.add(p); parts[p.name] = p; return p; };

  // cső (a burkolat és az előagy belsejében; szétszereléskor előbukkan)
  const barrel = lathe([[240, 0], [240, 6.5], [770, 6.5], [770, 0]], M.steel, 32);
  add(part('barrel', 'Cső', barrel));

  // hangtompító-burkolat: esztergált, gyűrűs hornyokkal, előlapi furattal
  const s = R.shroud, r = s.r;
  const shroud = lathe([
    [s.x0, 0], [s.x0, r - 3], [s.x0 + 2, r], [s.x0 + 22, r], [s.x0 + 23, r - 1.2], [s.x0 + 27, r - 1.2], [s.x0 + 28, r],
    [s.x1 - 40, r], [s.x1 - 39, r - 1.2], [s.x1 - 35, r - 1.2], [s.x1 - 34, r], [s.x1, r], [s.x1, 0]], M.anod, 64);
  const bore = cyl(4, 3, M.dark, 'x'); bore.position.set(vx(s.x0 - 0.5), 0, 0);
  add(part('shroud', 'Hangtompító-burkolat', shroud, bore));

  // M-LOK előagy: két oldallap a render körvonalával + alsó sín
  const fShapes = m.flat.forend.map(shapeFrom);
  const fL = slab(fShapes, 4, 0.8, M.face, M.side); fL.position.z = 0.0215;
  const fR = slab(fShapes, 4, 0.8, M.face, M.side); fR.position.z = -0.0215;
  const fB = new THREE.Mesh(new THREE.BoxGeometry(vx(372), 9 * PX, 46 * PX), M.side);
  fB.position.set(vx(372 + 372 / 2 + 2), vy(87.5), 0); fB.castShadow = true;
  add(part('forend', 'M-LOK előagy', fL, fR, fB));

  // tok (regulátor, szelep, sín) – tömör, letört élű
  const act = slab(m.flat.action.map(shapeFrom), 40, 2.2, M.face, M.side, 3);
  add(part('action', 'Tok, regulátor', act));

  // tár (forgótár) és alatta az állítókerék – a render textúrájával
  const disc = (c, depth, mat) => {
    const sh = new THREE.Shape(); sh.absarc(vx(c.x), vy(c.y), c.r * PX, 0, Math.PI * 2, false);
    const d = slab([sh], depth, 1.2, M.face, mat, 3); return d;
  };
  const mag = disc(R.magazine, 58, M.knurl);
  add(part('magazine', 'Tár', mag));
  const wheel = disc(R.wheel, 50, M.knurl);
  add(part('wheel', 'Teljesítményállító', wheel));

  // markolat – lekerekített polimer
  const grip = slab(m.flat.grip.map(shapeFrom), 24, 5, M.face, M.polymer, 5);
  add(part('grip', 'Markolat', grip));

  // palack: karbon, kupolás végekkel, alumínium nyakkal és csatlakozóval
  const b = R.bottle, bn = R.bottleNeck;
  const dome = [];
  for (let i = 0; i <= 10; i++) { const a = (i / 10) * Math.PI / 2; dome.push([b.x0 + 16 - 16 * Math.cos(a), b.r * Math.sin(a)]); }
  const bottle = lathe([[b.x0, 0], ...dome, [b.x1 - 12, b.r], [b.x1, b.r - 8], [b.x1 + 4, bn.r + 4], [b.x1 + 4, 0]], M.carbon, 64);
  bottle.position.y = vy(b.y);
  const neck = lathe([[b.x1 + 2, 0], [b.x1 + 2, bn.r], [bn.x1 - 8, bn.r], [bn.x1 - 6, bn.r - 3], [bn.x1, bn.r - 3], [bn.x1, 0]], M.steel, 40);
  neck.position.y = vy(b.y);
  const gauge = cyl(10, 14, M.brass, 'y'); gauge.position.set(vx(bn.x0 + 22), vy(b.y) - 0.02, 0);
  add(part('bottle', 'Levegőpalack', bottle, neck, gauge));

  // tus és arcpárna
  const stock = slab(m.flat.stock.map(shapeFrom), 34, 2.5, M.face, M.side, 3);
  add(part('stock', 'Tus', stock));
  // tustalp – gumi
  const pad = slab(m.flat.buttpad.map(shapeFrom), 40, 4, M.faceRubber, M.rubber, 4);
  add(part('buttpad', 'Tustalp', pad));

  // csavarok (szétszereléskor kirepülnek oldalra)
  const screws = new THREE.Group(); screws.name = 'screws'; screws.userData.label = '';
  [[770, 45], [770, 85], [960, 50], [985, 80], [1030, 80], [1215, 85], [1270, 90], [930, 120]].forEach(([x, y], i) => {
    const sc = new THREE.Group();
    const head = cyl(3.2, 2.4, M.steel, 'z', 20); head.position.z = 0.0215;
    const hex = cyl(1.6, 2.6, M.dark, 'z', 6); hex.position.z = 0.0225;
    const shank = cyl(1.6, 16, M.steel, 'z', 12); shank.position.z = 0.012;
    sc.add(head, hex, shank); sc.position.set(vx(x), vy(y), 0.0005); sc.userData.i = i;
    screws.add(sc);
  });
  add(screws);

  // céltávcső gyűrűkkel a sínen
  if (scope) {
    const sx0 = 560, sx1 = 1010, sy = -22; // tengely a sín fölött
    const tube = lathe([
      [sx0, 0], [sx0, 27], [sx0 + 4, 28], [sx0 + 70, 28], [sx0 + 110, 16], [sx1 - 120, 15], [sx1 - 120, 17], [sx1 - 100, 17],
      [sx1 - 96, 15], [sx1 - 70, 16.5], [sx1 - 40, 21], [sx1 - 4, 21], [sx1, 20], [sx1, 0]], M.anod, 64);
    tube.position.y = vy(sy);
    const lensF = cyl(24, 1, M.lens, 'x', 48); lensF.position.set(vx(sx0 - 0.2), vy(sy), 0);
    const lensR = cyl(17, 1, M.lens, 'x', 48); lensR.position.set(vx(sx1 + 0.2), vy(sy), 0);
    const tElev = cyl(13, 26, M.knurl, 'y'); tElev.position.set(vx(800), vy(sy) + 0.026, 0);
    const tWind = cyl(13, 24, M.knurl, 'z'); tWind.position.set(vx(800), vy(sy), 0.026);
    const tPar = cyl(15, 20, M.knurl, 'z'); tPar.position.set(vx(800), vy(sy), -0.024);
    const ring = (x) => {
      const gr = new THREE.Group();
      const rr = new THREE.Mesh(new THREE.TorusGeometry(19 * PX, 4 * PX, 16, 40), M.anod); rr.rotation.y = Math.PI / 2; rr.scale.set(1, 1, 3.2);
      const base = new THREE.Mesh(new THREE.BoxGeometry(14 * PX, 14 * PX, 30 * PX), M.anod); base.position.y = -0.021;
      rr.castShadow = base.castShadow = true;
      gr.add(rr, base); gr.position.set(vx(x), vy(sy), 0); return gr;
    };
    add(part('scope', 'Céltávcső', tube, lensF, lensR, tElev, tWind, tPar));
    add(part('rings', 'Távcsőszerelék', ring(760), ring(900)));
  }

  // a modell közepe az origóban
  rifle.position.x = -vx(MAT.w / 2);
  const holder = new THREE.Group(); holder.add(rifle);
  holder.userData.parts = parts;
  return holder;
}

/* ------------------------------------------------------------------ közös színpad */
function stage(container, { shadows = true } = {}) {
  const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.75));
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.1;
  renderer.shadowMap.enabled = shadows;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  renderer.domElement.className = 'fx3d-canvas';
  container.appendChild(renderer.domElement);
  const scene = new THREE.Scene();
  const pmrem = new THREE.PMREMGenerator(renderer);
  scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.035).texture;
  const camera = new THREE.PerspectiveCamera(28, 1, 0.01, 50);
  // stúdiófény: fő, súroló (narancsos perem), derítő
  const key = new THREE.DirectionalLight(0xfff4e6, 2.4); key.position.set(1.4, 2.2, 1.8); key.castShadow = shadows;
  key.shadow.mapSize.set(2048, 2048); key.shadow.camera.left = key.shadow.camera.bottom = -1.2; key.shadow.camera.right = key.shadow.camera.top = 1.2;
  key.shadow.bias = -0.0004; key.shadow.normalBias = 0.01; key.shadow.radius = 4;
  const rim = new THREE.DirectionalLight(0xffa24a, 1.6); rim.position.set(-2, 0.8, -1.8);
  const fill = new THREE.HemisphereLight(0xdfe6ff, 0x1a1410, 0.35);
  scene.add(key, rim, fill);
  const size = () => {
    const r = container.getBoundingClientRect();
    const w = Math.max(1, r.width), h = Math.max(1, r.height);
    renderer.setSize(w, h, false); renderer.domElement.style.width = w + 'px'; renderer.domElement.style.height = h + 'px';
    camera.aspect = w / h; camera.updateProjectionMatrix();
    return { w, h };
  };
  const ro = new ResizeObserver(() => size()); ro.observe(container);
  cleanups.push(() => { ro.disconnect(); renderer.dispose(); pmrem.dispose(); renderer.domElement.remove(); });
  let visible = false; let raf = 0; let frameFn = null;
  const io = new IntersectionObserver((es) => es.forEach((e) => { visible = e.isIntersecting; if (visible && !raf && frameFn) raf = requestAnimationFrame(loop); }), { rootMargin: '100px' });
  io.observe(container); cleanups.push(() => io.disconnect());
  let last = 0;
  function loop(now) {
    const dt = last ? Math.min(0.05, (now - last) / 1000) : 0.016; last = now;
    if (frameFn) frameFn(dt, now);
    renderer.render(scene, camera);
    raf = visible ? requestAnimationFrame(loop) : 0;
    if (!raf) last = 0;
  }
  cleanups.push(() => cancelAnimationFrame(raf));
  return {
    renderer, scene, camera, key, rim, size,
    start(fn) { frameFn = fn; size(); if (!raf) raf = requestAnimationFrame(loop); },
    renderOnce() { renderer.render(scene, camera); }
  };
}

function shadowFloor(y, opacity) {
  const g = new THREE.PlaneGeometry(6, 6); g.rotateX(-Math.PI / 2);
  const f = new THREE.Mesh(g, new THREE.ShadowMaterial({ opacity })); f.position.y = y; f.receiveShadow = true; return f;
}

/* ------------------------------------------------------------------ 1) két fegyver forog */
function rifles(root) {
  const host = root.querySelector('.rifles-stage');
  if (!host || !D() || !webglOK()) return;
  const S = stage(host);
  const turn = new THREE.Group(); S.scene.add(turn);
  const tilt = 0.33, lift = 0.0;
  const a = buildRifle({ scope: true }); a.rotation.set(0, 0, -Math.PI / 2 + tilt); a.position.set(-0.075, lift, 0.035);
  const b = buildRifle({ scope: true }); b.rotation.set(0, Math.PI, -Math.PI / 2 + tilt); b.position.set(0.075, lift, -0.035);
  turn.add(a, b);
  // talajon álljanak: a legalsó pont y-ja
  const box = new THREE.Box3().setFromObject(turn);
  turn.position.y = -box.min.y - (box.max.y - box.min.y) / 2;
  const floorY = -(box.max.y - box.min.y) / 2;
  S.scene.add(shadowFloor(floorY - 0.002, 0.32));
  const height = box.max.y - box.min.y;
  S.camera.position.set(0, 0.08, 1);
  const fit = () => {
    const dist = (height * 1.18) / (2 * Math.tan(THREE.MathUtils.degToRad(S.camera.fov / 2)));
    S.camera.position.set(0, height * 0.05, dist); S.camera.lookAt(0, 0, 0);
  };
  fit(); on(window, 'resize', fit);
  host.classList.add('is-3d');
  let angle = -0.5, vel = 0.42, drag = null;
  if (reduce()) { turn.rotation.y = -0.6; S.start(() => {}); return; }
  on(host, 'pointerdown', (e) => { drag = { x: e.clientX, a: angle, t: performance.now() }; host.setPointerCapture(e.pointerId); host.classList.add('is-drag'); });
  on(host, 'pointermove', (e) => {
    if (!drag) return; const now = performance.now(); const na = drag.a + (e.clientX - drag.x) * 0.008;
    vel = (na - angle) / Math.max(0.016, (now - drag.t) / 1000); drag.t = now; angle = na;
  });
  const end = () => { if (drag) { drag = null; host.classList.remove('is-drag'); vel = clamp(vel, -9, 9); } };
  on(host, 'pointerup', end); on(host, 'pointercancel', end);
  S.start((dt, now) => {
    if (!drag) { vel += (0.42 - vel) * Math.min(1, dt * 1.2); angle += vel * dt; }
    turn.rotation.y = angle;
    turn.position.y = -box.min.y - height / 2 + Math.sin(now / 1400) * 0.004; // lebegés helyett minimális „légzés”
  });
}

/* ------------------------------------------------------------------ 2) Panthera szétszerelés */
// szétszerelt helyzet: eltolás (m) és elfordulás (rad), a fegyver saját koordinátáiban
const EXPLODE = {
  shroud:   { p: [-0.24, 0.0, 0.0],   r: [0, 0, 0] },
  barrel:   { p: [-0.07, 0.0, 0.0],   r: [0, 0, 0] },
  forend:   { p: [-0.05, -0.11, 0.0], r: [0, 0, 0] },
  action:   { p: [0, 0, 0],           r: [0, 0, 0] },
  magazine: { p: [0.0, 0.0, 0.17],    r: [0, 0, 0] },
  wheel:    { p: [0.0, -0.035, -0.15], r: [0, 0, 0] },
  grip:     { p: [0.0, -0.15, 0.0],   r: [0, 0, 0] },
  bottle:   { p: [0.05, -0.19, 0.0],  r: [0, 0, 0] },
  stock:    { p: [0.12, 0.035, 0.0],  r: [0, 0, 0] },
  buttpad:  { p: [0.23, 0.0, 0.0],    r: [0, 0, 0] },
  scope:    { p: [0.0, 0.17, 0.0],    r: [0, 0, 0] },
  rings:    { p: [0.0, 0.085, 0.0],   r: [0, 0, 0] },
  screws:   { p: [0, 0, 0],           r: [0, 0, 0] }
};
function panthera(root) {
  const host = root.querySelector('.panthera-stage');
  if (!host || !D() || !webglOK()) return;
  const S = stage(host);
  const holder = buildRifle({ scope: true });
  const parts = holder.userData.parts;
  S.scene.add(holder);
  S.scene.add(shadowFloor(-0.36, 0.28));
  host.classList.add('is-3d');
  const base = {};
  Object.entries(parts).forEach(([k, g]) => { base[k] = { p: g.position.clone(), r: g.rotation.clone() }; g.userData.cx = new THREE.Box3().setFromObject(g).getCenter(new THREE.Vector3()).x; });
  const xs = Object.values(parts).map((g) => g.userData.cx); const xmin = Math.min(...xs), xmax = Math.max(...xs);
  // címkék
  const labels = document.createElement('div'); labels.className = 'p3d-labels'; host.appendChild(labels);
  const NS = 'http://www.w3.org/2000/svg';
  const svg = document.createElementNS(NS, 'svg'); svg.setAttribute('class', 'p3d-leads'); svg.setAttribute('aria-hidden', 'true'); labels.appendChild(svg);
  const lab = Object.entries(parts).filter(([, g]) => g.userData.label).map(([k, g]) => {
    const el = document.createElement('span'); el.className = 'p3d-label'; el.textContent = g.userData.label; labels.appendChild(el);
    const line = document.createElementNS(NS, 'line'); const dot = document.createElementNS(NS, 'circle'); dot.setAttribute('r', 3);
    svg.append(line, dot);
    return { k, g, el, line, dot };
  });
  const bar = root.querySelector('.panthera-progress i');
  let mx = 0, my = 0, tmx = 0, tmy = 0;
  on(host, 'pointermove', (e) => { const r = host.getBoundingClientRect(); tmx = (e.clientX - r.left) / r.width - 0.5; tmy = (e.clientY - r.top) / r.height - 0.5; });
  on(host, 'pointerleave', () => { tmx = tmy = 0; });
  const v = new THREE.Vector3();
  const progress = () => {
    if (reduce()) return 1;
    const r = root.getBoundingClientRect(); const total = r.height - window.innerHeight;
    return total > 0 ? clamp(-r.top / total) : 1;
  };
  S.start((dt, now) => {
    const p = progress();
    mx += (tmx - mx) * 0.06; my += (tmy - my) * 0.06;
    // kamera: oldalnézetből ívben 3/4-es felülnézetbe, közben a modell lassan fordul
    const e = ease(clamp(p / 0.85));
    const narrow = S.camera.aspect < 1;
    const az = lerp(0.1, narrow ? -0.35 : -0.62, e) + mx * 0.3;
    const el = lerp(0.04, 0.3, e) - my * 0.15;
    // álló képernyőn átlósan fektetjük a fegyvert, így sokkal nagyobb lehet
    holder.rotation.z = narrow ? -0.95 : 0;
    const dist = lerp(narrow ? 2.7 : 1.7, narrow ? 3.35 : 1.98, e);
    S.camera.position.set(Math.sin(az) * Math.cos(el) * dist, Math.sin(el) * dist, Math.cos(az) * Math.cos(el) * dist);
    S.camera.lookAt(0, lerp(0, -0.02, e), 0);
    holder.rotation.y = Math.sin(now / 4000) * 0.03 * (1 - e);
    // alkatrészek: a fegyver elejétől hátrafelé hullámban
    for (const [k, g] of Object.entries(parts)) {
      const X = EXPLODE[k] || { p: [0, 0, 0], r: [0, 0, 0] };
      const a = (g.userData.cx - xmin) / (xmax - xmin || 1);
      const t = easeOut(clamp((p - 0.14 - a * 0.16) / 0.5));
      const arc = Math.sin(Math.PI * t);
      // a mozgás közben egy kicsit „kibillen”, a végén pontosan a helyére áll (mint egy szerelési animáció)
      g.position.set(base[k].p.x + X.p[0] * t, base[k].p.y + X.p[1] * t + arc * 0.012, base[k].p.z + X.p[2] * t);
      g.rotation.set(base[k].r.x + X.r[0] * t + arc * 0.06, base[k].r.y + X.r[1] * t + arc * 0.04, base[k].r.z + X.r[2] * t);
      if (k === 'screws') g.children.forEach((sc, i) => { sc.position.z = 0.0005 + t * (0.07 + (i % 3) * 0.03); sc.rotation.z = t * Math.PI * (2 + i); });
    }
    // címkék: a vetített alkatrész-középponthoz vezetővonallal, egymást nem takarva
    const la = clamp((p - 0.72) / 0.14);
    labels.style.opacity = narrow ? 0 : la;
    if (la > 0 && !narrow) {
      const R = host.getBoundingClientRect();
      svg.setAttribute('viewBox', `0 0 ${R.width} ${R.height}`);
      for (const L of lab) {
        new THREE.Box3().setFromObject(L.g).getCenter(v); v.project(S.camera);
        L.ax = (v.x * 0.5 + 0.5) * R.width; L.ay = (-v.y * 0.5 + 0.5) * R.height;
        L.w = L.el.offsetWidth || 120; L.h = L.el.offsetHeight || 26;
      }
      const placed = [];
      const hit = (x, y, L) => placed.some((o) => Math.abs(o.x - x) < (o.w + L.w) / 2 + 8 && Math.abs(o.y - y) < (o.h + L.h) / 2 + 6)
        || x - L.w / 2 < 4 || x + L.w / 2 > R.width - 4 || y - L.h / 2 < 4;
      lab.slice().sort((p1, p2) => p1.ay - p2.ay).forEach((L) => {
        const sx = L.w / 2 + 34;
        const cands = [[0, -46], [0, 46], [-sx, -34], [sx, -34], [-sx, 34], [sx, 34], [0, -86], [0, 86], [-sx, -74], [sx, -74], [0, -126], [0, 126]];
        let [dx, dy] = cands[0];
        for (const c of cands) { if (!hit(L.ax + c[0], L.ay + c[1], L)) { [dx, dy] = c; break; } }
        L.x = L.ax + dx; L.y = L.ay + dy; placed.push(L);
        L.el.style.transform = `translate(${L.x}px, ${L.y}px) translate(-50%, -50%)`;
        // a vezetővonal a címke szélétől indul
        const ex = clamp(L.ax, L.x - L.w / 2, L.x + L.w / 2), ey = dy < 0 ? L.y + L.h / 2 : L.y - L.h / 2;
        L.line.setAttribute('x1', ex); L.line.setAttribute('y1', Math.abs(dx) > L.w / 2 ? L.y : ey);
        L.line.setAttribute('x2', L.ax); L.line.setAttribute('y2', L.ay);
        if (Math.abs(dx) > L.w / 2) L.line.setAttribute('x1', dx < 0 ? L.x + L.w / 2 : L.x - L.w / 2);
        L.dot.setAttribute('cx', L.ax); L.dot.setAttribute('cy', L.ay);
      });
    }
    if (bar) bar.style.transform = `scaleX(${p})`;
    root.classList.toggle('is-done', p > 0.9);
  });
}

window.SlugFX3D = {
  rifles, panthera,
  destroy() { cleanups.forEach((f) => f()); cleanups = []; }
};
