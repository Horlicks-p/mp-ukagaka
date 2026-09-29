/**
 * Interaction handoff flow tests (plan: Interaction_Reliability, phase 1).
 *
 * Boots a disposable Playground with this checkout mounted, points the plugin
 * at a scripted fake Ollama, and drives the real frontend bundle in Edge.
 * Nothing here reaches a paid model or a real database.
 *
 * Layers (reported per scenario, never merged):
 *   browser - real bundle + DOM + Playground REST, provider is the fake
 *   wp      - REST called directly; asserts on what the fake provider received
 *
 * The CLI's PHP build ships cURL, so chat runs over real server-side SSE (PHP
 * cURL -> fake NDJSON). Frontend-only SSE edge cases that the fake cannot
 * produce (error events, JSON fallback, watchdog) replay the endpoint instead.
 *
 * Usage: npm --prefix tools/node run test:interaction [-- --only=<name>] [-- --headed]
 */
const { chromium } = require("../node/node_modules/playwright");
const fs = require("node:fs");
const path = require("node:path");
const { startPlayground, root } = require("./lib/playground");
const { startFakeOllama } = require("./lib/fake-ollama");

const args = process.argv.slice(2);
const only = (args.find((a) => a.startsWith("--only=")) || "").slice(7);
const headed = args.includes("--headed");
const verbose = args.includes("--verbose");
const port = Number((args.find((a) => a.startsWith("--port=")) || "").slice(7)) || 9431;

const AUTO_TALK_INTERVAL_S = 3;
const outputDir = path.join(__dirname, "output");

// Site-local target hours. Frieren sleeps from 22/23 to 07, oversleeps to 09
// and may nap 12:30-13:30, so awake tests sit at 18:00 and sleep tests at 03:00.
const AWAKE_HOUR = 18;
const ASLEEP_HOUR = 3;

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

