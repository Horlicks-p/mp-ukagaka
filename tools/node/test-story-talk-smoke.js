const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");
const root = path.resolve(__dirname, "../..");

function clock() {
  let now = 0, serial = 0;
  const tasks = new Map(), callbacks = [];
  return {
    now: () => now,
    setTimeout(fn, delay) {
      const id = ++serial;
      tasks.set(id, { at: now + delay, fn });
      callbacks.push(fn);
      return id;
    },
    clearTimeout(id) { tasks.delete(id); },
    callbacks,
    advance(ms) {
      const until = now + ms;
      for (let limit = 0; limit < 100000; limit++) {
        const next = [...tasks].sort((a, b) => a[1].at - b[1].at || a[0] - b[0])[0];
        if (!next || next[1].at > until) { now = until; return; }
        tasks.delete(next[0]); now = next[1].at; next[1].fn();
      }
      throw Error("timer loop");
    },
    size: () => tasks.size,
  };
}

function setup(shared = new Map()) {
  const time = clock();
  const writes = [], history = [], historyAt = [], calls = [], blocks = new Set();
  const flags = { generation: 0, hidden: false, auto: true, chat: false, pending: false, robotHidden: false };
  let html = "", speed = 10, reads = 0, rngCalls = 0;
  const element = { setAttribute() {} };
  Object.defineProperty(element, "innerHTML", { get: () => html, set: (text) => { html = text; writes.push(text); } });
  const target = { 0: element, html(text) { element.innerHTML = text; } };
  const context = vm.createContext({
    window: { mpuCanvasManager: { playAnimation: () => calls.push("animation") } },
    URL, Math, Number, Map, Set,
    jQuery: () => target,
    mpuTypewriterTimer: null, mpuTypewriterSpeed: speed,
    mpuSetTypewriterTimer: (timer) => { context.mpuTypewriterTimer = timer; },
    mpuClearSystemPlaceholder() {},
    mpuLogger: { logL() {} },
    setTimeout: time.setTimeout, clearTimeout: time.clearTimeout,
    requestAnimationFrame: (fn) => time.setTimeout(fn, 16),
    cancelAnimationFrame: time.clearTimeout,
  });
  const base = fs.readFileSync(path.join(root, "js/ukagaka-base.js"), "utf8");
  const start = base.indexOf("let mpuTypewriterRunId =");
  const end = base.indexOf("function mpu_waitForTypewriterComplete(", start);
  assert.ok(start >= 0 && end > start);
  vm.runInContext(base.slice(start, end), context);
  vm.runInContext(fs.readFileSync(path.join(root, "js/ukagaka-story-talk.js"), "utf8"), context);
  const storage = {
    getItem(key) { reads++; return shared.get(key) ?? null; },
    setItem(key, value) { calls.push("persist"); shared.set(key, value); },
  };
  const env = {
    now: time.now, rng: () => { rngCalls++; return 0; }, storage: () => storage,
    restUrl: () => "https://EXAMPLE.test/site-a/wp-json/mp-ukagaka/v1/",
    generation: () => flags.generation,
    canStart: () => !flags.hidden && flags.auto && !flags.chat && !flags.pending && !flags.robotHidden,
    canContinue: () => !flags.hidden && flags.auto && !flags.chat,
    acquire: () => blocks.add("story-talk"), release: () => blocks.delete("story-talk"),
    ownsBlock: () => blocks.has("story-talk"),
    runId: () => context.mpuGetTypewriterRunId(), speed: () => speed,
    typewrite: (text, options) => {
      calls.push(["typewrite", text, options, time.now()]);
      return context.mpu_typewriter(text, "#ukagaka_msg", null, options);
    },
    cancelTypewriter: () => context.mpu_cancelTypewriter(),
    hide: () => calls.push("hide"), show: () => calls.push("show"), showText() {},
    afterFade: (fn) => fn(), emoji: (name) => calls.push(["emoji", name]),
    appendHistory: (text) => { history.push(text); historyAt.push(time.now()); }, setTimeout: time.setTimeout, clearTimeout: time.clearTimeout,
  };
  const make = (overrides = {}) => context.mpuCreateStoryTalk(Object.assign({}, env, overrides));
  return { context, time, flags, make, env, storage, shared, calls, history, historyAt, writes, blocks,
    html: () => html, reads: () => reads, rngCalls: () => rngCalls,
    speed(value) { speed = value; context.mpuTypewriterSpeed = value; } };
}

