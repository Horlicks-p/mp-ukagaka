const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");
const root = path.resolve(__dirname, "../..");
const source = (file) => fs.readFileSync(path.join(root, file), "utf8");
function between(text, start, end) {
  const a = text.indexOf(start), b = text.indexOf(end, a);
  assert.ok(a >= 0 && b > a, `Missing source boundary: ${start}`);
  return text.slice(a, b);
}
const base = source("js/ukagaka-base.js");
const features = source("js/ukagaka-features.js");

function checkModuleLoading() {
  const files = between(source("tools/node/build.js"), "const coreFiles = [", "];" )
    .match(/'ukagaka-[^']+\.js'/g).map((name) => "js/" + name.slice(1, -1));
  for (const mode of [files, ["js/dist/ukagaka-bundle.min.js"]]) {
    const ready = [], listeners = new Map();
    const noop = () => {};
    const document = { hidden: false, cookie: "", body: { style: {} }, getElementById: () => null,
      addEventListener(name, fn) {
        if (!listeners.has(name)) listeners.set(name, []);
        listeners.get(name).push(fn);
      } };
    const jq = { on() { return jq; }, ready(fn) { ready.push(fn); } };
    const ctx = vm.createContext({ document, URL, URLSearchParams, console: { log: noop, warn: noop, error: noop },
      jQuery(value) { if (typeof value === "function") { ready.push(value); return; } return jq; },
      setTimeout: () => 1, clearTimeout: noop, addEventListener: noop, fetch: () => new Promise(noop),
      location: { href: "https://example.test/" }, mpuRestUrl: "https://example.test/wp-json/mp-ukagaka/v1/" });
    ctx.window = ctx;
    // Evaluate every module in order, before any ready callback, as the browser does.
    for (const file of mode) vm.runInContext(source(file), ctx, { filename: file });
    assert.equal(typeof ctx.mpuStoryTalkIsPlaying, "function");
    assert.equal(ctx.mpuStoryTalkIsPlaying(), false);
    const callback = ready.find((fn) => fn.toString().includes("featuresJqueryReady"));
    assert.ok(callback, "features ready callback must be registered");
    // Stop after initialization, before unrelated site settings/asset loading.
    const boundary = Error("unrelated cookie initialization");
    ctx.mpu_init_jquery_cookie = () => { throw boundary; };
    assert.throws(callback, (error) => error === boundary);
    assert.equal(typeof ctx.mpuStoryTalk.cancel, "function");
    const count = [...listeners.values()].reduce((sum, list) => sum + list.length, 0);
    ctx.mpuInitStoryTalk();
    assert.equal([...listeners.values()].reduce((sum, list) => sum + list.length, 0), count);
    const cancelled = [];
    ctx.mpuStoryTalk.cancel = (reason) => cancelled.push(reason);
    for (const event of ctx.mpuSpaEvents) for (const fn of listeners.get(event)) fn({});
    assert.equal(cancelled.filter((reason) => reason === "spa").length, ctx.mpuSpaEvents.length);
    document.hidden = true;
    for (const fn of listeners.get("visibilitychange")) fn({});
    assert.ok(cancelled.includes("hidden"));
  }
}