function firstLine(text) {
  return String(text || "").split("\n")[0];
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

/** Etc/GMT zone whose local hour is `targetHour` right now (POSIX sign is inverted). */
function zoneForLocalHour(targetHour) {
  let offset = targetHour - new Date().getUTCHours();
  if (offset > 14) offset -= 24;
  if (offset < -12) offset += 24;
  if (offset === 0) return "UTC";
  return `Etc/GMT${offset > 0 ? "-" : "+"}${Math.abs(offset)}`;
}

// Ghost overrides for sites that must not run as Frieren. Asuna is a
// placeholder ghost with no wake animation and no ghost-specific manager, which
// is exactly the generic branch the Frieren site cannot reach.
const GHOST_SETUP = {
  Frieren: "",
  Asuna: `$o['current_personality'] = 'Asuna';
$o['ukagakas']['default_1'] = array_merge($o['ukagakas']['default_1'], array(
  'name' => 'アスナ',
  'shell' => plugins_url('mp-ukagaka/ghost/Asuna/shell/'),
  'dialog_filename' => 'Frieren',
));`,
};

// Scenarios are grouped by site profile; each profile boots its own disposable
// site. "block" turns checksum enforcement on, so a history that drifted from the
// server's copy makes the next chat turn fail instead of only logging.
function siteProfile(s) {
  return { ghost: s.ghost || "Frieren", integrity: s.integrity || "audit" };
}

function profileKey(profile) {
  return `${profile.ghost}, checksum ${profile.integrity}`;
}

function blueprintFor(fakeUrl, { ghost, integrity }) {
  const php = `<?php
require '/wordpress/wp-load.php';
$o = get_option('mp_ukagaka');
if (!is_array($o) || empty($o)) { $o = mpu_default_opt(); }
$o = array_merge($o, array(
  'ai_enabled' => true,
  'llm_provider' => 'ollama',
  'ollama_endpoint' => '${fakeUrl}',
  'llm_ollama_endpoint' => '${fakeUrl}',
  'ollama_model' => 'fake:latest',
  'llm_ollama_model' => 'fake:latest',
  'llm_replace_dialogue' => true,
  'auto_talk' => true,
  'auto_talk_interval' => ${AUTO_TALK_INTERVAL_S},
  'typewriter_speed' => 5,
  'ai_greet_first_visit' => false,
  'ai_probability' => 0,
  'enable_chat_mode' => true,
  'chat_integrity_mode' => '${integrity}',
));
${GHOST_SETUP[ghost]}
update_option('mp_ukagaka', $o);
update_option('timezone_string', '${zoneForLocalHour(AWAKE_HOUR)}');
update_option('gmt_offset', '');
`;
  return {
    landingPage: "/",
    login: true,
    steps: [
      { step: "activatePlugin", pluginPath: "mp-ukagaka/mp-ukagaka.php" },
      { step: "runPHP", code: php },
    ],
  };
}

// ---------------------------------------------------------------------------
// In-page instrumentation. Installed before the bundle runs; it only wraps
// globals after they exist, so it never changes behaviour on its own.
// ---------------------------------------------------------------------------
const PAGE_PROBE = () => {
  const probe = {
    autoTalkSets: [],   // { at, id } for every non-null timer handed to mpuSetAutoTalkTimer
    autoTalkLeaks: 0,   // a new timer stored while the previous one was never cleared/fired
    nextmsgCalls: [],   // { at, trigger }
    typewriter: [],     // { at, text }
    requests: [],       // { at, url, status }
    msgChanges: [],     // { at, text } every text change of #ukagaka_msg, including direct .html() writes
  };
  window.__mpuProbe = probe;

  const origFetch = window.fetch.bind(window);
  window.fetch = function (input, init) {
    const url = typeof input === "string" ? input : input.url;
    const entry = { at: Date.now(), url, method: (init && init.method) || "GET", status: null };
    probe.requests.push(entry);
    return origFetch(input, init).then((res) => { entry.status = res.status; return res; },
      (err) => { entry.status = "error:" + (err && err.name); throw err; });
  };

  function wrapWhenReady() {
    if (typeof window.mpuSetAutoTalkTimer !== "function" || typeof window.mpu_nextmsg !== "function") {
      return false;
    }
    const origSet = window.mpuSetAutoTalkTimer;
    let live = null;
    window.mpuSetAutoTalkTimer = function (timer) {
      if (timer !== null && timer !== undefined) {
        if (live !== null) probe.autoTalkLeaks += 1;
        live = timer;
        probe.autoTalkSets.push({ at: Date.now(), id: timer });
      } else {
        live = null;
      }
      return origSet.apply(this, arguments);
    };
    const origNext = window.mpu_nextmsg;
    window.mpu_nextmsg = function (trigger) {
      probe.nextmsgCalls.push({ at: Date.now(), trigger: trigger === undefined ? "" : trigger });
      return origNext.apply(this, arguments);
    };
    const origType = window.mpu_typewriter;
    window.mpu_typewriter = function (text) {
      probe.typewriter.push({ at: Date.now(), text: String(text) });
      return origType.apply(this, arguments);
    };
    return true;
  }
  document.addEventListener("DOMContentLoaded", () => {
    const box = document.getElementById("ukagaka_msg");
    if (!box) return;
    let last = null;
    new MutationObserver(() => {
      const text = box.textContent;
      if (text !== last) {
        last = text;
        probe.msgChanges.push({ at: Date.now(), text });
      }
    }).observe(box, { childList: true, subtree: true, characterData: true });
  });
  if (!wrapWhenReady()) {
    document.addEventListener("DOMContentLoaded", function retry() {
      if (!wrapWhenReady()) setTimeout(retry, 0);
    });
  }
  // Keep the visitor "active" so the 60 s idle rule never skips a tick mid-test.
  setInterval(() => {
    if (typeof window.mpuSetLastUserActionTime === "function") window.mpuSetLastUserActionTime(Date.now());
  }, 2000);
};

// ---------------------------------------------------------------------------
// Harness helpers
// ---------------------------------------------------------------------------
class Harness {
  constructor(site, fake, browser) {
    this.site = site;
    this.fake = fake;
    this.browser = browser;
  }

  async newPage() {
    const context = await this.browser.newContext({ viewport: { width: 1280, height: 900 } });
    await context.addInitScript(PAGE_PROBE);
    const page = await context.newPage();
    page.setDefaultTimeout(60000);
    this.currentPage = page;
    const consoleErrors = [];
    page.on("pageerror", (err) => consoleErrors.push(String(err)));
    page.on("console", (msg) => { if (msg.type() === "error") consoleErrors.push(msg.text()); });
    page.consoleErrors = consoleErrors;
    return page;
  }

  async open(page) {
    await page.goto(this.site.url + "/", { waitUntil: "load", timeout: 120000 });
    await page.waitForFunction(() => window.__mpuProbe && typeof window.mpuGetState === "function"
      && window.mpuGetState().flags.settingsLoaded === true, null, { timeout: 60000 });
    await page.evaluate(() => (typeof window.mpuWaitForVisualReady === "function" ? window.mpuWaitForVisualReady() : null));
  }

  /** Switch the site clock by timezone so the server decides sleep vs awake. */
  async setSiteHour(page, hour) {
    const zone = zoneForLocalHour(hour);
    const result = await page.evaluate(async (tz) => {
      const base = window.mpuRestUrl.replace(/mp-ukagaka\/v1\/?$/, "");
      const res = await fetch(base + "wp/v2/settings", {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-WP-Nonce": window.mpuPreSettings.rest_nonce },
        body: JSON.stringify({ timezone: tz }),
      });
      return { status: res.status, body: await res.json() };
    }, zone);
    assert(result.status === 200 && result.body.timezone === zone,
      `could not set site timezone to ${zone}: ${JSON.stringify(result).slice(0, 300)}`);
  }

  async captureFailure(name, error) {
    const page = this.currentPage;
    if (!page || page.isClosed()) return null;
    fs.mkdirSync(outputDir, { recursive: true });
    const base = path.join(outputDir, name);
    try {
      await page.screenshot({ path: base + ".png" });
      const snapshot = {
        error: error ? String(error.stack || error.message) : null,
        state: await this.state(page),
        probe: await this.probe(page),
        consoleErrors: page.consoleErrors,
      };
      fs.writeFileSync(base + ".json", JSON.stringify(snapshot, null, 2));
    } catch (error) {
      return `capture failed: ${firstLine(error.message)}`;
    }
    return path.relative(root, base) + ".{png,json}";
  }

  async state(page) {
    return page.evaluate(() => {
      const s = window.mpuGetState();
      return {
        autoTalkEnabled: s.autoTalk.enabled,
        autoTalkTimer: s.autoTalk.timer,
        messageBlocking: s.llm.messageBlocking,
        ollamaRequesting: s.llm.ollamaRequesting,
        chatMode: window.mpuChatModeActive === true,
        chatRequesting: window.mpuChatRequesting === true,
        unawokenSleep: typeof window.mpu_isUnawokenSleepMode === "function" && window.mpu_isUnawokenSleepMode(),
        msg: (document.getElementById("ukagaka_msg") || {}).textContent || "",
        inputDisabled: !!(document.getElementById("mpu_user_input") || {}).disabled,
        history: (window.mpuChatHistory || []).map((h) => ({ role: h.role, type: h.type || "", content: h.content, at: h.timestamp })),
      };
    });
  }

  async probe(page) {
    return page.evaluate(() => JSON.parse(JSON.stringify(window.__mpuProbe)));
  }

  async typewriterIdle(page, timeout = 30000) {
    await page.waitForFunction(() => window.mpuGetState().typewriter.timer === null, null, { timeout });
  }
}

// ---------------------------------------------------------------------------
// Scenarios
// ---------------------------------------------------------------------------
const scenarios = [];
function scenario(name, layer, fn, options = {}) {
  scenarios.push({ name, layer, fn, ...options });
}

scenario("awake-autotalk-single-chain", "browser", async (h) => {
  h.fake.reset();
  const page = await h.newPage();
  await h.setSiteHourVia(page, AWAKE_HOUR);
  await h.open(page);
  const s0 = await h.state(page);
  assert(!s0.unawokenSleep, "precondition: site must be awake");
  assert(s0.autoTalkEnabled, "precondition: auto talk enabled");

  // Watch six intervals (slack for slower machines). One chain means ticks are spaced by at least the
  // interval and no timer is ever overwritten while still pending.
  const t0 = Date.now();
  await sleep(AUTO_TALK_INTERVAL_S * 1000 * 6);
  const p = await h.probe(page);
  const autos = p.nextmsgCalls.filter((c) => c.trigger === "auto" && c.at >= t0);
  assert(autos.length >= 2, `expected auto talk to tick, got ${autos.length}`);
  for (let i = 1; i < autos.length; i++) {
    const gap = autos[i].at - autos[i - 1].at;
    assert(gap >= AUTO_TALK_INTERVAL_S * 1000 * 0.9, `auto ticks ${gap} ms apart: more than one chain`);
  }
  assert(p.autoTalkLeaks === 0, `${p.autoTalkLeaks} auto-talk timer(s) overwritten while pending`);
  const llmCalls = h.fake.chatRequests().length;
  assert(llmCalls >= autos.length - 1, `auto ticks did not reach the provider (${llmCalls} calls)`);
  return { autoTicks: autos.length, providerCalls: llmCalls };
});

scenario("sleep-ok-wake-resumes-autotalk", "browser", async (h) => {
  h.fake.reset();
  const page = await h.newPage();
  await h.setSiteHourVia(page, ASLEEP_HOUR);
  await h.open(page);
  const s0 = await h.state(page);
  assert(s0.unawokenSleep, "precondition: site must be asleep and not yet woken");

  // Asleep: ticks must not reach the model, and no timer chain may run.
  const callsBeforeWait = h.fake.chatRequests().length;
  await sleep(AUTO_TALK_INTERVAL_S * 1000 * 2);
  const asleepCalls = h.fake.chatRequests().length - callsBeforeWait;
  assert(asleepCalls === 0, `${asleepCalls} provider call(s) while asleep`);
  const pAsleep = await h.probe(page);
  assert(pAsleep.nextmsgCalls.filter((c) => c.trigger === "auto").length === 0, "auto talk ran while asleep");

  h.fake.queueReply("……起きた。WAKE_LINE_MARKER");
  const tClick = Date.now();
  await page.click("#mpu_ok_btn img");
  await page.waitForFunction(() => (window.mpuChatHistory || []).some((m) => m.type === "wake_reaction"), null, { timeout: 90000 });
  await h.typewriterIdle(page);
  const s1 = await h.state(page);
  assert(s1.msg.includes("WAKE_LINE_MARKER"), `wake reaction not fully on screen: "${s1.msg}"`);
  const wake = s1.history.filter((m) => m.type === "wake_reaction");
  assert(wake.length === 1, `wake reaction stored ${wake.length} times`);
  const shown = (await h.probe(page)).typewriter.filter((t) => t.at >= tClick);
  assert(shown.some((t) => t.text.includes(wake[0].content.slice(0, 8))), "wake reaction was never typed out");
  assert(!s1.unawokenSleep, "still reported as unawoken after the wake reaction");
  assert(s1.autoTalkTimer !== null, "auto talk was not resumed after the wake reaction");

  // The wake line must stay until the resumed chain's first tick, which then
  // runs as a single chain.
  const tResume = Date.now();
  const wakeShownAt = shown.find((t) => t.text.includes("WAKE_LINE_MARKER")).at;
  await sleep(AUTO_TALK_INTERVAL_S * 1000 * 4);
  const p = await h.probe(page);
  const autos = p.nextmsgCalls.filter((c) => c.trigger === "auto" && c.at >= tResume);
  assert(autos.length >= 1, "auto talk never ticked after waking");
  for (let i = 1; i < autos.length; i++) {
    assert(autos[i].at - autos[i - 1].at >= AUTO_TALK_INTERVAL_S * 1000 * 0.9, "more than one auto-talk chain after waking");
  }
  assert(p.autoTalkLeaks === 0, `${p.autoTalkLeaks} auto-talk timer(s) overwritten while pending`);
  const replacedAt = p.typewriter.find((t) => t.at > wakeShownAt && !t.text.includes("WAKE_LINE_MARKER"));
  assert(!replacedAt || replacedAt.at - wakeShownAt >= AUTO_TALK_INTERVAL_S * 1000 * 0.9,
    `wake reaction replaced after ${replacedAt && replacedAt.at - wakeShownAt} ms`);
  const wakeRequest = p.requests.filter((r) => r.url.includes("wake-ghost"));
  assert(wakeRequest.length === 1, `wake-ghost requested ${wakeRequest.length} times`);
  return { providerCallsWhileAsleep: asleepCalls, autoTicksAfterWake: autos.length, wakeReaction: wake[0].content };
});

Harness.prototype.setSiteHourVia = async function (page, hour) {
  // Settings are written through the REST API, which needs a page with a nonce.
  await page.goto(this.site.url + "/", { waitUntil: "domcontentloaded", timeout: 120000 });
  await page.waitForFunction(() => window.mpuPreSettings && window.mpuPreSettings.rest_nonce, null, { timeout: 60000 });
  await this.setSiteHour(page, hour);
};

/** Click like a user; the dock and OK/cancel anchors are zero-sized around their image. */
Harness.prototype.click = async function (page, selector) {
  const target = page.locator(`${selector} img`);
  if (await target.count()) {
    await target.first().click();
  } else {
    await page.locator(selector).click();
  }
};

Harness.prototype.awakePage = async function () {
  const page = await this.newPage();
  await this.setSiteHourVia(page, AWAKE_HOUR);
  await this.open(page);
  assert(!(await this.state(page)).unawokenSleep, "precondition: site must be awake");
  // The startup line is requested ~2 s after load and is not gated by auto talk;
  // hand the page over only after it has finished, so it cannot land mid-scenario.
  await page.waitForFunction(() => window.__mpuProbe.nextmsgCalls.some((c) => c.trigger === "startup")
    && window.mpuGetState().llm.ollamaRequesting === false, null, { timeout: 30000 });
  await this.typewriterIdle(page);
  return page;
};

/**
 * Stop auto talk for scenarios about chat or gifts, so no auto tick can draw a
 * scripted reply meant for the chat request.
 */
Harness.prototype.quietAutoTalk = async function (page) {
  await page.evaluate(() => { window.mpuSetAutoTalkEnabled(false); window.stopAutoTalk(); });
  await page.waitForFunction(() => window.mpuGetState().llm.ollamaRequesting === false, null, { timeout: 30000 });
  await this.typewriterIdle(page);
};

Harness.prototype.enterChat = async function (page) {
  assert(await page.evaluate(() => window.mpuIsChatModeEnabled()), "precondition: chat mode must be enabled in settings");
  await this.click(page, "#mpu_chat_toggle");
  await page.waitForFunction(() => window.mpuChatModeActive === true
    && jQuery("#mpu_user_input").is(":visible"), null, { timeout: 30000 });
  await this.typewriterIdle(page);
  // The chat box must end up visible, not merely have been shown mid-fade.
  await page.waitForFunction(() => {
    const $box = jQuery("#ukagaka_msgbox");
    return $box.is(":visible") && !$box.is(":animated");
  }, null, { timeout: 5000 }).catch(() => {
    throw new Error("chat opened but the message box is hidden");
  });
};

Harness.prototype.exitChat = async function (page) {
  await this.click(page, "#mpu_cancel_btn");
  await page.waitForFunction(() => window.mpuChatModeActive === false, null, { timeout: 10000 });
};

Harness.prototype.send = async function (page, text) {
  await page.fill("#mpu_user_input", text);
  await page.press("#mpu_user_input", "Enter");
};

Harness.prototype.waitChatIdle = async function (page, timeout = 90000) {
  await page.waitForFunction(() => window.mpuChatRequesting === false, null, { timeout });
  await this.typewriterIdle(page);
};

function countAssistant(history, marker) {
  return history.filter((m) => m.role === "assistant" && String(m.content).includes(marker)).length;
}

function typedAfter(probe, at, marker) {
  return probe.typewriter.filter((t) => t.at >= at && t.text.includes(marker));
}

/** Any moment after `at` where the message box showed `marker`, however it got there. */
function shownAfter(probe, at, marker) {
  return typedAfter(probe, at, marker).length > 0
    || probe.msgChanges.some((c) => c.at >= at && c.text.includes(marker));
}

// --- chat handoff -----------------------------------------------------------

scenario("chat-entered-while-autotalk-waiting", "browser", async (h) => {
  h.fake.reset();
  const page = await h.awakePage();
  await page.waitForFunction(() => window.mpuGetState().autoTalk.timer !== null, null, { timeout: 30000 });
  await h.enterChat(page);
  const tEnter = Date.now();
  const callsAtEnter = h.fake.chatRequests().length;

  await sleep(AUTO_TALK_INTERVAL_S * 1000 * 3);
  let p = await h.probe(page);
  const autosInChat = p.nextmsgCalls.filter((c) => c.trigger === "auto" && c.at >= tEnter);
  assert(autosInChat.length === 0, `${autosInChat.length} auto tick(s) ran during chat`);
  assert(h.fake.chatRequests().length === callsAtEnter, "provider was called during an idle chat");
  const s = await h.state(page);
  assert(s.autoTalkTimer === null, "an auto-talk timer is pending during chat");

  await h.exitChat(page);
  const tExit = Date.now();
  await sleep(5000 + AUTO_TALK_INTERVAL_S * 1000 * 3);
  p = await h.probe(page);
  const autos = p.nextmsgCalls.filter((c) => c.trigger === "auto" && c.at >= tExit);
  assert(autos.length >= 1, "auto talk did not resume after leaving chat");
  assert(autos[0].at - tExit >= 5000, `auto talk resumed ${autos[0].at - tExit} ms after leaving (expected >= 5000)`);
  for (let i = 1; i < autos.length; i++) {
    assert(autos[i].at - autos[i - 1].at >= AUTO_TALK_INTERVAL_S * 1000 * 0.9, "more than one auto-talk chain after chat");
  }
  assert(p.autoTalkLeaks === 0, `${p.autoTalkLeaks} auto-talk timer(s) overwritten while pending`);
  return { autoTicksAfterExit: autos.length, firstTickAfterExitMs: autos[0].at - tExit };
});

scenario("chat-entered-while-autotalk-llm-in-flight", "browser", async (h) => {
  h.fake.reset();
  const page = await h.awakePage();
  h.fake.setDelay(6000);
  h.fake.setReplyFor(() => "AUTO_LINE_LATE");
  // Wait for an auto tick to be waiting on the provider, then open chat.
  await page.waitForFunction(() => window.mpuGetState().llm.ollamaRequesting === true, null, { timeout: 30000 });
  const tEnter = Date.now();
  await h.enterChat(page);
  await page.waitForFunction(() => window.mpuGetState().llm.ollamaRequesting === false, null, { timeout: 30000 });
  await sleep(1500);
  await h.typewriterIdle(page);
  const s = await h.state(page);
  const p = await h.probe(page);
  assert(h.fake.chatRequests().some((r) => r.reply === "AUTO_LINE_LATE"), "the auto-talk request never got its reply; nothing was tested");
  assert(!shownAfter(p, tEnter, "AUTO_LINE_LATE"), "an auto-talk reply requested before chat was shown in the chat box");
  // Rule (phase 2): a late reply is not shown but is still recorded, as the
  // character did say it and the chat context needs it.
  const stored = s.history.filter((m) => m.role === "assistant" && String(m.content).includes("AUTO_LINE_LATE"));
  assert(stored.length === 1 && stored[0].type === "auto_talk",
    `late auto-talk reply stored as ${JSON.stringify(stored.map((m) => m.type))}, expected one auto_talk`);
  assert(s.chatMode, "chat mode was left");

  // The request must also be released, or every later auto tick is skipped as "busy".
  h.fake.setDelay(0);
  h.fake.setReplyFor((n) => `AFTER_CHAT_${n}`);
  await h.exitChat(page);
  const tExit = Date.now();
  await sleep(5000 + AUTO_TALK_INTERVAL_S * 1000 * 3);
  const resumed = h.fake.chatRequests().filter((r) => r.at >= tExit && String(r.reply).startsWith("AFTER_CHAT_"));
  assert(resumed.length >= 1, "auto talk never reached the provider after leaving chat (request flag left set?)");
  return { msg: s.msg, providerCallsAfterExit: resumed.length };
});

// Both transports run against the real server: SSE streams through PHP cURL
// from the fake's NDJSON; JSON is forced by turning streaming off in the page.
async function useTransport(page, transport) {
  await page.evaluate((t) => { window.mpuPreSettings.streaming_enabled = t === "sse"; }, transport);
}

/**
 * Rule (phase 2): a reply that arrives after chat was closed or reopened is not
 * shown, but it is stored once, right after the user turn it answers. The server
 * already counted it in the checksum, so dropping it would break the next turn
 * under block mode.
 */
function assertLateReplyRecorded(s, userText, marker) {
  const at = s.history.findIndex((m) => m.role === "user" && m.content === userText);
  assert(at !== -1, "the user turn was lost");
  const stored = countAssistant(s.history, marker);
  assert(stored === 1, `late reply stored ${stored} times, expected once`);
  const next = s.history[at + 1];
  assert(next && next.role === "assistant" && String(next.content).includes(marker),
    `late reply is not right after its user turn: ${s.history.map((m) => `${m.role}:${m.type}`).join(" ")}`);
}

// Guards the guard: on the "block" site a tampered history must be rejected,
// otherwise the late-reply scenarios would pass without proving anything.
scenario("checksum-block-rejects-tampered-history", "browser + wp", async (h) => {
  h.fake.reset();
  const page = await h.awakePage();
  await h.quietAutoTalk(page);
  await h.enterChat(page);
  h.fake.queueReply("BEFORE_TAMPER");
  await h.send(page, "before tamper");
  await h.waitChatIdle(page);
  await page.evaluate(() => {
    const reply = window.mpuChatHistory.filter((m) => m.role === "assistant").pop();
    reply.content = "TAMPERED";
    mpu_saveChatHistory();
  });
  const mark = h.fake.chatRequests().length;
  h.fake.queueReply("AFTER_TAMPER");
  await h.send(page, "after tamper");
  await h.waitChatIdle(page);
  const s = await h.state(page);
  assert(h.fake.chatRequests().length === mark, "a tampered history still reached the provider; block mode is not active");
  assert(!s.msg.includes("AFTER_TAMPER"), "a tampered history still got a reply");
  return { msg: s.msg };
}, { integrity: "block" });

for (const transport of ["sse", "json"]) {
  scenario(`chat-close-keeps-late-reply-${transport}`, `browser + wp (${transport})`, async (h) => {
    h.fake.reset();
    const page = await h.awakePage();
    await h.quietAutoTalk(page);
    await useTransport(page, transport);
    await h.enterChat(page);
    // Longer than the 5 s exit line, so the reply lands after that line is in
    // history and only an insert-after-its-turn keeps the pair together.
    h.fake.setDelay(7000);
    h.fake.queueReply("CLOSED_REPLY");
    await h.send(page, "hello before closing");
    await page.waitForFunction(() => window.mpuChatRequesting === true, null, { timeout: 10000 });
    await h.exitChat(page);
    const tExit = Date.now();
    await page.waitForFunction(() => window.mpuChatRequesting === false, null, { timeout: 60000 });
    await sleep(500);
    const s = await h.state(page);
    const p = await h.probe(page);
    assert(h.fake.chatRequests().some((r) => r.reply === "CLOSED_REPLY"), "the provider never answered; nothing was tested");
    assert(!shownAfter(p, tExit, "CLOSED_REPLY"), "reply for a closed chat was shown");
    assert(!s.inputDisabled, "chat input stayed disabled");
    assertLateReplyRecorded(s, "hello before closing", "CLOSED_REPLY");
    const exitLine = s.history.find((m) => m.type === "auto_talk");
    const reply = s.history.find((m) => m.role === "assistant" && String(m.content).includes("CLOSED_REPLY"));
    assert(exitLine && exitLine.at < reply.at,
      "the exit line was not written before the late reply arrived; the ordering was not exercised");

    // Next turn: runs on a checksum "block" site, so it only succeeds if the
    // history still matches what the server stored.
    await h.enterChat(page);
    h.fake.queueReply("NEXT_TURN_REPLY");
    await h.send(page, "next turn");
    await h.waitChatIdle(page);
    assert((await h.state(page)).msg.includes("NEXT_TURN_REPLY"), "next turn was rejected or not shown (checksum drift?)");
    return { history: s.history.map((m) => `${m.role}:${m.type}`).join(" ") };
  }, { integrity: "block" });

  scenario(`chat-reopen-keeps-late-reply-off-screen-${transport}`, `browser + wp (${transport})`, async (h) => {
    h.fake.reset();
    const page = await h.awakePage();
    await h.quietAutoTalk(page);
    await useTransport(page, transport);
    await h.enterChat(page);
    h.fake.setDelay(5000);
    h.fake.queueReply("STALE_REPLY");
    await h.send(page, "hello before reopening");
    await page.waitForFunction(() => window.mpuChatRequesting === true, null, { timeout: 10000 });
    await h.exitChat(page);
    await h.enterChat(page);
    const tReopen = Date.now();
    await page.waitForFunction(() => window.mpuChatRequesting === false, null, { timeout: 60000 });
    await sleep(500);
    await h.typewriterIdle(page);
    const s = await h.state(page);
    const p = await h.probe(page);
    assert(h.fake.chatRequests().some((r) => r.reply === "STALE_REPLY"), "the provider never answered; nothing was tested");
    assert(!shownAfter(p, tReopen, "STALE_REPLY"), "reply from the previous chat session appeared in the reopened chat");
    assert(s.chatMode && !s.inputDisabled, "reopened chat is not usable after the stale reply");
    assertLateReplyRecorded(s, "hello before reopening", "STALE_REPLY");

    // The reopened chat keeps working and carries the late reply as context.
    h.fake.setDelay(0);
    h.fake.queueReply("FRESH_REPLY");
    const mark = h.fake.chatRequests().length;
    await h.send(page, "hello after reopening");
    await h.waitChatIdle(page);
    const s2 = await h.state(page);
    assert(s2.msg.includes("FRESH_REPLY"), `next turn was rejected or not shown (checksum drift?): "${s2.msg}"`);
    const sent = JSON.stringify(h.fake.chatRequests().slice(mark).map((r) => r.body && r.body.messages));
    assert(sent.includes("STALE_REPLY"), "the late reply was not sent as context on the next turn");
    return { msg: s2.msg };
  }, { integrity: "block" });
}

scenario("sse-server-provider-cut", "browser + wp (sse)", async (h) => {
  // The provider drops the connection after the first chunk. Exercises the PHP
  // cURL stream client and the SSE error path end to end.
  h.fake.reset();
  const page = await h.awakePage();
  await h.quietAutoTalk(page);
  await useTransport(page, "sse");
  await h.enterChat(page);
  h.fake.queueReply("CUT_REPLY_FIRST_HALF_SECOND_HALF");
  h.fake.cutStreamAfter(1);
  await h.send(page, "cut me");
  await page.waitForFunction(() => window.mpuChatRequesting === false, null, { timeout: 90000 });
  await h.typewriterIdle(page);
  const s = await h.state(page);
  const streamState = await page.evaluate(() => jQuery("#ukagaka_msgbox").attr("data-mpu-stream-state") || "");
  assert(!s.inputDisabled, "chat input stayed disabled");
  const stored = s.history.filter((m) => m.role === "assistant" && String(m.content).includes("CUT_REPLY")).length;
  const users = s.history.filter((m) => m.role === "user" && m.content === "cut me").length;
  // Provider and transport diagnostics stay in the server log, never on screen.
  assert(!/curl|transfer closed/i.test(s.msg), `raw transport error shown to the visitor: "${s.msg}"`);
  assert(streamState === "error", `expected error state, got "${streamState}"`);
  assert(stored === 0 && users === 0, "a cut stream left the turn in history");
  return { streamState, msg: s.msg };
});


scenario("sse-provider-emitted-error", "browser + wp (sse)", async (h) => {
  // Gemini and Claude emit their own 'error' event (raw text) before returning
  // WP_Error. Ollama does the same when its tool-loop guard trips, which the fake
  // can provoke by asking for the same tool call every turn.
  h.fake.reset();
  const page = await h.awakePage();
  await h.quietAutoTalk(page);
  await useTransport(page, "sse");
  await h.enterChat(page);
  h.fake.alwaysToolCall({ name: "e2e_loop_tool", arguments: { q: 1 } });
  const mark = h.fake.chatRequests().length;
  await h.send(page, "loop me");
  await page.waitForFunction(() => window.mpuChatRequesting === false, null, { timeout: 90000 });
  await h.typewriterIdle(page);
  const s = await h.state(page);
  const streamState = await page.evaluate(() => jQuery("#ukagaka_msgbox").attr("data-mpu-stream-state") || "");
  const turns = h.fake.chatRequests().length - mark;
  assert(turns >= 2, `the tool loop never repeated (${turns} provider call); the guard was not reached`);
  assert(!/e2e_loop_tool|ツール|tool call/i.test(s.msg), `provider's own error text shown to the visitor: "${s.msg}"`);
  assert(streamState === "error", `expected error state, got "${streamState}"`);
  assert(!s.inputDisabled, "chat input stayed disabled");
  return { msg: s.msg, providerTurns: turns };
});

// --- SSE terminal paths (frontend half; the stream endpoint is replayed) ------

function sseBody(frames) {
  return frames.map(([event, data]) => `event: ${event}\ndata: ${JSON.stringify(data)}\n\n`).join("");
}

async function withStreamingChat(h, handler) {
  const page = await h.awakePage();
  await page.evaluate(() => { window.mpuPreSettings.streaming_enabled = true; });
  const calls = [];
  await page.route((url) => url.href.includes("chat/user-stream") || url.href.includes("chat%2Fuser-stream"), async (route) => {
    calls.push(Date.now());
    await handler(route, calls.length);
  });
  await h.enterChat(page);
  return { page, calls };
}

async function assertStreamCleanup(h, page, { marker, expectReply, userText }) {
  await page.waitForFunction(() => window.mpuChatRequesting === false, null, { timeout: 70000 });
  await h.typewriterIdle(page);
  const s = await h.state(page);
  assert(!s.inputDisabled, "chat input stayed disabled");
  const stored = countAssistant(s.history, marker);
  const users = s.history.filter((m) => m.role === "user" && m.content === userText).length;
  if (expectReply) {
    assert(stored === 1, `reply stored ${stored} times`);
    assert(s.msg.includes(marker), `reply not on screen: "${s.msg}"`);
    assert(users === 1, `user turn stored ${users} times`);
  } else {
    assert(stored === 0, "failed stream left an assistant turn in history");
    assert(users === 0, "failed stream left the user turn in history (not rolled back)");
  }
  const leftovers = await page.evaluate(() => ({
    placeholder: document.querySelectorAll("#ukagaka_msg[data-mpu-system-placeholder], #ukagaka_msg .mpu-system-placeholder").length,
    streamState: jQuery("#ukagaka_msgbox").attr("data-mpu-stream-state") || "",
  }));
  return { msg: s.msg, ...leftovers };
}

scenario("sse-complete", "browser (SSE replayed)", async (h) => {
  const { page, calls } = await withStreamingChat(h, (route) => route.fulfill({
    status: 200,
    headers: { "Content-Type": "text/event-stream" },
    body: sseBody([["start", {}], ["delta", { text: "SSE_OK " }], ["delta", { text: "done." }], ["done", { msg: "SSE_OK done." }]]),
  }));
  await h.send(page, "sse hello");
  const out = await assertStreamCleanup(h, page, { marker: "SSE_OK", expectReply: true, userText: "sse hello" });
  assert(out.streamState === "", `stream state badge left as "${out.streamState}"`);
  assert(calls.length === 1, `stream requested ${calls.length} times`);
  return out;
});

scenario("sse-json-fallback", "browser (SSE replayed)", async (h) => {
  const { page } = await withStreamingChat(h, (route) => route.fulfill({
    status: 200,
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ msg: "JSON_FALLBACK_REPLY" }),
  }));
  await h.send(page, "json hello");
  return assertStreamCleanup(h, page, { marker: "JSON_FALLBACK_REPLY", expectReply: true, userText: "json hello" });
});