function store(ids = ["chest"]) {
  return { storiesGeneration: 0, stories: { personality: "Frieren",
    config: { start_chance: 1, cooldown_minutes: 0,
      step_pause: { base_ms: 1200, per_char_ms: 50, min_ms: 1500, max_ms: 4000 } },
    stories: ids.map((id) => ({ id, steps: ["one", "two", "three"].map((text) => ({ text, emoji: null })) })) } };
}

// Persistent rounds survive a fresh player/page after every draw, including cancellations.
{
  const disk = new Map();
  let previous;
  for (let round = 0; round < 3; round++) {
    const seen = new Set();
    for (let i = 0; i < 3; i++) {
      const r = setup(disk), player = r.make();
      assert.equal(player.tryStart(store(["a", "b", "c"])), true);
      const id = player.getStateForDebug().storyId;
      if (i === 0 && previous) assert.notEqual(id, previous);
      assert.equal(seen.has(id), false); seen.add(id); previous = id;
      assert.equal(JSON.parse([...disk.values()][0]).drawn.includes(id), true);
      assert.equal(r.calls.includes("hide"), true);
      assert.equal(r.calls.some((c) => Array.isArray(c) && c[0] === "typewrite"), false);
      player.cancel("test");
    }
    assert.equal(seen.size, 3);
  }
}

// Start/end cooldown, no RNG while cooling down, and the F5/no-finish exception.
{
  const r = setup(), s = store(); s.stories.config.cooldown_minutes = 1;
  const p = r.make(); p.tryStart(s);
  assert.equal(JSON.parse([...r.shared.values()][0]).cooldownUntil, 60000);
  r.time.advance(500); p.cancel();
  const saved = JSON.parse([...r.shared.values()][0]);
  assert.equal(saved.cooldownUntil, 60500);
  assert.deepEqual(saved.drawn, ["chest"]);
  const before = r.rngCalls(); assert.equal(p.tryStart(s), false); assert.equal(r.rngCalls(), before);
  r.time.advance(60000); assert.equal(p.tryStart(s), true); p.cancel();
  const fresh = setup(r.shared); assert.equal(fresh.make().tryStart(s), false);
}

// Eligibility checks do not touch storage or RNG. Invalid HTML never reaches the writer.
for (const mutation of [
  (r, s) => { s.storiesGeneration = 9; },
  (r) => { r.flags.pending = true; }, (r) => { r.flags.robotHidden = true; },
  (r) => { r.flags.hidden = true; }, (r) => { r.flags.auto = false; },
  (r, s) => { s.stories.stories[0].steps[0].text = "<img onerror=x>"; },
]) {
  const r = setup(), s = store(); mutation(r, s);
  assert.equal(r.make().tryStart(s), false); assert.equal(r.reads(), 0); assert.equal(r.rngCalls(), 0);
}
{
  const r = setup(), s = store(); s.stories.config.start_chance = 0;
  assert.equal(r.make().tryStart(s), false); assert.equal(r.shared.size, 0);
  const key = r.context.mpuStoryBagKey;
  assert.equal(key("https://EXAMPLE.test/a/", "Frieren"), key("https://example.test/a", "Frieren"));
  assert.notEqual(key("https://example.test/a/", "Frieren"), key("https://example.test/b/", "Frieren"));
}

// Real Typewriter + fake DOM/clock: natural completion commits each segment once.
{
  const r = setup(), p = r.make(); p.tryStart(store());
  r.time.advance(429); assert.equal(r.history.length, 0);
  r.time.advance(1); assert.deepEqual(r.history, ["one"]); assert.equal(r.html(), "one");
  const firstComplete = r.calls.find((c) => Array.isArray(c) && c[0] === "typewrite")[2].onComplete;
  firstComplete(); firstComplete();
  r.time.advance(10000);
  assert.deepEqual(r.history, ["one", "two", "three"]);
  assert.equal(p.isPlaying(), false); assert.equal(r.blocks.size, 0); assert.equal(r.time.size(), 0);
  assert.equal(r.calls.filter((c) => c === "animation").length, 1);
  assert.deepEqual(r.calls.filter((c) => Array.isArray(c) && c[0] === "typewrite").map((c) => c[2].skipCharacterAnimation), [false, true, true]);
}