// Actual player, Typewriter and caller functions; only the browser, transport,
// clock and unrelated subsystems are simulated. This is not WordPress E2E.
function setup() {
  let now = 0, serial = 0, store = null;
  const timers = new Map(), events = new Map(), elements = new Map();
  const calls = [], requests = [], waits = [], animations = [], writes = [], disk = new Map();
  const doc = { hidden: false, body: { style: {} },
    addEventListener(name, fn) { if (!events.has(name)) events.set(name, []); events.get(name).push(fn); },
    getElementById(id) { return $("#" + id)[0]; }, createTextNode: (text) => text };
  const noop = () => {};
  function $(key) {
    if (typeof key === "function") return; // Other modules' unrelated ready callbacks.
    if (!elements.has(key)) {
      let html = key === "#ukagaka_msgnum" ? "0" : "";
      const attrs = {}, handlers = {};
      const node = { style: { removeProperty: noop }, setAttribute: noop, removeAttribute: noop, getAttribute: (name) => attrs[name] ?? null };
      Object.defineProperty(node, "innerHTML", { get: () => html,
        set(value) { html = value; writes.push([key, value]); } });
      const el = { 0: node, length: key === "#toggleAutoTalk" ? 0 : 1, hidden: false, animated: false,
        html(value) { if (value === undefined) return html; node.innerHTML = value; return el; },
        text(value) { return value === undefined ? html : el.html(value); },
        attr(name, value) { if (value === undefined) return attrs[name]; attrs[name] = value; return el; },
        removeAttr(name) { delete attrs[name]; return el; },
        is(selector) { return selector === ":hidden" ? el.hidden : selector === ":animated" && el.animated; },
        on(name, fn) { handlers[name] = fn; return el; },
        click() { handlers.click({ preventDefault: noop, stopPropagation: noop }); },
        fadeOut() { el.animated = true; return el; }, fadeIn() { el.hidden = false; el.animated = true; return el; },
        stop() { el.animated = false; return el; },
        promise() { return { done(fn) { fn(); } }; },
        empty() { return el.html(""); }, append() { calls.push("append"); return el; },
        find() { return el; }, children() { return el; }, remove() { return el; }, css() { return el; }, addClass() { return el; },
        removeClass() { return el; }, slideDown(ms, fn) { if (fn) fn(); return el; },
        focus() { return el; }, val() { return el; }, ready: noop,
      };
      elements.set(key, el);
    }
    return elements.get(key);
  }
  const ctx = vm.createContext({ URL, URLSearchParams, FormData, Math: Object.create(Math), Date: class extends Date { static now() { return now; } },
    document: doc, jQuery: $, console,
    setTimeout(fn, delay) { const id = ++serial; timers.set(id, { fn, at: now + delay }); return id; },
    clearTimeout(id) { timers.delete(id); },
    mpuLogger: new Proxy({}, { get: () => noop }),
    mpuRestUrl: "https://example.test/wp-json/mp-ukagaka/v1/", mpuInfo: { robot: ["show", "hide"], msg: ["show", "hide"] },
    mpuAutoTalk: true, mpuAutoTalkTimer: null, mpuMessageBlocking: false, mpuTypewriterTimer: null,
    mpuTypewriterSpeed: 10, mpuOllamaReplaceDialogue: false, mpuOllamaRequesting: false,
    mpuAiContextInProgress: false, mpuGreetInProgress: false, mpuOllamaRequestQueue: [],
    mpuNextMode: "random", mpuDefaultMsg: 0, mpuLastUserActionTime: 0, mpuIdleThreshold: 60000,
    mpuMessageBlockOwners: new Set(), mpuLastLLMResponse: "", mpuLLMResponseHistory: [], mpuMaxResponseHistory: 8,
    mpuGetState: () => state, mpuGetDialogStore: () => store, mpuSetDialogStore: (value) => { store = value; },
    mpuSetAutoTalkTimer: (value) => { ctx.mpuAutoTalkTimer = value; },
    mpuSetTypewriterTimer: (value) => { ctx.mpuTypewriterTimer = value; },
    mpuGetBaseAutoTalkInterval: () => 1000, mpu_isDeepSleepTime: () => false,
    mpuSetDialogNextMode: noop, mpuSetDialogDefaultMsg: noop,
    mpuSetAutoTalkEnabled: (value) => { ctx.mpuAutoTalk = value; },
    mpuClearSystemPlaceholder: noop, mpuIsDebugMode: () => false, mpu_setLocal: noop, mpu_getLocal: () => "show",
    mpu_unescapeHTML: (text) => text, mpu_getChatHistoryForRequest: () => ctx.mpuChatHistory,
    mpuSetOllamaRequesting: (value) => { ctx.mpuOllamaRequesting = value; },
    mpuSetLastLLMResponse: noop, mpuSetLastUserActionTime: noop,
    mpu_saveChatHistory() { calls.push("saveHistory"); }, mpu_loadChatHistory() { calls.push("loadHistory"); },
    mpu_handle_error(error, context, options) { calls.push(["error", context, options.showToUser]); },
    mpu_waitForTypewriterComplete: (fn) => waits.push(fn),
    mpuFetch(url, options) { calls.push("fetch"); return new Promise((resolve, reject) => requests.push({ url, options, resolve, reject })); },
  });
  ctx.window = ctx;
  ctx.addEventListener = noop;
  ctx.location = { href: "https://example.test/post" };
  ctx.localStorage = { getItem: (key) => disk.get(key) ?? null, setItem: (key, value) => disk.set(key, value) };
  ctx.requestAnimationFrame = (fn) => ctx.setTimeout(fn, 16);
  ctx.cancelAnimationFrame = ctx.clearTimeout;
  ctx.mpuChatHistory = []; ctx.mpuChatModeActive = false; ctx.mpuChatGeneration = 0;
  ctx.mpuCanvasManager = { playAnimation: noop, isFrierenMode: false,
    triggerCharacterAnimation(force, fn) { animations.push(fn); return true; } };
  ctx.Math.random = () => 0;
  const state = { flags: { contextPending: false }, dialog: {}, llm: {} };
  const run = (text, filename) => vm.runInContext(text, ctx, { filename });
  run(between(base, "let mpuTypewriterRunId =", "function mpu_waitForTypewriterComplete("), "real-typewriter.js");
  run(between(base, "const mpuContextPendingOwners =", "function mpuSetSettingsProcessed("), "real-pending.js");
  run(between(base, "function mpuShuffleBuiltInBag(", "function mpu_handle_error("), "real-shuffle-bag.js");
  run(source("js/ukagaka-core.js"), "ukagaka-core.js");
  // Real message-block owner functions, with only their state adapter stubbed.
  run(between(base, "function mpuSyncMessageBlocking(", "function mpuSetLastLLMResponse("), "real-message-blocks.js");
  run(source("js/ukagaka-story-talk.js"), "ukagaka-story-talk.js");
  run(source("js/ukagaka-dialog.js"), "ukagaka-dialog.js");
  run(source("js/ukagaka-chat-mode.js"), "ukagaka-chat-mode.js");
  run(features.slice(features.indexOf('  if (jQuery("#toggleAutoTalk").length === 0)'), features.indexOf('\n});\n\njQuery(window).on("blur"')), "real-ui-handlers.js");
  run(features.slice(features.indexOf("// ====== SPA Navigation 支援 ======")), "real-spa.js");
  ctx.mpuInitObservationTracking = noop;
  ctx.mpuInitStoryTalk();
  ctx.mpuFrierenManager = {};
  run(source("ghost/Frieren/frieren-interactions.js"), "frieren-interactions.js");
  return { ctx, $, calls, requests, waits, animations, writes, disk,
    setStore(value) { store = value; }, store: () => store,
    emit(name, event = {}) { for (const fn of events.get(name) || []) fn(event); },
    advance(ms) {
      const until = now + ms;
      for (let limit = 0; limit < 100000; limit++) {
        const next = [...timers].sort((a, b) => a[1].at - b[1].at || a[0] - b[0])[0];
        if (!next || next[1].at > until) { now = until; return; }
        timers.delete(next[0]); now = next[1].at; next[1].fn();
      }
      throw Error("timer loop");
    },
  };
}
function catalog() {
  return { msg: ["normal A", "normal B", "normal C"], storiesGeneration: 0, stories: { personality: "Frieren",
    config: { start_chance: 1, cooldown_minutes: 360,
      step_pause: { base_ms: 1200, per_char_ms: 50, min_ms: 1500, max_ms: 4000 } },
    stories: [{ id: "chest", steps: ["one", "two", "three"].map((text) => ({ text, emoji: null })) }] } };
}
const drain = async () => { for (let i = 0; i < 8; i++) await Promise.resolve(); };
function start(r) { r.setStore(catalog()); r.ctx.mpu_nextmsg("auto"); assert.equal(r.ctx.mpuStoryTalkIsPlaying(), true); }