scenario("sse-ends-without-done", "browser (SSE replayed)", async (h) => {
  const { page } = await withStreamingChat(h, (route) => route.fulfill({
    status: 200,
    headers: { "Content-Type": "text/event-stream" },
    body: sseBody([["start", {}], ["delta", { text: "PARTIAL_TEXT" }]]),
  }));
  await h.send(page, "cut hello");
  const out = await assertStreamCleanup(h, page, { marker: "PARTIAL_TEXT", expectReply: false, userText: "cut hello" });
  assert(out.streamState === "error", `expected error state, got "${out.streamState}"`);
  return out;
});

scenario("sse-error-event", "browser (SSE replayed)", async (h) => {
  const { page } = await withStreamingChat(h, (route) => route.fulfill({
    status: 200,
    headers: { "Content-Type": "text/event-stream" },
    body: sseBody([["start", {}], ["error", { message: "PROVIDER_FAILED_MSG" }]]),
  }));
  await h.send(page, "err hello");
  const out = await assertStreamCleanup(h, page, { marker: "PROVIDER_FAILED_MSG", expectReply: false, userText: "err hello" });
  assert(out.msg.includes("PROVIDER_FAILED_MSG"), `error message not shown: "${out.msg}"`);
  return out;
});

scenario("sse-transport-failure", "browser (SSE replayed)", async (h) => {
  const { page } = await withStreamingChat(h, (route) => route.abort("connectionreset"));
  await h.send(page, "net hello");
  const out = await assertStreamCleanup(h, page, { marker: "__none__", expectReply: false, userText: "net hello" });
  assert(out.streamState === "error", `expected error state, got "${out.streamState}"`);
  return out;
});

