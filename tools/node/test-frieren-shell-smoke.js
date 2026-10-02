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
 *   to load.
 *
 * Runs ghost/Frieren/frieren.js + frieren-animation.js in a vm with DOM stubs
 * and a fake clock.
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
  return {
    tagName,
    id: "",
    dataset: {},
    width: 0,
    height: 0,
    style: {
      setProperty: (k, v) => { props[k] = v; },
      removeProperty: (k) => { delete props[k]; },
      props,
    },
    attrLog: [],
    getAttribute: (k) => (k in attrs ? attrs[k] : null),
    setAttribute(k, v) {
      attrs[k] = v;
      if (k === "src") this.attrLog.push(v);
    },
    appendChild() {},
  };
}

function loadRuntime({ failSrc } = {}) {
  const clock = makeClock();
  const container = makeElement("DIV");
  const canvas = makeElement("CANVAS");
  const created = [];
  const document = {
    getElementById: (id) => (id === "ukagaka_img" ? container : null),
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
        }, 1);
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
    mpuCanvasManager: { canvas, ctx: {}, imagesLoaded: false, currentCharacterName: "Frieren" },
    devicePixelRatio: 1,
  };
  const context = {
    window, document, Image, mpuLogger, Promise, Object, Array, Number, String, Math, Error, isFinite,
    setTimeout: clock.setTimeout, clearTimeout: clock.clearTimeout,
  };
  vm.createContext(context);
  for (const file of ["frieren.js", "frieren-animation.js"]) {
    vm.runInContext(fs.readFileSync(path.join(ghostDir, file), "utf8"), context, { filename: file });
  }
  const m = window.mpuFrierenManager;
  m.isFrierenMode = true;
  m.isSleepMessage = () => false;
  m.setupDecorationClickThrough = () => {};
  return { m, clock, logs, created, window };
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

(async () => {
  testValidator();
  testFiles();
  await testRenderer();
  await testFailedSequence();
  console.log("frieren shell smoke tests passed");
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