// Stale callbacks (even explicitly replayed) cannot finish/kill a newer session.
{
  const r = setup(), p = r.make(); p.tryStart(store(["a", "b"])); r.time.advance(450);
  const oldCallbacks = r.time.callbacks.slice();
  p.cancel(); assert.equal(p.tryStart(store(["a", "b"])), true);
  const active = p.getStateForDebug().storyId;
  oldCallbacks.forEach((fn) => fn());
  assert.equal(p.getStateForDebug().storyId, active); assert.equal(r.blocks.size, 1);
  r.time.advance(10000); assert.equal(p.isPlaying(), false);
}

// Cancel mid-second-step preserves only step one; stale rAF does not alter a new owner.
for (const reason of ["cancel", "chat", "hidden", "generation", "off", "replaced"]) {
  const r = setup(), p = r.make(); p.tryStart(store()); r.time.advance(1940);
  assert.deepEqual(r.history, ["one"]);
  const staleFrames = r.time.callbacks.slice();
  if (reason === "chat") r.flags.chat = true;
  if (reason === "hidden") r.flags.hidden = true;
  if (reason === "generation") r.flags.generation++;
  if (reason === "off") r.flags.auto = false;
  if (reason === "replaced") r.context.mpu_typewriter("welcome", "#ukagaka_msg", null, true);
  else if (reason === "cancel") p.cancel();
  r.time.advance(11000);
  assert.equal(p.isPlaying(), false); assert.equal(r.blocks.size, 0);
  assert.deepEqual(r.history, ["one"]);
  if (reason === "replaced") {
    const text = r.html(); staleFrames.forEach((fn) => fn()); assert.equal(r.html(), text);
  }
}

// Generation changes must not redirect the canceled session's cooldown to another key.
{
  const r = setup(), p = r.make(), s = store(); s.stories.config.cooldown_minutes = 1;
  p.tryStart(s); r.time.advance(500);
  s.stories.personality = "Asuna"; s.stories.config.cooldown_minutes = 9; r.flags.generation++;
  p.cancel();
  assert.equal(r.shared.size, 1); assert.match([...r.shared.keys()][0], /_Frieren$/);
  assert.equal(JSON.parse([...r.shared.values()][0]).cooldownUntil, 60500);
}

// Storage read/write failures remain in-memory authoritative until the page ends.
for (const fail of ["read", "write"]) {
  const r = setup(), s = store(["a", "b", "c"]);
  if (fail === "read") r.storage.getItem = () => { throw Error("denied"); };
  else r.storage.setItem = () => { throw Error("quota"); };
  const p = r.make(), seen = new Set();
  for (let i = 0; i < 3; i++) { assert.equal(p.tryStart(s), true); seen.add(p.getStateForDebug().storyId); p.cancel(); }
  assert.equal(seen.size, 3);
}

// Missing completion, exceptions, very slow text: watchdog recovery never leaks blocks.
{
  const r = setup();
  const p = r.make({ typewrite: () => 9, runId: () => 9 });
  p.tryStart(store()); r.time.advance(11000);
  assert.equal(p.isPlaying(), false); assert.equal(r.blocks.size, 0);
}
{
  const r = setup(), p = r.make({ emoji: () => { throw Error("emoji unavailable"); } });
  p.tryStart(store()); r.time.advance(400); assert.equal(r.blocks.size, 0); assert.equal(p.isPlaying(), false);
}
{
  const r = setup(); r.speed(200);
  const s = store(); s.stories.stories[0].steps = Array.from({ length: 5 }, () => ({ text: "あ".repeat(120), emoji: null }));
  const p = r.make(); p.tryStart(s); r.time.advance(200000);
  assert.equal(r.history.length, 5); assert.equal(p.isPlaying(), false); assert.equal(r.blocks.size, 0);
}