scenario("sse-watchdog-timeout", "browser (SSE replayed)", async (h) => {
  // Never answer: the 45 s watchdog must abort, roll back and release input.
  const { page } = await withStreamingChat(h, () => new Promise(() => {}));
  await h.send(page, "slow hello");
  const out = await assertStreamCleanup(h, page, { marker: "__none__", expectReply: false, userText: "slow hello" });
  assert(out.streamState === "timeout", `expected timeout state, got "${out.streamState}"`);
  return out;
});

// --- page-aware context vs. auto talk --------------------------------------

/** Create a post long enough for page-aware context (>= 300 chars) and open it. */
async function openLongPost(h, page) {
  const link = await page.evaluate(async () => {
    const base = window.mpuRestUrl.replace(/mp-ukagaka\/v1\/?$/, "");
    const body = "フリーレンは千年以上生きたエルフの魔法使いで、勇者ヒンメルたちと魔王を倒した。".repeat(12);
    const res = await fetch(base + "wp/v2/posts", {
      method: "POST",
      headers: { "Content-Type": "application/json", "X-WP-Nonce": window.mpuPreSettings.rest_nonce },
      body: JSON.stringify({ title: "E2E 長文テスト", content: "<p>" + body + "</p>", status: "publish" }),
    });
    const json = await res.json();
    return json.link;
  });
  assert(link, "could not create the test post");
  await page.goto(link, { waitUntil: "load", timeout: 120000 });
  await page.waitForFunction(() => window.__mpuProbe && typeof window.mpuGetState === "function"
    && window.mpuGetState().flags.settingsLoaded === true, null, { timeout: 60000 });
  await page.evaluate(() => (typeof window.mpuWaitForVisualReady === "function" ? window.mpuWaitForVisualReady() : null));
  await h.typewriterIdle(page);
}

