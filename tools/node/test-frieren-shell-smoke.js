/**
 * Frieren SVG shell smoke test.
 *
 * - shell/Frieren/assets.json passes the runtime validator, and malformed
 *   manifests are rejected;
 * - every frame and decoration SVG exists and is a safe, self-contained
 *   static SVG (plan rule 7), body frames share one master grid and the one
 *   approved drop-shadow filter (plan rule 8);
 * - the sequence renderer plays loops and one-shot sequences in manifest
 *   order with manifest durations, and does not play a sequence that failed
 *   to load;
 * - the first frame shows before the rest of its sequence has loaded;
 * - loads still in flight when the character is switched away do not touch
 *   the next character's canvas, state or DOM;
 * - container listeners are not duplicated and are removed on cleanup;
 * - back decorations do not take clicks on the body's opaque pixels;
 * - wake callbacks, speaking retries, deferred generic animations and late
 *   decoration loads from a switched-away character do nothing.
 *
 * Runs the Frieren runtime modules (frieren.js, -animation, -interactions,
 * -decorations) and js/ukagaka-anime.js in a vm with DOM stubs and a fake
 * clock.
 */
const assert = require("assert");
const fs = require("fs");
const path = require("path");
const vm = require("vm");
const { execFileSync } = require("child_process");

const repoRoot = path.resolve(__dirname, "..", "..");
const ghostDir = path.join(repoRoot, "ghost", "Frieren");
const shellDir = path.join(ghostDir, "shell", "Frieren");
const assets = JSON.parse(fs.readFileSync(path.join(shellDir, "assets.json"), "utf8"));

// ------------------------------------------------------------------ runtime

function makeClock() {
  let now = 0;
  let seq = 0;
  const timers = new Map();
  return {
    setTimeout(fn, ms) {
      const id = ++seq;
      timers.set(id, { at: now + Math.max(0, ms || 0), fn });
      return id;
    },
    clearTimeout(id) {
      timers.delete(id);
    },
    now: () => now,
    pending: () => timers.size,
    advance(ms) {
      const end = now + ms;
      for (;;) {
        let next = null;
        for (const [id, t] of timers) {
          if (t.at <= end && (!next || t.at < next[1].at)) next = [id, t];
        }
        if (!next) break;
        timers.delete(next[0]);
        now = next[1].at;
        next[1].fn();
      }
      now = end;
    },
  };
}

function makeElement(tagName) {
  const attrs = {};
  const props = {};
  const listeners = [];
  return {
    tagName,
    id: "",
    dataset: {},
    width: 0,
    height: 0,
    parentNode: null,
    style: {
      setProperty: (k, v) => { props[k] = v; },
      removeProperty: (k) => { delete props[k]; },
      props,
    },
    attrLog: [],
    listeners,
    getAttribute: (k) => (k in attrs ? attrs[k] : null),
    setAttribute(k, v) {
      attrs[k] = v;
      if (k === "src") this.attrLog.push(v);
    },
    appendChild(child) { child.parentNode = this; },
    removeChild(child) { child.parentNode = null; },
    addEventListener(type, fn, capture) { listeners.push({ type, fn, capture: !!capture }); },
    removeEventListener(type, fn, capture) {
      const i = listeners.findIndex((l) => l.type === type && l.fn === fn && l.capture === !!capture);
      if (i >= 0) listeners.splice(i, 1);
    },
    getBoundingClientRect: () => ({ left: 0, top: 0, right: 208, bottom: 328, width: 208, height: 328 }),
    querySelector: () => null,
    getContext: () => ({ drawImage() {}, clearRect() {}, getImageData: () => ({ data: [0, 0, 0, 255] }) }),
  };
}

// fetch stub whose responses the test releases by hand
function makeFetch() {
  const pending = [];
  const fetch = (url) => new Promise((resolve) => pending.push({ url, resolve }));
  fetch.respond = (body) => {
    const p = pending.shift();
    p.resolve({ ok: true, status: 200, json: () => Promise.resolve(body) });
  };
  fetch.fail = (status = 404) => {
    const p = pending.shift();
    p.resolve({ ok: false, status, json: () => Promise.reject(new Error("no body")) });
  };
  fetch.pending = pending;
  return fetch;
}