(async () => {
  checkModuleLoading();
  // Selected stories bypass /nextmsg even in LLM mode. Tick owns rearming,
  // skips security checks during playback, and resumes after the final pause.
  for (const llm of [false, true]) {
    const r = setup(); r.setStore(catalog()); r.ctx.mpuOllamaReplaceDialogue = llm;
    let checks = 0;
    r.ctx.mpu_checkSpamEvent = (fn) => { checks++; fn(false); };
    r.ctx.startAutoTalk(); r.advance(1000);
    assert.equal(r.ctx.mpuStoryTalkIsPlaying(), true); assert.equal(checks, 1);
    r.advance(4000); assert.equal(checks, 1); assert.equal(r.requests.length, 0);
    r.advance(1100); assert.equal(r.ctx.mpuStoryTalkIsPlaying(), false);
    assert.deepEqual(r.ctx.mpuChatHistory.map((m) => m.content), ["（独り言）", "one", "（独り言）", "two", "（独り言）", "three"]);
    r.advance(1000); assert.equal(checks, 2);
  }

  // Ordinary/manual/startup paths and disabled catalogs never draw stories.
  for (const trigger of [undefined, "startup"]) {
    const r = setup(); r.setStore(catalog()); r.ctx.mpu_nextmsg(trigger); r.advance(500);
    assert.equal(r.ctx.mpuStoryTalkIsPlaying(), false); assert.equal(r.disk.size, 0);
    assert.ok(r.$("#ukagaka_msg").html().startsWith("normal"));
  }
  {
    const r = setup(), s = catalog(); s.stories = null; r.setStore(s); r.ctx.mpu_nextmsg("auto"); r.advance(500);
    assert.equal(r.ctx.mpuStoryTalkIsPlaying(), false); assert.ok(r.$("#ukagaka_msg").html().startsWith("normal"));
  }

  // A delayed built-in callback cannot consume the bag or replace a story.
  for (const animation of [false, true]) {
    const r = setup(); r.setStore(catalog()); r.ctx.mpuCanvasManager.isFrierenMode = animation;
    r.ctx.mpu_nextmsg();
    if (animation) r.advance(400); // Selection happened; wake callback is pending.
    else r.advance(200);
    start(r);
    const bag = JSON.stringify(r.store()), num = r.$("#ukagaka_msgnum").html(), run = r.ctx.mpuGetTypewriterRunId();
    if (animation) r.animations.shift()(); else r.advance(200);
    assert.equal(JSON.stringify(r.store()), bag); assert.equal(r.$("#ukagaka_msgnum").html(), num);
    assert.equal(r.ctx.mpuGetTypewriterRunId(), run); assert.equal(r.ctx.mpuChatHistory.length, 0);
    assert.equal(r.ctx.mpuStoryTalkIsPlaying(), true);
  }

  // Dialog binds only current responses; late first-line display is discarded.
  for (const empty of [false, true]) {
    const r = setup(); r.$("#ukagaka_msg").attr("data-initial-msg-system", "1");
    r.ctx.loadExternalDialog("dialog.json", empty);
    const s = catalog(); if (empty) s.msg = [];
    r.requests[0].resolve(s); await drain();
    assert.equal(r.store().storiesGeneration, 0); assert.ok(r.store().stories);
    if (!empty) {
      r.waits.shift()(); start(r);
      const num = r.$("#ukagaka_msgnum").html(); r.advance(1000);
      assert.equal(r.$("#ukagaka_msgnum").html(), num);
      assert.equal(r.$("#ukagaka_msg").attr("data-initial-msg-system"), undefined);
      assert.equal(r.ctx.mpuStoryTalkIsPlaying(), true);
    }
  }
  {
    const r = setup(); r.ctx.loadExternalDialog("old.json", true); r.ctx.loadExternalDialog("new.json", true);
    r.requests[1].resolve(catalog()); await drain(); const current = r.store();
    r.requests[0].resolve(catalog()); await drain(); assert.equal(r.store(), current);
    r.ctx.mpuCharacterGeneration++; assert.equal(r.ctx.mpuStoryTalk.tryStart(current), false);
  }

  // LLM history is already committed before its delayed wake animation.
  // A newer story must keep its Typewriter run, DOM and completed history.
  {
    const r = setup(), s = catalog(); s.stories.config.start_chance = 0;
    r.setStore(s); r.ctx.mpuOllamaReplaceDialogue = true; r.ctx.mpuCanvasManager.isCharacterMode = true;
    r.ctx.mpu_nextmsg("auto"); r.requests[0].resolve({ msg: "LLM line", msgnum: 1 }); await drain();
    assert.equal(r.ctx.mpuChatHistory.length, 2); assert.equal(r.animations.length, 1);
    s.stories.config.start_chance = 1; r.ctx.mpu_nextmsg("auto");
    const run = r.ctx.mpuGetTypewriterRunId(), count = r.writes.length;
    r.animations.shift()();
    assert.equal(r.ctx.mpuGetTypewriterRunId(), run); assert.equal(r.writes.length, count);
    assert.equal(r.ctx.mpuChatHistory.length, 2); assert.equal(r.ctx.mpuStoryTalkIsPlaying(), true);
    r.advance(450); assert.equal(r.ctx.mpuChatHistory.length, 4);
  }

  // Successful switch invalidates the old catalog even if the next dialog
  // load fails; a response from the previous generation cannot restore it.
  {
    const r = setup(); r.setStore(catalog()); r.ctx.loadExternalDialog("old.json", true);
    r.ctx.mpuChange("2"); r.requests[1].resolve({ num: "2" }); await drain();
    assert.equal(r.ctx.mpuCharacterGeneration, 1); assert.equal(r.store().stories, null);
    assert.equal(r.ctx.mpuStoryTalk.tryStart(r.store()), false);
    r.requests[0].resolve(catalog()); await drain(); assert.equal(r.store().stories, null);
  }

  // User interruptions cancel before UI writes. Completed segments survive;
  // cancelled partial segments cannot later reappear through Typewriter timers.
  for (const action of ["hide", "robot", "off", "hidden", "spa", "chat", "menu", "switch", "extend"]) {
    const r = setup(); start(r); r.advance(1940); // First segment done, second is typing.
    assert.equal(r.ctx.mpuChatHistory.length, 2);
    if (action === "hide") r.$("#show_msg").click();
    if (action === "robot") r.$("#remove").click();
    if (action === "off") r.$("#toggleAutoTalk").click();
    if (action === "hidden") { r.ctx.document.hidden = true; r.emit("visibilitychange"); }
    if (action === "spa") r.emit("moelogContentSwap");
    if (action === "chat") r.ctx.mpu_toggleChatMode(true);
    if (action === "menu") r.ctx.mpuChange();
    if (action === "switch") r.ctx.mpuChange("2");
    if (action === "extend") r.$("#mpu_extend").click();
    assert.equal(r.ctx.mpuStoryTalkIsPlaying(), false, action);
    assert.equal(r.ctx.mpuHasMessageBlock("story-talk"), false, action);
    const run = r.ctx.mpuGetTypewriterRunId(); r.advance(10000);
    assert.equal(r.ctx.mpuChatHistory.length, 2, action);
    assert.equal(r.ctx.mpuGetTypewriterRunId(), run, action);
    assert.ok(JSON.parse([...r.disk.values()][0]).cooldownUntil >= 21601940, action);
  }

  // Other owners retain their locks after cancellation; body/touch/decorations
  // do nothing during playback, including not consuming interaction cooldowns.
  {
    const r = setup(); start(r); r.advance(450); r.ctx.mpuAcquireMessageBlock("gift");
    const count = r.writes.length, history = r.ctx.mpuChatHistory.length;
    r.$("#ukagaka_img").click(); r.ctx.mpuFrierenManager.handleTouchZone("head");
    r.ctx.mpuFrierenManager.handleDecorationClick("staff"); r.ctx.mpu_nextmsg();
    assert.equal(r.writes.length, count); assert.equal(r.ctx.mpuChatHistory.length, history);
    assert.equal(r.ctx.mpuStoryTalkIsPlaying(), true);
    r.ctx.mpuStoryTalk.cancel(); assert.equal(r.ctx.mpuHasMessageBlock("gift"), true);
  }

  // SPA bursts retain pending until the latest callback. Neither SPA nor
  // startup may clear the other owner's pending state.
  {
    const r = setup(); r.setStore(catalog()); r.ctx.mpuSetContextPending(true);
    r.emit("moelogContentSwap"); r.advance(500); r.emit("turbo:load");
    r.ctx.mpuSetContextPending(false); r.advance(500);
    assert.equal(r.ctx.mpuIsContextPending(), true); assert.equal(r.ctx.mpuStoryTalk.tryStart(r.store()), false);
    r.ctx.mpuSetContextPending(true); r.advance(500); assert.equal(r.ctx.mpuIsContextPending(), true);
    r.ctx.mpuSetContextPending(false); assert.equal(r.ctx.mpuIsContextPending(), false);
    assert.equal(r.ctx.mpuStoryTalk.tryStart(r.store()), true);
  }

  // Requested robot fade-out is already ineligible before :hidden turns true.
  {
    const r = setup(); r.setStore(catalog()); r.ctx.mpu_hiderobot(400);
    assert.equal(r.$("#ukagaka").hidden, false); assert.equal(r.ctx.mpuStoryTalk.tryStart(r.store()), false);
    r.$("#ukagaka").animated = false; assert.equal(r.ctx.mpuStoryTalk.tryStart(r.store()), true);
    r.ctx.mpuStoryTalk.cancel(); r.ctx.mpu_showrobot(); assert.equal(r.ctx.mpuRobotHideRequested, false);
  }

  // Extend's late success/error cannot overwrite a newer story. Without a
  // story the original link/error behavior is preserved, including bad data.
  for (const playing of [false, true]) for (const result of ["ok", "bad", "error"]) {
    const r = setup(); r.$("#mpu_extend").click(); if (playing) { start(r); r.advance(450); }
    const count = r.writes.length, run = r.ctx.mpuGetTypewriterRunId();
    if (result === "error") r.requests[0].reject(Error("offline"));
    else r.requests[0].resolve(result === "ok" ? { label: "characters" } : {});
    await drain(); assert.equal(r.ctx.document.body.style.cursor, "auto");
    if (playing) {
      assert.equal(r.writes.length, count); assert.equal(r.ctx.mpuGetTypewriterRunId(), run);
      assert.equal(r.ctx.mpuStoryTalkIsPlaying(), true);
      assert.ok(!r.calls.some((c) => Array.isArray(c) && c[0] === "error" && c[2]));
    } else if (result === "ok") assert.ok(r.calls.includes("append"));
    else assert.ok(r.calls.some((c) => Array.isArray(c) && c[0] === "error" && c[2]));
  }
  console.log("Story Talk integration smoke tests passed (actual JS entrypoints; simulated browser/transport/time).");
})().catch((error) => { console.error(error); process.exitCode = 1; });