scenario("page-aware-during-autotalk-llm", "browser + wp", async (h) => {
  h.fake.reset();
  const page = await h.awakePage();
  await openLongPost(h, page);

  // An auto-talk request is waiting on the provider when page-aware starts
  // (as on an SPA page change). mpu_chat_context() is what both triggers call.
  // Let the startup line finish first, so the request caught below is an auto tick.
  await page.waitForFunction(() => window.mpuGetState().llm.ollamaRequesting === false
    && window.__mpuProbe.nextmsgCalls.some((c) => c.trigger === "startup"), null, { timeout: 30000 });
  await h.typewriterIdle(page);
  // Both requests wait 6 s at the provider; the auto tick's arrives first, so its
  // reply lands while page-aware is still running. Replies are chosen by content
  // because PHP reaches the provider some time after the browser sends.
  h.fake.setDelay(6000);
  h.fake.setReplyFor((n, body) => (JSON.stringify(body || {}).includes("E2E 長文テスト") ? "CONTEXT_LINE" : `AUTO_${n}`));
  const tArm = Date.now();
  await page.waitForFunction((t) => window.mpuGetState().llm.ollamaRequesting === true
    && window.__mpuProbe.nextmsgCalls.some((c) => c.trigger === "auto" && c.at >= t), tArm, { timeout: 30000 });
  const tContext = Date.now();
  await page.evaluate(() => {
    try { sessionStorage.removeItem("mpu_context_last_shown"); } catch (e) { /* no storage */ }
    mpu_chat_context();
  });
  await page.waitForFunction(() => window.mpuGetState().llm.aiContextInProgress === true, null, { timeout: 10000 });
  await page.waitForFunction(() => window.mpuGetState().llm.aiContextInProgress === false, null, { timeout: 90000 });
  const tContextEnd = Date.now();
  // Later auto ticks should be answered at once, so a pending request is not
  // mistaken for a stuck one.
  h.fake.setDelay(0);
  const p0 = await h.probe(page);
  const contextShown = shownAfter(p0, tContext, "CONTEXT_LINE");

  // Expected: auto talk is back within an interval or two after page-aware ends.
  await sleep(AUTO_TALK_INTERVAL_S * 1000 * 4);
  const s = await h.state(page);
  // Counted on arrival at the provider; page-aware requests carry the post title.
  const autoCalls = h.fake.chatRequests().filter((r) => r.at >= tContextEnd
    && !JSON.stringify(r.body || {}).includes("E2E 長文テスト"));
  const p = await h.probe(page);
  const ticks = p.nextmsgCalls.filter((c) => c.trigger === "auto" && c.at >= tContextEnd).length;
  const detail = {
    contextShown,
    contextMs: tContextEnd - tContext,
    autoTicksAfterContext: ticks,
    providerCallsAfterContext: autoCalls.length,
    ollamaRequestingStuck: s.ollamaRequesting,
  };
  assert(contextShown, "page-aware line was not shown");
  assert(autoCalls.length >= 1,
    `auto talk did not reach the provider within ${AUTO_TALK_INTERVAL_S * 4}s after page-aware ended: ${JSON.stringify(detail)}`);
  return detail;
});