function loadRuntime({ failSrc, delay } = {}) {
  const clock = makeClock();
  const container = makeElement("DIV");
  const canvas = makeElement("CANVAS");
  const created = [];
  const document = {
    getElementById: (id) => (id === "ukagaka_img" ? container : null),
    querySelectorAll: () => [],
    createElement: (tag) => {
      const el = makeElement(tag.toUpperCase());
      created.push(el);
      return el;
    },
  };
  function Image() {
    const img = { complete: false, naturalWidth: 0 };
    Object.defineProperty(img, "src", {
      set(v) {
        this._src = v;
        clock.setTimeout(() => {
          if (failSrc && v.endsWith(failSrc)) {
            this.onerror && this.onerror();
          } else {
            this.complete = true;
            this.naturalWidth = 208;
            this.onload && this.onload();
          }
        }, delay ? delay(v) : 1);
      },
      get() { return this._src; },
    });
    return img;
  }
  const logs = [];
  const mpuLogger = new Proxy({}, {
    get: (_, name) => (name === "log" ? false : (...args) => logs.push([name, ...args])),
  });
  const window = {
    mpuCanvasManager: { canvas, ctx: {}, imagesLoaded: false, currentCharacterName: "Frieren", loadGeneration: 0 },
    devicePixelRatio: 1,
    getComputedStyle: (el) => ({ zIndex: String(el.zIndex || 0) }),
  };
  const fetch = makeFetch();
  const context = {
    window, document, Image, mpuLogger, fetch, Promise, Object, Array, Number, String, Math, Error, isFinite, parseInt, isNaN,
    setTimeout: clock.setTimeout, clearTimeout: clock.clearTimeout,
  };
  vm.createContext(context);
  for (const file of ["frieren.js", "frieren-animation.js", "frieren-interactions.js", "frieren-decorations.js"]) {
    vm.runInContext(fs.readFileSync(path.join(ghostDir, file), "utf8"), context, { filename: file });
  }
  const m = window.mpuFrierenManager;
  m.isFrierenMode = true;
  m.frierenLoadGeneration = 0;
  m.isSleepMessage = () => false;
  m.realSetupDecorationClickThrough = m.setupDecorationClickThrough;
  m.setupDecorationClickThrough = () => {};
  return { m, clock, logs, created, window, container, canvas, fetch };
}

// what the canvas manager does when the visitor switches to another character
function switchAway(m, window) {
  window.mpuCanvasManager.loadGeneration++;
  m.cleanupFrierenElements();
}

async function flush(clock, ms = 5) {
  for (let i = 0; i < 20; i++) {
    clock.advance(ms / 20);
    await Promise.resolve();
    await new Promise((r) => setImmediate(r));
  }
}

// ------------------------------------------------------------------ manifest

function testValidator() {
  const { m } = loadRuntime();
  assert.strictEqual(m.validateFrierenAssets(assets), null, "shipped assets.json must validate");

  const bad = [
    ["no sleep", (a) => { delete a.sequences.sleep; }],
    ["no wake", (a) => { delete a.sequences.wake; }],
    ["no book_flip", (a) => { delete a.sequences.book_flip; }],
    ["empty frames", (a) => { a.sequences.wake.frames = []; }],
    ["book loops", (a) => { a.sequences.book_flip.loop = true; }],
    ["idle loop not boolean", (a) => { a.sequences.idle.loop = "true"; }],
    ["parent path", (a) => { a.sequences.idle.frames[0].src = "../idle/x.svg"; }],
    ["absolute url", (a) => { a.sequences.idle.frames[0].src = "https://example.com/x.svg"; }],
    ["png frame", (a) => { a.sequences.sleep.frames[0].src = "sleep/frieren-sleep-00.png"; }],
    ["zero duration", (a) => { a.sequences.book_flip.frames[3].duration_ms = 0; }],
    ["string duration", (a) => { a.sequences.wake.frames[0].duration_ms = "80"; }],
    ["short frame layout", (a) => { a.layout.frame = [1, 2, 3]; }],
    ["no layout box", (a) => { delete a.layout.box; }],
    ["format version", (a) => { a.format_version = 2; }],
  ];
  for (const [label, mutate] of bad) {
    const copy = JSON.parse(JSON.stringify(assets));
    mutate(copy);
    assert.ok(m.validateFrierenAssets(copy), "validator accepted: " + label);
    assert.throws(() => m.applyFrierenAssets(copy, "base/"), /invalid assets\.json/, "apply accepted: " + label);
  }
}