// Typewriter contract: cancellation/clear/replacement invalidate old timers AND rAF.
// Pause budgets use Unicode character counts, with both clamp boundaries covered.
for (const [length, expected] of [[1, 1500], [15, 1950], [40, 3200], [80, 4000]]) {
  const r = setup(), s = store();
  s.stories.stories[0].steps[0].text = "あ".repeat(length);
  const p = r.make(); p.tryStart(s); r.time.advance(10000);
  const second = r.calls.filter((c) => Array.isArray(c) && c[0] === "typewrite")[1];
  assert.equal(second[3] - r.historyAt[0], expected);
}
// The default history adapter writes only completed role/content pairs.
{
  const r = setup(), env = Object.assign({}, r.env);
  delete env.appendHistory;
  r.context.window.mpuChatHistory = [];
  let saved = [];
  r.context.mpu_saveChatHistory = () => { saved = JSON.parse(JSON.stringify(r.context.window.mpuChatHistory)); };
  const p = r.context.mpuCreateStoryTalk(env); p.tryStart(store()); r.time.advance(450); p.cancel();
  assert.deepEqual(saved.map((m) => [m.role, m.type, m.content]), [
    ["user", "synthetic", "（独り言）"], ["assistant", "auto_talk", "one"],
  ]);
  assert.equal(saved.length, 2);
}
// Catalog changes keep drawn valid IDs, add new stories, and discard removed IDs.
{
  const r = setup(), p = r.make();
  p.tryStart(store(["a", "b"])); p.cancel();
  p.tryStart(store(["a", "c"])); assert.equal(p.getStateForDebug().storyId, "c"); p.cancel();
  const bag = JSON.parse([...r.shared.values()][0]); assert.deepEqual(bag.drawn, ["a", "c"]);
  r.shared.set([...r.shared.keys()][0], "{broken");
  const fresh = setup(r.shared), player = fresh.make();
  assert.equal(player.tryStart(store()), true); player.cancel();
  assert.equal(player.tryStart(store()), true); player.cancel();
}

// Typewriter contract: cancellation/clear/replacement invalidate old timers AND rAF.
{
  const r = setup(); let completed = 0;
  const id = r.context.mpu_typewriter("old", "#ukagaka_msg", null, { onComplete: () => completed++ });
  const stale = r.time.callbacks.slice();
  assert.equal(id, r.context.mpuGetTypewriterRunId());
  r.context.mpu_cancelTypewriter();
  r.context.mpu_typewriter("new", "#ukagaka_msg", null, true);
  r.time.advance(100); stale.forEach((fn) => fn());
  assert.equal(r.html(), "new"); assert.equal(completed, 0);
  r.context.mpu_typewriter("", "#ukagaka_msg"); assert.equal(r.html(), "");
  for (const text of ["abc", "<b>hello</b>", "あ".repeat(120)]) {
    r.context.mpu_typewriter(text, "#ukagaka_msg", null, { onComplete: () => completed++ });
    r.time.advance(10000); assert.equal(r.html(), text);
  }
  assert.equal(completed, 3);
}

// Existing bool callers retain the default startup owner; SPA cannot clear it.
{
  const state = { flags: { contextPending: false } };
  const ctx = vm.createContext({ mpuGetState: () => state, Set });
  const base = fs.readFileSync(path.join(root, "js/ukagaka-base.js"), "utf8");
  const start = base.indexOf("const mpuContextPendingOwners =");
  const end = base.indexOf("function mpuSetSettingsProcessed(", start);
  vm.runInContext(base.slice(start, end), ctx);
  ctx.mpuSetContextPending(true); ctx.mpuSetContextPending(true);
  ctx.mpuSetContextPending(true, "spa");
  ctx.mpuSetContextPending(false);
  assert.equal(ctx.mpuIsContextPending(), true);
  ctx.mpuSetContextPending(false, "spa");
  assert.equal(ctx.mpuIsContextPending(), false);
  ctx.mpuSetContextPending(true); ctx.mpuSetContextPending(true, "spa");
  ctx.mpuSetContextPending(false, "spa");
  assert.equal(ctx.mpuIsContextPending(), true);
  ctx.mpuSetContextPending(false);
  assert.equal(ctx.mpuIsContextPending(), false);
}

console.log("Story Talk smoke tests passed (engine + real Typewriter, simulated DOM/time; no browser E2E).");