// --- gift handoff (browser + what the provider actually received) -----------

function providerMessages(request) {
  return (request.body && Array.isArray(request.body.messages)) ? request.body.messages : [];
}

scenario("chat-gift-chat", "browser + wp", async (h) => {
  h.fake.reset();
  const page = await h.awakePage();
  await h.quietAutoTalk(page);
  await h.enterChat(page);

  h.fake.queueReply("REPLY_ONE_MARK");
  let mark = h.fake.chatRequests().length;
  await h.send(page, "CHAT_TURN_ONE");
  await h.waitChatIdle(page);
  assert((await h.state(page)).msg.includes("REPLY_ONE_MARK"), "first chat reply not shown");

  h.fake.queueReply("GIFT_REPLY_MARK");
  mark = h.fake.chatRequests().length;
  await page.click("#mpu_gift_picker_button");
  await page.click("#mpu_gift_picker .mpu-gift-picker-item");
  await page.waitForFunction(() => (window.mpuChatHistory || []).some((m) => m.type === "give"), null, { timeout: 90000 });
  await h.typewriterIdle(page);
  const giftCalls = h.fake.chatRequests().slice(mark);
  assert(giftCalls.length === 1, `gift made ${giftCalls.length} provider calls`);
  const giftMsgs = providerMessages(giftCalls[0]);
  const giftText = JSON.stringify(giftMsgs);
  assert(giftText.includes("CHAT_TURN_ONE") && giftText.includes("REPLY_ONE_MARK"),
    "gift request to the provider did not carry the preceding chat turn");
  const last = giftMsgs[giftMsgs.length - 1] || {};
  assert(last.role === "user" && !String(last.content).includes("CHAT_TURN_ONE"),
    "gift prompt is not the final user message");
  let s = await h.state(page);
  assert(s.msg.includes("GIFT_REPLY_MARK"), "gift reply not shown");
  assert(countAssistant(s.history, "GIFT_REPLY_MARK") === 1, `gift reply stored ${countAssistant(s.history, "GIFT_REPLY_MARK")} times`);

  h.fake.queueReply("REPLY_TWO_MARK");
  mark = h.fake.chatRequests().length;
  await h.send(page, "CHAT_TURN_TWO");
  await h.waitChatIdle(page);
  const nextCalls = h.fake.chatRequests().slice(mark);
  assert(nextCalls.length === 1, `second chat made ${nextCalls.length} provider calls`);
  const nextText = JSON.stringify(providerMessages(nextCalls[0]));
  assert(nextText.includes("GIFT_REPLY_MARK"), "the next chat turn did not carry the gift reaction to the provider");
  assert((nextText.match(/GIFT_REPLY_MARK/g) || []).length === 1, "gift reaction reached the provider more than once");
  s = await h.state(page);
  assert(countAssistant(s.history, "GIFT_REPLY_MARK") === 1, "gift reply duplicated in history after the next turn");
  assert(s.msg.includes("REPLY_TWO_MARK"), "second chat reply not shown");
  return {
    giftProviderMessages: giftMsgs.length,
    giftFinalUserPrompt: String(last.content).slice(0, 80),
    history: s.history.map((m) => `${m.role}:${m.type}`).join(" "),
  };
});