// ------------------------------------------------------------------ files

const FORBIDDEN = [
  [/<script/i, "script"],
  [/<foreignObject/i, "foreignObject"],
  [/\son[a-z]+\s*=/i, "event attribute"],
  [/<image\b/i, "embedded image"],
  [/href\s*=/i, "href"],
  [/@import|<style/i, "stylesheet"],
  [/url\((?!#)/i, "external url()"],
  [/base64/i, "embedded data"],
];
const SHADOW = '<filter id="character-drop-shadow" x="-10%" y="-10%" width="120%" height="130%" color-interpolation-filters="sRGB">\n'
  + '<feGaussianBlur in="SourceAlpha" stdDeviation="0.7" result="shadow-blur"/>\n'
  + '<feOffset in="shadow-blur" dx="0" dy="5" result="shadow-offset"/>\n'
  + '<feFlood flood-color="#000000" flood-opacity="0.24" result="shadow-color"/>\n';

// opaque cells of a body frame (its paths are 1-cell-aligned rects)
function rectCells(svg, w, h) {
  const cells = new Uint8Array(w * h);
  for (const m of svg.matchAll(/M(\d+) (\d+)h(\d+)v(\d+)h-\d+z/g)) {
    const [x, y, rw, rh] = m.slice(1).map(Number);
    for (let j = y; j < y + rh; j++) for (let i = x; i < x + rw; i++) cells[j * w + i] = 1;
  }
  return cells;
}

// transparent cells not reachable from the frame border
function holes(cells, w, h) {
  const seen = new Uint8Array(w * h);
  const stack = [];
  for (let i = 0; i < w; i++) stack.push(i, (h - 1) * w + i);
  for (let j = 0; j < h; j++) stack.push(j * w, j * w + w - 1);
  while (stack.length) {
    const k = stack.pop();
    if (seen[k] || cells[k]) continue;
    seen[k] = 1;
    const x = k % w, y = (k / w) | 0;
    if (x > 0) stack.push(k - 1);
    if (x < w - 1) stack.push(k + 1);
    if (y > 0) stack.push(k - w);
    if (y < h - 1) stack.push(k + w);
  }
  let n = 0;
  for (let k = 0; k < w * h; k++) if (!cells[k] && !seen[k]) n++;
  return n;
}

// opaque cells with no opaque 8-neighbour
function specks(cells, w, h) {
  let n = 0;
  for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) {
    if (!cells[y * w + x]) continue;
    let nb = 0;
    for (let dy = -1; dy <= 1; dy++) for (let dx = -1; dx <= 1; dx++) {
      if ((dx || dy) && x + dx >= 0 && x + dx < w && y + dy >= 0 && y + dy < h) nb += cells[(y + dy) * w + x + dx];
    }
    if (!nb) n++;
  }
  return n;
}

function checkSafe(file, svg) {
  for (const [re, what] of FORBIDDEN) {
    assert.ok(!re.test(svg), `${file}: ${what} is not allowed`);
  }
}

function testFiles() {
  const [, , vw, vh] = assets.view_box;
  const seen = new Set();
  for (const [name, seq] of Object.entries(assets.sequences)) {
    for (const frame of seq.frames) {
      const file = path.join(shellDir, frame.src);
      assert.ok(fs.existsSync(file), `${name}: missing ${frame.src}`);
      const svg = fs.readFileSync(file, "utf8");
      checkSafe(frame.src, svg);
      assert.ok(svg.includes(`viewBox="0 0 ${vw} ${vh}" width="${vw}" height="${vh}"`), `${frame.src}: not on the ${vw}x${vh} master grid at intrinsic size`);
      // layout.frame may stretch the frame horizontally; "meet" would ignore it
      assert.ok(svg.includes('preserveAspectRatio="none"'), `${frame.src}: must allow non-uniform scaling (preserveAspectRatio="none")`);
      assert.ok(svg.includes(SHADOW), `${frame.src}: drop-shadow filter differs from the master definition`);
      assert.strictEqual((svg.match(/<filter\b/g) || []).length, 1, `${frame.src}: only the drop-shadow filter is allowed`);
      assert.ok(!/<mask\b/i.test(svg), `${frame.src}: masks are not allowed`);
      seen.add(frame.src);
      const cells = rectCells(svg, vw, vh);
      assert.strictEqual(holes(cells, vw, vh), 0, `${frame.src}: transparent hole inside the character`);
      assert.strictEqual(specks(cells, vw, vh), 0, `${frame.src}: isolated pixel outside the character`);
    }
  }
  assert.strictEqual(seen.size, 38, "expected 38 distinct body frame files");

  const decorations = JSON.parse(fs.readFileSync(path.join(ghostDir, "decorations.json"), "utf8"));
  for (const item of decorations.items) {
    assert.ok(/^[a-z_]+\.svg$/.test(item.image), `decoration ${item.type} must be an SVG`);
    const file = path.join(ghostDir, decorations.decorations_base_folder, item.image);
    assert.ok(fs.existsSync(file), `decoration ${item.type}: missing ${item.image}`);
    const svg = fs.readFileSync(file, "utf8");
    checkSafe(item.image, svg);
    assert.ok(!/<filter\b|<mask\b/i.test(svg), `${item.image}: decorations take no filter or mask`);
  }

  // what ships: tracked files (local, untracked reference images are ignored)
  const tracked = execFileSync("git", ["ls-files", "ghost/Frieren/shell/Frieren", "ghost/Frieren/decorations"], { cwd: repoRoot, encoding: "utf8" })
    .split(/\r?\n/).filter(Boolean);
  const shellFiles = tracked.filter((f) => f.startsWith("ghost/Frieren/shell/Frieren/"));
  const stray = shellFiles.filter((f) => !/^ghost\/Frieren\/shell\/Frieren\/(assets\.json|(idle|sleep|book|wake)\/frieren-[a-z]+-\d\d\.svg)$/.test(f));
  assert.deepStrictEqual(stray, [], "shell/Frieren must only hold assets.json and the frame SVGs");
  assert.strictEqual(shellFiles.length, 39, "assets.json + 38 frames");
  const raster = tracked.filter((f) => f.startsWith("ghost/Frieren/decorations/") && /\.(png|apng|webp|jpe?g|gif)$/i.test(f));
  assert.deepStrictEqual(raster, [], "no raster decorations may remain");
}

// ------------------------------------------------------------------ renderer

async function testRenderer() {
  const { m, clock } = loadRuntime();
  m.applyFrierenAssets(assets, "base/");
  await Promise.all(["idle", "book_flip", "wake"].map((n) => {
    const p = m.loadFrierenSequence(n);
    return p;
  }).concat(flush(clock)));
  for (const n of ["idle", "book_flip", "wake"]) {
    assert.strictEqual(m.frierenSequenceState[n], "ready", n + " should be ready");
  }

  // idle loops in manifest order with manifest durations
  m.showFrierenIdle();
  const img = m.frierenIdleImgElement;
  assert.ok(img, "body <img> created");
  assert.strictEqual(img.style.display, "block");
  const idle = assets.sequences.idle.frames;
  const start = clock.now();
  const timeline = [];
  const origSet = img.setAttribute.bind(img);
  img.setAttribute = (k, v) => { if (k === "src") timeline.push([clock.now() - start, v]); origSet(k, v); };
  const loopMs = idle.reduce((s, f) => s + f.duration_ms, 0);
  clock.advance(loopMs + 1);
  // consecutive identical frames (idle-00/01) still change src (different files)
  const expected = [];
  let t = 0;
  for (let i = 1; i <= idle.length; i++) {
    t += idle[i - 1].duration_ms;
    expected.push([t, "base/" + idle[i % idle.length].src]);
  }
  assert.deepStrictEqual(timeline, expected, "idle frames or timing differ from assets.json");

  // book flip plays once, in order, then hands back to idle
  timeline.length = 0;
  const flipStart = clock.now();
  img.setAttribute = (k, v) => { if (k === "src") timeline.push([clock.now() - flipStart, v]); origSet(k, v); };
  m.playFrierenBookFlipAnimation();
  const book = assets.sequences.book_flip.frames;
  const total = book.reduce((s, f) => s + f.duration_ms, 0);
  clock.advance(total - 1);
  assert.deepStrictEqual(timeline.map((e) => e[1]), book.map((f) => "base/" + f.src), "book flip frames differ from assets.json");
  assert.strictEqual(m.frierenIsSpeaking, true, "still speaking during the flip");
  clock.advance(1);
  assert.strictEqual(m.frierenIsSpeaking, false, "flip ends after the sum of its durations");
  assert.strictEqual(img.getAttribute("src"), "base/" + idle[0].src, "flip hands back to idle frame 00");

  // wake calls back after all its frames
  let woke = null;
  const wakeStart = clock.now();
  m.playWakeUpAnimation(() => { woke = clock.now() - wakeStart; });
  await flush(clock, 1);
  clock.advance(1000);
  // (the test's own promise flushing advances the clock by < 1 ms before playback starts)
  const wakeMs = assets.sequences.wake.frames.reduce((s, f) => s + f.duration_ms, 0);
  assert.ok(woke !== null && Math.abs(woke - wakeMs) < 1, `wake callback after ${woke} ms, expected ${wakeMs}`);
}

async function testFailedSequence() {
  const failSrc = assets.sequences.book_flip.frames[4].src;
  const { m, clock, logs } = loadRuntime({ failSrc });
  m.applyFrierenAssets(assets, "base/");
  let rejected = false;
  const load = m.loadFrierenSequence("book_flip").catch(() => { rejected = true; });
  await Promise.all([load, flush(clock)]);
  assert.ok(rejected, "a sequence with a missing frame must reject");
  assert.strictEqual(m.frierenSequenceState.book_flip, "failed");
  m.playFrierenBookFlipAnimation();
  assert.strictEqual(m.frierenAnimationTimer, null, "a failed sequence must not play");
  assert.ok(logs.some((l) => l[1] === "frierenSequenceLoadFailed"), "failure is logged");
}

async function testStaleWake() {
  const { m, clock, window } = loadRuntime({ delay: () => 20 });
  m.applyFrierenAssets(assets, "base/");
  let calls = 0;
  m.playWakeUpAnimation(() => { calls++; });
  await flush(clock, 5);
  switchAway(m, window);
  await flush(clock, 2000);
  assert.strictEqual(calls, 0, "a switched-away wake must not continue the old dialogue flow");

  // a failed wake of the current character still hands control back
  const failSrc = assets.sequences.wake.frames[1].src;
  const live = loadRuntime({ failSrc });
  live.m.applyFrierenAssets(assets, "base/");
  let fallback = 0;
  live.m.playWakeUpAnimation(() => { fallback++; });
  await flush(live.clock, 20);
  assert.strictEqual(fallback, 1, "current-generation wake failure calls back");
}

async function testStaleSpeakingRetry() {
  const { m, clock, window } = loadRuntime();
  let flips = 0;
  m.playFrierenBookFlipAnimation = () => { flips++; };
  window.mpuCanvasManager.imagesLoaded = false;
  m.triggerFrierenSpeaking(false); // waits for imagesLoaded, retrying every 100 ms
  // Frieren A -> generic -> Frieren B, and B is fully loaded
  switchAway(m, window);
  window.mpuCanvasManager.loadGeneration++;
  m.isFrierenMode = true;
  m.frierenLoadGeneration = window.mpuCanvasManager.loadGeneration;
  m.applyFrierenAssets(assets, "base/");
  await Promise.all([m.loadFrierenSequence("book_flip"), flush(clock)]);
  window.mpuCanvasManager.imagesLoaded = true;
  clock.advance(500);
  assert.strictEqual(flips, 0, "A's retry must not flip B's book");

  // the same retry within one generation still fires
  window.mpuCanvasManager.imagesLoaded = false;
  m.triggerFrierenSpeaking(false);
  window.mpuCanvasManager.imagesLoaded = true;
  clock.advance(150);
  assert.strictEqual(flips, 1, "retry fires once the current character has loaded");
}

function testStaleGenericAnimation() {
  const clock = makeClock();
  function Image() {
    const img = { complete: false, naturalWidth: 0, width: 100, height: 100 };
    Object.defineProperty(img, "src", {
      set(v) {
        this._src = v;
        clock.setTimeout(() => { this.complete = true; this.naturalWidth = 100; this.onload && this.onload(); }, 1);
      },
      get() { return this._src; },
    });
    return img;
  }
  const window = {};
  const context = {
    window, document: { getElementById: () => null }, Image, mpuLogger: { log: false },
    setTimeout: clock.setTimeout, clearTimeout: clock.clearTimeout,
  };
  vm.createContext(context);
  vm.runInContext(fs.readFileSync(path.join(repoRoot, "js", "ukagaka-anime.js"), "utf8"), context, { filename: "ukagaka-anime.js" });
  const cm = window.mpuCanvasManager;
  let plays = 0;
  const run = (switchMidway) => {
    plays = 0;
    cm.canvas = makeElement("CANVAS");
    cm.ctx = { clearRect() {}, drawImage() {} };
    cm.imageUrls = ["a.png", "b.png"];
    cm.isAnimated = true;
    cm.pendingAnimation = true;
    cm.playAnimation = () => { plays++; };
    cm.loadImages();
    clock.advance(2); // frames loaded; the deferred play is 50 ms out
    if (switchMidway) cm.loadGeneration++;
    clock.advance(100);
    return plays;
  };
  assert.strictEqual(run(false), 1, "deferred animation plays for the current character");
  assert.strictEqual(run(true), 0, "a switched-away character's deferred animation must not play");
}

function testRendererSelection() {
  const canvas = makeElement("CANVAS");
  const window = {};
  const context = {
    window,
    document: { getElementById: (id) => (id === "cur_ukagaka" ? canvas : null) },
    Image: function () {}, mpuLogger: { log: false, warnAlways() {}, errorL() {} },
    setTimeout, clearTimeout,
  };
  vm.createContext(context);
  vm.runInContext(fs.readFileSync(path.join(repoRoot, "js", "ukagaka-anime.js"), "utf8"), context, { filename: "ukagaka-anime.js" });
  const cm = window.mpuCanvasManager;
  const route = (num, name) => {
    const calls = [];
    window.mpuFrierenManager = {
      isFrierenMode: false,
      initFrierenMode() { calls.push("frieren"); },
      stopFrierenAnimation() {},
      cleanupFrierenElements() {},
    };
    cm.initGenericMode = () => { calls.push("generic"); };
    cm.init({ type: "folder", url: "base/", images: ["a.png"] }, name, num);
    return calls.join(",");
  };
  assert.strictEqual(route("default_1", "フリーレン"), "frieren", "the built-in character uses the Frieren renderer");
  assert.strictEqual(route("default_1", "Someone Else"), "frieren", "renamed built-in character still uses it");
  assert.strictEqual(route("custom_2", "Frieren Test"), "generic", "a DIY character named Frieren stays generic");
  assert.strictEqual(route("custom_3", "フリーレン風キャラ"), "generic", "a DIY character named フリーレン stays generic");
  assert.strictEqual(route("custom_4", "my frieren"), "generic");
  assert.strictEqual(cm.isFrieren("custom_2"), false);
}

function testStaleDecorationLoad() {
  const { m, window, container } = loadRuntime();
  m.addFrierenDecoration({ type: "staff", src: "staff.svg", zIndex: 8 });
  const decoration = m.frierenDecorations[0];
  assert.ok(decoration, "decoration added");
  decoration.complete = true;
  decoration.naturalWidth = 110;
  decoration.naturalHeight = 300;
  const onLoad = decoration.listeners.find((l) => l.type === "load").fn;
  switchAway(m, window);
  onLoad();
  assert.strictEqual(m.decorationHitCanvases.size, 0, "a removed decoration must not refill the hit cache");
  assert.strictEqual(container.listeners.length, 0);
}

async function testFirstFrameFirst() {
  const idle = assets.sequences.idle.frames;
  const { m, clock, window, container } = loadRuntime({
    delay: (src) => (src.endsWith(idle[0].src) ? 1 : 50),
  });
  m.applyFrierenAssets(assets, "base/");
  m.loadFrierenImages();
  await flush(clock, 5);
  const img = m.frierenIdleImgElement;
  assert.ok(img, "body <img> exists once frame 00 has loaded");
  assert.strictEqual(img.getAttribute("src"), "base/" + idle[0].src, "frame 00 is on screen");
  assert.strictEqual(img.style.display, "block");
  assert.strictEqual(container.style.visibility, "visible", "container revealed before the sequence is ready");
  assert.strictEqual(m.frierenSequenceState.idle, "loading");
  assert.strictEqual(m.frierenLoopTimer, null, "the loop waits for the whole sequence");
  assert.strictEqual(window.mpuCanvasManager.imagesLoaded, false);

  await flush(clock, 60);
  assert.strictEqual(m.frierenSequenceState.idle, "ready");
  assert.strictEqual(window.mpuCanvasManager.imagesLoaded, true);
  assert.ok(m.frierenLoopTimer, "loop starts once every frame is ready");
}

async function testStaleManifest() {
  const { m, clock, window, canvas, created, fetch } = loadRuntime();
  m.loadFrierenAssets("base/");
  assert.strictEqual(fetch.pending.length, 1);
  switchAway(m, window);
  fetch.respond(assets);
  await flush(clock, 100);
  assert.deepStrictEqual(canvas.style.props, {}, "stale manifest must not lay out the next character's canvas");
  assert.strictEqual(canvas.dataset.mpuBodyBox, undefined);
  assert.strictEqual(window.mpuCanvasManager.imagesLoaded, false, "stale load must not flag the next character as loaded");
  assert.ok(!created.some((el) => el.tagName === "IMG"), "stale load must not create a body <img>");
  assert.deepStrictEqual(Object.keys(m.frierenSequences), []);
}

async function testManifestFailure() {
  const { m, clock, window, fetch } = loadRuntime();
  let flips = 0;
  m.playFrierenBookFlipAnimation = () => { flips++; };
  m.loadFrierenAssets("base/");
  fetch.fail();
  await flush(clock, 100);
  assert.strictEqual(window.mpuCanvasManager.imagesLoaded, true, "a failed manifest ends the load, so speaking stops waiting");
  m.triggerFrierenSpeaking(false);
  m.triggerFrierenSpeaking(false);
  clock.advance(1000);
  assert.strictEqual(clock.pending(), 0, "no 100 ms retry chain is left behind");
  assert.strictEqual(flips, 0, "nothing to flip without a manifest");

  // a manifest that fails after switching away must not touch the next character
  const stale = loadRuntime();
  stale.m.loadFrierenAssets("base/");
  switchAway(stale.m, stale.window);
  stale.fetch.fail();
  await flush(stale.clock, 100);
  assert.strictEqual(stale.window.mpuCanvasManager.imagesLoaded, false, "stale failure must not flag the next character as loaded");
}

async function testStaleSequence() {
  const { m, clock, window, canvas, created } = loadRuntime({ delay: () => 20 });
  m.applyFrierenAssets(assets, "base/");
  m.loadFrierenImages();
  await flush(clock, 5);
  switchAway(m, window);
  const imgsBefore = created.filter((el) => el.tagName === "IMG").length;
  await flush(clock, 200);
  assert.deepStrictEqual(canvas.style.props, {}, "cleanup cleared the layout and nothing re-applied it");
  assert.strictEqual(window.mpuCanvasManager.imagesLoaded, false);
  assert.strictEqual(created.filter((el) => el.tagName === "IMG").length, imgsBefore, "no body <img> after switching away");
  assert.strictEqual(m.frierenIdleImgElement, null);
  assert.strictEqual(m.frierenLoopTimer, null);
}

async function testReentry() {
  let slow = false;
  const { m, clock, window } = loadRuntime({ delay: () => (slow ? 100 : 5) });
  m.applyFrierenAssets(assets, "base/");
  m.loadFrierenSequence("idle");
  await flush(clock, 1);
  switchAway(m, window);
  // back to Frieren before the first load finished
  window.mpuCanvasManager.loadGeneration++;
  m.isFrierenMode = true;
  m.frierenLoadGeneration = window.mpuCanvasManager.loadGeneration;
  slow = true;
  m.applyFrierenAssets(assets, "base/");
  m.loadFrierenSequence("idle");
  await flush(clock, 20);
  assert.strictEqual(m.frierenSequenceState.idle, "loading", "the old load must not mark the new sequence ready");
  await flush(clock, 120);
  assert.strictEqual(m.frierenSequenceState.idle, "ready");
}

function testListeners() {
  const { m, window, container } = loadRuntime();
  m.setupDecorationClickThrough = m.realSetupDecorationClickThrough;
  const count = (type) => container.listeners.filter((l) => l.type === type).length;
  m.setupCharacterTouchEvents();
  m.setupDecorationClickThrough();
  m.setupCharacterTouchEvents();
  m.setupDecorationClickThrough();
  assert.strictEqual(count("mousemove"), 1, "touch cursor handler bound once");
  assert.strictEqual(count("click"), 2, "touch + decoration click handlers bound once each");
  switchAway(m, window);
  assert.strictEqual(container.listeners.length, 0, "cleanup removes every container handler");
  assert.strictEqual(m.detectTouchZone({ clientX: 50, clientY: 50 }, makeElement("CANVAS")), null, "no Frieren touch zones outside Frieren mode");
}

function testClickArbitration() {
  const { m, container } = loadRuntime();
  m.setupDecorationClickThrough = m.realSetupDecorationClickThrough;
  const decoration = (type, zIndex) => {
    const el = makeElement("IMG");
    el.className = "frieren-decoration " + type;
    el.zIndex = zIndex;
    el.parentNode = container;
    return el;
  };
  const back = decoration("staff", 8);
  const front = decoration("dark_dragon_horn", 101);
  const body = makeElement("IMG");
  body.id = "frieren_idle_apng";
  body.zIndex = 99;
  body.naturalWidth = 208;
  body.style.display = "block";
  body.parentNode = container;
  m.frierenIdleImgElement = body;
  m.isPixelHit = () => true;
  let opaque = true;
  m.isCharacterPixelHit = () => opaque;
  const at = { clientX: 60, clientY: 100 };

  m.frierenDecorations = [back, front];
  assert.strictEqual(m.findDecorationAt(at, true), "dark_dragon_horn", "front decorations still win over the body");
  m.frierenDecorations = [back];
  assert.strictEqual(m.findDecorationAt(at, true), null, "an opaque body pixel hides the back decoration");
  opaque = false;
  assert.strictEqual(m.findDecorationAt(at, true), "staff", "a transparent body pixel clicks through to it");

  // the container click handler follows the same rule
  const clicked = [];
  m.handleDecorationClick = (type) => clicked.push(type);
  m.setupDecorationClickThrough();
  const handler = container.listeners.find((l) => l.type === "click").fn;
  const event = { target: body, clientX: at.clientX, clientY: at.clientY, stopPropagation() {}, preventDefault() {} };
  opaque = true;
  handler(event);
  assert.deepStrictEqual(clicked, [], "click on opaque body does not reach the back decoration");
  opaque = false;
  handler(event);
  assert.deepStrictEqual(clicked, ["staff"]);
}

(async () => {
  testValidator();
  testFiles();
  await testRenderer();
  await testFailedSequence();
  await testFirstFrameFirst();
  await testStaleManifest();
  await testManifestFailure();
  await testStaleSequence();
  await testReentry();
  testListeners();
  testClickArbitration();
  await testStaleWake();
  await testStaleSpeakingRetry();
  testStaleGenericAnimation();
  testRendererSelection();
  testStaleDecorationLoad();
  console.log("frieren shell smoke tests passed");
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