scenario("gift-rest-unknown-item", "wp", async (h) => {
  h.fake.reset();
  const page = await h.awakePage();
  // Otherwise the startup line or an auto tick can reach the provider mid-test.
  await h.quietAutoTalk(page);
  const mark = h.fake.chatRequests().length;
  const res = await page.evaluate(async () => {
    const fd = new FormData();
    fd.append("item_id", "no_such_item_e2e");
    fd.append("session_id", mpu_getOrCreateChatSessionId());
    fd.append("history", "[]");
    try {
      return { ok: true, body: await mpuFetch(mpuRestUrl + "touch/give", { method: "POST", body: fd, retries: 0 }) };
    } catch (error) {
      return { ok: false, message: error.message };
    }
  });
  assert(h.fake.chatRequests().length === mark, "an unknown item still reached the provider");
  assert(!res.ok || (res.body && res.body.error), `unknown item was accepted: ${JSON.stringify(res)}`);
  return res;
});

// --- rapid repeats ----------------------------------------------------------

scenario("rapid-chat-send", "browser", async (h) => {
  h.fake.reset();
  const page = await h.awakePage();
  await h.quietAutoTalk(page);
  await h.enterChat(page);
  h.fake.setDelay(2000);
  const mark = h.fake.chatRequests().length;
  await page.fill("#mpu_user_input", "double send");
  await page.locator("#mpu_ok_btn img").dblclick();
  await page.locator("#mpu_ok_btn img").click();
  await h.waitChatIdle(page);
  const p = await h.probe(page);
  const posts = p.requests.filter((r) => r.url.includes("chat/user") || r.url.includes("chat%2Fuser"));
  assert(posts.length === 1, `chat endpoint hit ${posts.length} times`);
  assert(h.fake.chatRequests().length - mark === 1, "provider called more than once");
  const s = await h.state(page);
  assert(s.history.filter((m) => m.role === "user" && m.content === "double send").length === 1, "user turn duplicated");
  return { posts: posts.length };
});

scenario("rapid-gift", "browser", async (h) => {
  h.fake.reset();
  const page = await h.awakePage();
  await h.quietAutoTalk(page);
  await h.enterChat(page);
  h.fake.setDelay(2000);
  const mark = h.fake.chatRequests().length;
  await page.click("#mpu_gift_picker_button");
  // The picker closes on the first click, so repeat through the handler the way
  // a second item click or a key repeat would reach it.
  await page.evaluate(() => {
    const item = document.querySelector("#mpu_gift_picker .mpu-gift-picker-item");
    item.click();
    item.click();
  });
  await page.waitForFunction(() => (window.mpuChatHistory || []).some((m) => m.type === "give"), null, { timeout: 60000 });
  await h.typewriterIdle(page);
  await sleep(1000);
  const p = await h.probe(page);
  const gives = p.requests.filter((r) => r.url.includes("touch/give") || r.url.includes("touch%2Fgive"));
  assert(gives.length === 1, `touch/give hit ${gives.length} times`);
  assert(h.fake.chatRequests().length - mark === 1, "provider called more than once");
  const s = await h.state(page);
  assert(s.history.filter((m) => m.type === "give").length === 1, "gift reaction duplicated in history");
  return { gives: gives.length };
});

scenario("sleep-ok-wake-no-animation-stub", "browser (stubbed branch)", async (h) => {
  // No real non-Frieren ghost is configured in this site, so the "no wake
  // animation" branch is reached by stubbing the capability check only.
  h.fake.reset();
  const page = await h.newPage();
  await h.setSiteHourVia(page, ASLEEP_HOUR);
  await h.open(page);
  assert((await h.state(page)).unawokenSleep, "precondition: site must be asleep");
  await page.evaluate(() => { window.mpuCanvasManager.hasWakeUpAnimation = () => false; });
  h.fake.queueReply("……起きた。NOANIM_WAKE_MARK");
  await page.locator("#mpu_ok_btn img").dblclick();
  await page.waitForFunction(() => (window.mpuChatHistory || []).some((m) => m.type === "wake_reaction"), null, { timeout: 90000 });
  await h.typewriterIdle(page);
  const s = await h.state(page);
  const p = await h.probe(page);
  assert(p.requests.filter((r) => r.url.includes("wake-ghost")).length === 1, "double OK sent more than one wake request");
  assert(s.history.filter((m) => m.type === "wake_reaction").length === 1, "wake reaction stored more than once");
  assert(s.msg.includes("NOANIM_WAKE_MARK"), `wake reaction not on screen: "${s.msg}"`);
  assert(s.autoTalkTimer !== null, "auto talk not resumed after waking");
  return { msg: s.msg };
});

scenario("sleep-ok-wake-non-frieren", "browser", async (h) => {
  h.fake.reset();
  const page = await h.newPage();
  await h.setSiteHourVia(page, ASLEEP_HOUR);
  await h.open(page);
  const s0 = await h.state(page);
  const ghost = await page.evaluate(() => ({
    frierenManager: typeof window.mpuFrierenManager !== "undefined" && !!window.mpuFrierenManager.isFrierenMode,
    wakeAnimation: !!(window.mpuCanvasManager && window.mpuCanvasManager.hasWakeUpAnimation && window.mpuCanvasManager.hasWakeUpAnimation()),
    title: (document.getElementById("cur_ukagaka") || {}).dataset?.title || "",
  }));
  assert(!ghost.frierenManager, "precondition: this site must not run the Frieren manager");
  assert(!ghost.wakeAnimation, "precondition: ghost must have no wake animation");
  assert(s0.unawokenSleep, "precondition: site must be asleep and not yet woken");

  const callsBeforeWait = h.fake.chatRequests().length;
  await sleep(AUTO_TALK_INTERVAL_S * 1000 * 2);
  assert(h.fake.chatRequests().length === callsBeforeWait, "provider called while asleep");

  h.fake.queueReply("……おはよう。ASUNA_WAKE_MARK");
  await h.click(page, "#mpu_ok_btn");
  await page.waitForFunction(() => (window.mpuChatHistory || []).some((m) => m.type === "wake_reaction")
    || (window.__mpuProbe.requests.some((r) => r.url.includes("wake-ghost") && r.status !== null)), null, { timeout: 90000 });
  await sleep(500);
  await h.typewriterIdle(page);
  const s1 = await h.state(page);
  const p = await h.probe(page);
  const wakeRequests = p.requests.filter((r) => r.url.includes("wake-ghost")).length;
  assert(wakeRequests === 1, `wake-ghost requested ${wakeRequests} times`);
  assert(!s1.unawokenSleep, "still reported as unawoken after OK");
  assert(s1.autoTalkTimer !== null || p.nextmsgCalls.length > 0, "neither the wake reaction path nor the OK fallback resumed talking");
  const tResume = Date.now();
  await sleep(AUTO_TALK_INTERVAL_S * 1000 * 4);
  const p2 = await h.probe(page);
  const autos = p2.nextmsgCalls.filter((c) => c.trigger === "auto" && c.at >= tResume);
  assert(autos.length >= 1, "auto talk never ticked after waking");
  for (let i = 1; i < autos.length; i++) {
    assert(autos[i].at - autos[i - 1].at >= AUTO_TALK_INTERVAL_S * 1000 * 0.9, "more than one auto-talk chain after waking");
  }
  assert(p2.autoTalkLeaks === 0, `${p2.autoTalkLeaks} auto-talk timer(s) overwritten while pending`);
  return { ghost: ghost.title, wakeReactionStored: s1.history.filter((m) => m.type === "wake_reaction").length, msg: s1.msg, autoTicksAfterWake: autos.length };
}, { ghost: "Asuna" });

// ---------------------------------------------------------------------------
async function runScenario(harness, s) {
  const started = Date.now();
  let status = "PASS";
  let detail = null;
  try {
    detail = await s.fn(harness);
    if (s.knownIssue) {
      status = "XPASS";
      detail = { note: `known issue no longer reproduces: ${s.knownIssue}`, ...detail };
    }
  } catch (error) {
    // A known issue only counts as XFAIL when it fails on its own assertion;
    // any other error (harness, timeout) is a real failure.
    const expected = s.knownIssue && (!s.failsWith || String(error.message).includes(s.failsWith));
    status = expected ? "XFAIL" : "FAIL";
    detail = { error: firstLine(error.message), knownIssue: s.knownIssue };
    detail.evidence = await harness.captureFailure(s.name, error);
  }
  if (harness.currentPage) {
    await harness.currentPage.context().close().catch(() => {});
    harness.currentPage = null;
  }
  const ms = Date.now() - started;
  console.log(`${status.padEnd(5)} [${s.layer}] ${s.name} (${(ms / 1000).toFixed(1)}s)`);
  if (detail) console.log("      " + JSON.stringify(detail));
  return { name: s.name, layer: s.layer, status, ms, detail };
}

async function main() {
  const selected = scenarios.filter((s) => !only || s.name === only || s.name.startsWith(only));
  assert(selected.length > 0, `no scenario matches --only=${only}`);

  const results = [];
  // One disposable site per ghost; the ghost is fixed in the site's options.
  const profiles = [...new Map(selected.map((s) => [profileKey(siteProfile(s)), siteProfile(s)])).values()];
  const fake = await startFakeOllama();
  console.log(`fake ollama: ${fake.url}`);
  // The fake server keeps Node alive, so it must be stopped even when the
  // browser fails to launch (Edge missing, launch error).
  let browser = null;
  try {
    browser = await chromium.launch({ channel: "msedge", headless: !headed });
    const harness = new Harness(null, fake, browser);
    for (const [index, profile] of profiles.entries()) {
      console.log(`booting Playground for ${profileKey(profile)} (first run downloads WordPress)...`);
      harness.site = await startPlayground({
        port: port + index,
        blueprint: blueprintFor(fake.url, profile),
        log: verbose ? (t) => process.stdout.write(t) : () => {},
      });
      console.log(`playground: ${harness.site.url}`);
      try {
        for (const s of selected.filter((x) => profileKey(siteProfile(x)) === profileKey(profile))) {
          results.push(await runScenario(harness, s));
        }
      } finally {
        await harness.site.stop();
      }
    }
  } finally {
    if (browser) await browser.close();
    await fake.stop();
  }

  const failed = results.filter((r) => r.status === "FAIL" || r.status === "XPASS");
  console.log(`
${results.length} scenarios: ` + ["PASS", "FAIL", "XFAIL", "XPASS"]
    .map((k) => `${results.filter((r) => r.status === k).length} ${k}`).join(", "));
  process.exitCode = failed.length > 0 ? 1 : 0;
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
