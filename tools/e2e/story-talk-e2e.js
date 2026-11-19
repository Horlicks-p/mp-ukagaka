/** Story Talk Phase 3: real DOM, WordPress REST and a captured fake provider. */
const fs = require("node:fs");
const os = require("node:os");
const path = require("node:path");
const root = path.resolve(__dirname, "../..");

// Written only into the disposable site's VFS, never into the mounted plugin.
const STORY_CONTROL_PHP = `<?php
/**
 * Isolated Story Talk E2E fixture controls.
 *
 * @package MP_Ukagaka
 * @subpackage Tests
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
add_action( 'rest_api_init', static function () {
    register_rest_route( 'mpu-story-e2e/v1', '/control', array(
        'methods' => 'POST',
        'permission_callback' => static function () { return current_user_can( 'manage_options' ); },
        'callback' => static function ( $request ) {
            $keys = array( 'turnstile' => 'mpu_turnstile_block_event', 'akismet' => 'mpu_akismet_spam_event', 'mbb' => 'moelog_bot_blocker_event' );
            if ( 'seed' === $request['operation'] && isset( $keys[ $request['kind'] ] ) ) {
                foreach ( array( 'mpu_turnstile_reaction_cooldown', 'mpu_spam_reaction_cooldown', 'mpu_mbb_reaction_cooldown' ) as $key ) { delete_transient( $key ); }
                foreach ( $keys as $key ) { delete_transient( $key ); }
                set_transient( $keys[ $request['kind'] ], array( 'count' => 2, 'last_time' => current_time( 'timestamp' ) ), 600 );
            }
            if ( 'configure' === $request['operation'] ) {
                $allowed = array_flip( array( 'ai_probability', 'ai_trigger_pages', 'auto_talk_interval', 'external_file_format', 'default_msg' ) );
                $changes = array_intersect_key( (array) $request['changes'], $allowed );
                update_option( 'mp_ukagaka', array_merge( mpu_get_option(), $changes ) );
            }
            return array_map( 'get_transient', $keys );
        },
    ) );
} );
`;

function createStoryFixture() {
  const parent = fs.mkdtempSync(path.join(os.tmpdir(), "mpu-story-e2e-"));
  const target = path.join(parent, "Frieren");
  const dialogs = path.join(parent, "dialogs");
  fs.cpSync(path.join(root, "ghost/Frieren"), target, { recursive: true });
  const restoreDialogs = () => fs.cpSync(path.join(root, "dialogs"), dialogs, { recursive: true });
  restoreDialogs();
  const original = JSON.parse(fs.readFileSync(path.join(target, "stories.json"), "utf8"));
  const baseline = { ...original, enabled: true,
    config: { ...original.config, start_chance: 0 },
    stories: ["a", "b", "c"].map((id) => ({ id: `story_${id}`,
      steps: original.stories[0].steps.map((step, i) => ({ ...step, text: `STORY_${id.toUpperCase()}_${i + 1} ${step.text}` })) })) };
  function write(data = baseline) {
    fs.writeFileSync(path.join(target, "stories.json"), typeof data === "string" ? data : JSON.stringify(data));
  }
  write();
  return { path: target, dialogs, baseline, write, restoreDialogs, cleanup() {
    // Never delete a computed Windows path until its containment is checked.
    const resolved = fs.realpathSync(parent), temp = fs.realpathSync(os.tmpdir());
    if (path.dirname(resolved) !== temp || !path.basename(resolved).startsWith("mpu-story-e2e-")) throw Error("Unsafe fixture cleanup path");
    fs.rmSync(resolved, { recursive: true });
  } };
}

function registerStoryScenarios({ scenario, assert, sleep, outputDir, openLongPost }) {
  const options = { storyFixture: true, integrity: "block", ghost: "FrierenAndAsuna" };
  const reg = (name, fn, debug = false, extra = {}) => {
    scenario(`story-${name}`, "browser + wp (real REST/provider capture)", fn, { ...options, ...extra });
    if (debug) scenario(`story-debug-${name}`, "browser + wp (SCRIPT_DEBUG)", fn, { ...options, scriptDebug: true });
  };
  async function fresh(h, fixture) {
    h.storyFixture.write(fixture);
    h.fake.reset();
    const page = await h.awakePage();
    await h.quietAutoTalk(page);
    await page.evaluate(() => { mpuSetAutoTalkEnabled(true); stopAutoTalk(); });
    page.storyErrorsAt = page.consoleErrors.length;
    h.fake.reset();
    return page;
  }
  async function control(page, data) {
    const result = await page.evaluate(async (data) => {
      const base = mpuRestUrl.replace(/mp-ukagaka\/v1\/?$/, "");
      const res = await fetch(base + "mpu-story-e2e/v1/control", {
        method: "POST", headers: { "Content-Type": "application/json", "X-WP-Nonce": mpuPreSettings.rest_nonce }, body: JSON.stringify(data),
      });
      return { status: res.status, body: await res.json() };
    }, data);
    assert(result.status === 200, `Fixture control failed: ${JSON.stringify(result)}`);
    return result.body;
  }
  async function arm(page, settings = {}) {
    return page.evaluate((settings) => {
      const store = mpuGetDialogStore();
      if (!store || !store.stories) throw Error("Story catalog not loaded by /dialog");
      const data = store.stories;
      data.config.start_chance = 1;
      if (!settings.all) data.stories = data.stories.slice(0, 1);
      if (settings.steps) data.stories[0].steps = settings.steps;
      if (settings.cooldown !== undefined) data.config.cooldown_minutes = settings.cooldown;
      if (settings.speed !== undefined) { mpuTypewriterSpeed = settings.speed; mpuGetState().typewriter.speed = settings.speed; }
      mpuSetOllamaReplaceDialogue(settings.llm === true);
      const key = mpuStoryBagKey(mpuRestUrl, data.personality);
      if (!settings.keepBag) localStorage.removeItem(key);
      return { texts: data.stories[0].steps.map((step) => step.text),
        emojis: data.stories[0].steps.map((step) => step.emoji), key,
        historyAt: window.mpuChatHistory.length, at: Date.now(), config: data.config };
    }, settings);
  }
  async function start(page, settings = {}) {
    const run = await arm(page, settings);
    await page.evaluate(() => mpu_nextmsg("auto"));
    await page.waitForFunction(() => mpuStoryTalkIsPlaying());
    return run;
  }
  const finish = (page, timeout = 30000) => page.waitForFunction(() => !mpuStoryTalkIsPlaying(), null, { timeout });
  const partial = (page) => page.waitForFunction(() => {
    const s = mpuStoryTalk.getStateForDebug();
    return s && s.step === 1 && !s.completedSteps.includes(1) && mpuGetState().typewriter.timer !== null;
  }, null, { timeout: 30000, polling: 20 });
  async function released(h, page) {
    const s = await h.state(page);
    assert(!s.messageBlockOwners.includes("story-talk"), "Story block leaked");
    assert(!await page.evaluate(() => mpuStoryTalkIsPlaying()), "Story still playing");
    assert(page.unhandledErrors.length === 0, `Unhandled browser error: ${page.unhandledErrors.join("; ")}`);
    assert(page.httpErrors.length === 0, `PHP output polluted REST: ${JSON.stringify(page.httpErrors.map((e) => e.body.slice(0, 250)))}`);
    if (!page.allowReportedError) assert(page.consoleErrors.length === page.storyErrorsAt, `Browser error: ${page.consoleErrors.slice(page.storyErrorsAt).join("; ")}`);
    return s;
  }
  async function history(h, page, run, count) {
    const s = await h.state(page);
    const added = s.history.slice(run.historyAt);
    assert(added.length === count * 2, `Expected ${count} pairs, got ${JSON.stringify(added)}`);
    for (let i = 0; i < count; i++) {
      assert(added[2 * i].type === "synthetic" && added[2 * i + 1].type === "auto_talk", "History type mismatch");
      assert(added[2 * i + 1].content === run.texts[i], "History order/text mismatch");
    }
  }
  async function chat(h, page, transport, text) {
    await page.evaluate((streaming) => { window.mpuPreSettings.streaming_enabled = streaming; }, transport === "sse");
    const before = h.fake.chatRequests().length, marker = `STORY_CHAT_${transport.toUpperCase()}_OK`;
    h.fake.queueReply(marker);
    await h.send(page, text); await h.waitChatIdle(page);
    const request = h.fake.chatRequests()[before];
    assert(request && request.body.stream === (transport === "sse"), `${transport} did not reach the actual provider`);
    assert((await h.state(page)).msg.includes(marker), `${transport} was rejected or overwritten (checksum block)`);
    return request.body.messages;
  }

  for (const llm of [false, true]) reg(`whole-${llm ? "llm" : "builtin"}`, async (h) => {
    const page = await fresh(h), run = await arm(page, { llm });
    await page.evaluate(() => { mpuSetBaseAutoTalkInterval(3000); startAutoTalk(); });
    await page.waitForFunction(() => mpuStoryTalkIsPlaying());
    const providerAt = h.fake.chatRequests().length;
    const spamAt = (await h.probe(page)).requests.filter((r) => r.url.includes("check-spam-event")).length;
    fs.mkdirSync(outputDir, { recursive: true });
    const frames = [];
    for (let i = 0; i < 3; i++) {
      await page.waitForFunction((text) => window.mpuChatHistory.some((m) => m.content === text), run.texts[i]);
      await page.waitForTimeout(420);
      assert(await page.locator("#ukagaka_msgbox").isVisible(), "Completed story line is hidden");
      assert(await page.locator("#ukagaka_msg").innerText() === run.texts[i], "Story was overwritten");
      const file = `story-${llm ? "llm" : "builtin"}-${h.site.scriptDebug ? "debug" : "bundle"}-step${i + 1}.png`;
      await page.screenshot({ path: path.join(outputDir, file) }); frames.push(file);
    }
    await finish(page); await history(h, page, run, 3); await released(h, page);
    assert(h.fake.chatRequests().length === providerAt, "Provider called during story");
    const p = await h.probe(page), typed = p.typewriter.filter((t) => run.texts.includes(t.text));
    assert(typed.length === 3, "Missing/duplicate story typing");
    const gaps = typed.slice(1).map((t, i) => t.at - typed[i].completeAt);
    const expected = run.texts.slice(0, 2).map((t) => Math.min(4000, Math.max(1500, 1200 + 50 * Array.from(t).length)));
    gaps.forEach((gap, i) => assert(Math.abs(gap - expected[i]) < 350, `Pause ${gap} != ${expected[i]}`));
    assert(JSON.stringify(p.emoji.filter((e) => e.at >= typed[0].at - 10).slice(0, 3).map((e) => e.emoji)) === JSON.stringify(run.emojis), "Emoji order mismatch");
    assert(p.requests.filter((r) => r.url.includes("check-spam-event")).length === spamAt, "Security checked during story");
    const after = Date.now();
    await page.waitForFunction((after) => __mpuProbe.nextmsgCalls.some((c) => c.at > after && c.trigger === "auto"), after);
    if (llm) await page.waitForFunction(() => !mpuGetState().llm.ollamaRequesting);
    await h.quietAutoTalk(page);
    assert(!llm || h.fake.chatRequests().length > providerAt, "LLM auto talk did not resume");
    return { gaps, expected, frames, providerCallsDuringStory: 0, resumed: true };
  }, true);

  reg("locked-clicks", async (h) => {
    const page = await fresh(h), run = await start(page, { speed: 60 });
    await page.waitForFunction(() => mpuGetState().typewriter.timer !== null);
    const before = await page.evaluate(() => ({ run: mpuGetTypewriterRunId(), touch: JSON.stringify(mpuFrierenManager.touchZoneCooldown),
      counts: JSON.stringify(mpuFrierenManager.touchZoneClicks) }));
    await h.click(page, "#mpu_ok_btn"); await h.click(page, "#mpu_cancel_btn");
    await page.locator("#mpu_gift_picker_button").dispatchEvent("click");
    // Real mouse events through the character capture listener and decoration DOM.
    const bodyClick = await page.evaluate(() => {
      const el = document.querySelector("#frieren_idle_apng") || document.querySelector("#cur_ukagaka");
      const r = el.getBoundingClientRect();
      for (let y = 0.03; y < 0.97; y += 0.05) for (let x = 0.03; x < 0.97; x += 0.05) {
        const event = { clientX: r.left + r.width * x, clientY: r.top + r.height * y };
        if (document.elementFromPoint(event.clientX, event.clientY) === el && !mpuFrierenManager.detectTouchZone(event, el)) return { x: event.clientX, y: event.clientY };
      }
      return null;
    });
    assert(bodyClick, "No non-touch character point found"); await page.mouse.click(bodyClick.x, bodyClick.y);
    const zoneClick = await page.evaluate(() => {
      const el = document.querySelector("#frieren_idle_apng") || document.querySelector("#cur_ukagaka");
      const r = mpuGetCharacterRect(el);
      for (let y = 0.1; y < 0.8; y += 0.08) for (let x = 0.2; x < 0.8; x += 0.1) {
        const event = { clientX: r.left + r.width * x, clientY: r.top + r.height * y };
        const zone = mpuFrierenManager.detectTouchZone(event, el);
        if (zone && document.elementFromPoint(event.clientX, event.clientY) === el) {
          const original = mpuFrierenManager.handleTouchZone;
          window.__storyTouchHits = [];
          mpuFrierenManager.handleTouchZone = function (zone) { __storyTouchHits.push(zone); return original.apply(this, arguments); };
          return { x: event.clientX, y: event.clientY, zone };
        }
      }
      return null;
    });
    assert(zoneClick, "Could not find an opaque touch-zone point");
    await page.mouse.click(zoneClick.x, zoneClick.y);
    assert(await page.evaluate(() => __storyTouchHits.length) === 1, "Mouse did not traverse the touch capture listener");
    const decoration = page.locator('[data-decoration-type], .frieren-decoration, .mpu-decoration').first();
    assert(await decoration.count() > 0, "Decoration hit target not found");
    await decoration.click({ force: true });
    const after = await page.evaluate(() => ({ run: mpuGetTypewriterRunId(), touch: JSON.stringify(mpuFrierenManager.touchZoneCooldown),
      counts: JSON.stringify(mpuFrierenManager.touchZoneClicks), playing: mpuStoryTalkIsPlaying() }));
    assert(after.playing && before.run === after.run, "Click replaced story");
    assert(before.touch === after.touch && before.counts === after.counts, "Interaction consumed cooldown");
    await finish(page); await history(h, page, run, 3); await released(h, page);
    return { characterClicks: true, decorationClick: true, touchZone: zoneClick.zone, providerCalls: h.fake.chatRequests().length };
  }, true);

  for (const action of ["chat", "bubble", "robot", "off", "menu", "contextmenu", "extend", "switch", "spa", "background"]) {
    reg(`interrupt-${action}`, async (h) => {
      const page = await fresh(h), run = await start(page, { speed: 90 });
      await partial(page);
      if (action === "chat") await h.enterChat(page);
      if (action === "bubble") await h.click(page, "#show_msg");
      if (action === "robot") await h.click(page, "#remove");
      if (action === "off") await h.click(page, "#toggleAutoTalk");
      if (action === "menu") { await page.evaluate(() => mpuSetEnableChatMode(false)); await h.click(page, "#mpu_chat_toggle"); }
      if (action === "contextmenu") await page.locator("#frieren_idle_apng:visible, #cur_ukagaka:visible").first().click({ button: "right", force: true });
      if (action === "extend") await h.click(page, "#mpu_extend");
      if (action === "switch") await page.evaluate(() => mpuChange("asuna_e2e"));
      if (action === "spa") await page.evaluate(() => document.dispatchEvent(new CustomEvent("moelogContentSwap")));
      if (action === "background") {
        const other = await page.context().newPage(); await other.goto("about:blank"); await other.bringToFront();
        await page.waitForFunction(() => document.hidden, null, { timeout: 5000 });
        await other.close(); await page.bringToFront();
      }
      await finish(page); await released(h, page); await history(h, page, run, 1);
      await sleep(4500);
      assert(!(await h.probe(page)).typewriter.some((t) => t.text === run.texts[2]), "Cancelled story resumed");
      const bag = await page.evaluate((key) => JSON.parse(localStorage.getItem(key)), run.key);
      assert(bag.drawn.length === 1 && bag.cooldownUntil > Date.now(), "Interrupt lost consumption/cooldown");
      if (action === "bubble") { await h.click(page, "#show_msg"); assert(await page.locator("#ukagaka_msgbox").isVisible(), "Bubble did not reopen"); }
      if (action === "robot") { await h.click(page, "#remove"); assert(await page.locator("#ukagaka").isVisible(), "Robot did not reopen"); }
      if (action === "off") {
        assert((await h.state(page)).autoTalkTimer === null, "OFF left timer");
        await h.click(page, "#toggleAutoTalk"); assert((await h.state(page)).autoTalkTimer !== null, "ON did not resume");
      }
      return { completedSteps: 1, cancelledPartialStep: true, consumed: bag.drawn, cooldown: true };
    }, ["chat", "spa"].includes(action), { requiresHeaded: action === "background" });
  }

  reg("history-json-sse", async (h) => {
    const page = await fresh(h), run = await start(page);
    await finish(page); await history(h, page, run, 3); await h.enterChat(page);
    const received = {};
    for (const transport of ["json", "sse"]) {
      const messages = await chat(h, page, transport, "剛剛在說什麼？");
      for (const text of run.texts) assert(messages.some((m) => m.content.includes(text)), `${transport} provider missing completed segment`);
      received[transport] = messages.map((m) => ({ role: m.role, content: m.content })).filter((m) => run.texts.some((t) => m.content.includes(t)));
    }
    await released(h, page); return { integrity: "block", received };
  }, true);

  reg("partial-history-sse", async (h) => {
    const page = await fresh(h), run = await start(page, { speed: 90 });
    await partial(page); await h.enterChat(page); await history(h, page, run, 1);
    const messages = await chat(h, page, "sse", "寶箱故事剛剛說到哪？");
    const content = messages.map((m) => m.content).join("\n");
    assert(content.includes(run.texts[0]) && !content.includes(run.texts[1]) && !content.includes(run.texts[2]), "Provider received unspoken steps");
    return { integrity: "block", completedOnly: true };
  }, true);

  reg("after-two-history-json", async (h) => {
    const page = await fresh(h), run = await start(page);
    await page.waitForFunction((text) => mpuChatHistory.some((m) => m.content === text), run.texts[1]);
    await h.enterChat(page); await history(h, page, run, 2);
    const content = (await chat(h, page, "json", "剛剛說了什麼？")).map((m) => m.content).join("\n");
    assert(content.includes(run.texts[0]) && content.includes(run.texts[1]) && !content.includes(run.texts[2]), "Wrong story prefix sent");
    return { integrity: "block", completedSteps: 2 };
  });

  reg("reload-consumed-cooldown", async (h) => {
    const page = await fresh(h), run = await start(page, { speed: 90 });
    await partial(page); const saved = await page.evaluate((key) => localStorage.getItem(key), run.key);
    await page.reload({ waitUntil: "load" }); await h.open(page); await h.quietAutoTalk(page);
    await page.evaluate(() => mpuSetAutoTalkEnabled(true));
    const again = await arm(page, { keepBag: true });
    await page.evaluate(() => mpu_nextmsg("auto"));
    assert(!await page.evaluate(() => mpuStoryTalkIsPlaying()), "F5 ignored cooldown");
    const before = JSON.parse(saved), after = await page.evaluate((key) => JSON.parse(localStorage.getItem(key)), again.key);
    assert(JSON.stringify(before.drawn) === JSON.stringify(after.drawn) && before.lastDrawn === after.lastDrawn && after.cooldownUntil >= before.cooldownUntil, "F5 reset bag/cooldown");
    await released(h, page); return { consumedAcrossReload: true, cooldownPreserved: true, visibilityEndRefreshMs: after.cooldownUntil - before.cooldownUntil };
  });

  reg("bag-three-rounds", async (h) => {
    const page = await fresh(h); await arm(page, { all: true, cooldown: 0 });
    const ids = await page.evaluate(async () => {
      const ids = [];
      for (let i = 0; i < 9; i++) {
        mpu_nextmsg("auto"); ids.push(mpuStoryTalk.getStateForDebug().storyId); mpuStoryTalk.cancel("test-abort");
        // Recreate the player, forcing each draw to read persisted storage.
        window.mpuStoryTalk = mpuCreateStoryTalk();
      }
      return ids;
    });
    for (let i = 0; i < 9; i += 3) assert(new Set(ids.slice(i, i + 3)).size === 3, "Repeated story within round");
    assert(ids[2] !== ids[3] && ids[5] !== ids[6], "Repeated across round boundary");
    await released(h, page); return { ids, abortedStoriesConsumed: true };
  });

  for (const kind of ["disabled", "bad-json", "blocked-storage"]) reg(`degrade-${kind}`, async (h) => {
    const fixture = JSON.parse(JSON.stringify(h.storyFixture.baseline));
    if (kind === "disabled") fixture.enabled = false;
    const page = await fresh(h, kind === "bad-json" ? "{bad" : fixture);
    if (kind === "blocked-storage") {
      await page.evaluate(() => {
        const get = Storage.prototype.getItem, set = Storage.prototype.setItem;
        Storage.prototype.getItem = function (key) { if (key.startsWith("mpu_story_bag_")) throw Error("denied"); return get.call(this, key); };
        Storage.prototype.setItem = function (key, value) { if (key.startsWith("mpu_story_bag_")) throw Error("denied"); return set.call(this, key, value); };
      });
      const run = await start(page); await finish(page); await history(h, page, run, 3);
    } else {
      assert(await page.evaluate(() => mpuGetDialogStore().stories === null), "PHP did not reject fixture");
      await page.evaluate(() => mpu_nextmsg("auto")); await h.typewriterIdle(page);
      assert(!await page.evaluate(() => mpuStoryTalkIsPlaying()), "Invalid catalog played");
    }
    await released(h, page); return { fallbackSafe: true };
  });

  for (const kind of ["builtin-fade", "builtin-animation", "first-line", "llm-animation"]) reg(`late-${kind}`, async (h) => {
    const page = await fresh(h);
    if (kind === "builtin-fade") {
      await page.evaluate(() => { mpuSetOllamaReplaceDialogue(false); mpu_nextmsg(); });
      await page.waitForTimeout(150);
    } else if (kind === "first-line") {
      await page.evaluate(() => loadExternalDialog("Frieren.txt", false));
      await page.waitForResponse((r) => r.url().includes("/dialog?"));
      await page.waitForTimeout(120);
    } else {
      await page.evaluate((llm) => {
        mpuSetOllamaReplaceDialogue(llm);
        const trigger = mpuCanvasManager.triggerCharacterAnimation;
        mpuCanvasManager.triggerCharacterAnimation = function (force, callback) {
          if (typeof callback === "function") { window.__lateStoryCallback = callback; return true; }
          return trigger.apply(this, arguments);
        };
        mpu_nextmsg();
      }, kind === "llm-animation");
      await page.waitForFunction(() => typeof __lateStoryCallback === "function" && !mpuGetState().llm.ollamaRequesting);
    }
    const run = await start(page), before = await page.evaluate(() => ({ num: jQuery("#ukagaka_msgnum").html(),
      bag: JSON.stringify(mpuGetState().dialog.builtInBag), history: mpuChatHistory.length }));
    if (kind.includes("animation")) await page.evaluate(() => __lateStoryCallback());
    await page.waitForTimeout(1300);
    assert(await page.evaluate(() => mpuStoryTalkIsPlaying()), "Old callback cancelled story");
    const after = await page.evaluate(() => ({ num: jQuery("#ukagaka_msgnum").html(), bag: JSON.stringify(mpuGetState().dialog.builtInBag) }));
    assert(before.num === after.num && before.bag === after.bag, "Old callback consumed built-in index/bag");
    await finish(page); await history(h, page, run, 3); await released(h, page);
    return { staleDisplayDiscarded: true, bagAndIndexUnchanged: true };
  });

  for (const result of ["success", "failure", "invalid"]) reg(`late-extend-${result}`, async (h) => {
    const page = await fresh(h);
    let release; const gate = new Promise((resolve) => { release = resolve; });
    let captured; const responseReady = new Promise((resolve) => { captured = resolve; });
    await page.route("**/mp-ukagaka/v1/extend", async (route) => {
      const response = result === "success" ? await route.fetch() : null;
      if (response) assert((await response.json()).label, "Success fixture did not receive a valid real extend response");
      captured();
      await gate;
      if (result === "success") await route.fulfill({ response });
      else await route.fulfill({ status: result === "failure" ? 500 : 200, contentType: "application/json", body: "{}" });
    });
    try {
      await h.click(page, "#mpu_extend");
      await responseReady;
      const run = await start(page);
      await page.waitForFunction(() => mpuGetState().typewriter.timer !== null);
      const id = await page.evaluate(() => mpuGetTypewriterRunId());
      page.allowReportedError = result === "failure";
      release(); await page.waitForTimeout(1800);
      assert(await page.evaluate(() => mpuStoryTalkIsPlaying()), "Extend displaced story");
      assert(await page.evaluate(() => mpuGetTypewriterRunId()) === id, "Extend replaced Typewriter");
      assert(await page.evaluate(() => document.body.style.cursor) === "auto", "Extend left busy cursor");
      await finish(page); await history(h, page, run, 3); await released(h, page);
      return { staleResultIgnored: result, cursorRestored: true };
    } finally { release(); }
  });

  reg("spa-pending-real-context", async (h) => {
    const page = await fresh(h); await openLongPost(h, page); await h.quietAutoTalk(page);
    await page.evaluate(() => { mpuSetAutoTalkEnabled(true); stopAutoTalk(); sessionStorage.removeItem("mpu_context_last_shown"); });
    await arm(page, { llm: true });
    h.fake.reset(); h.fake.setReplyFor((n, body) => JSON.stringify(body).includes("E2E 長文テスト") ? "STORY_SPA_CONTEXT_OK" : "OTHER_AUTO_LINE");
    await page.evaluate(() => {
      window.mpuSettings.ai_enabled = true; window.mpuSettings.ai_trigger_pages = "is_single,is_page"; window.mpuSettings.ai_probability = 100;
      document.dispatchEvent(new CustomEvent("moelogContentSwap"));
    });
    const saved = await page.evaluate(() => {
      const key = mpuStoryBagKey(mpuRestUrl, mpuGetDialogStore().stories.personality);
      const before = localStorage.getItem(key); mpu_nextmsg("auto");
      return { before, after: localStorage.getItem(key), playing: mpuStoryTalkIsPlaying(), pending: mpuIsContextPending() };
    });
    assert(saved.pending && !saved.playing && saved.before === saved.after, "Story started during SPA pending");
    await page.waitForFunction(() => __mpuProbe.requests.some((r) => r.url.includes("chat/context")));
    await page.waitForFunction(() => mpuChatHistory.some((m) => m.type === "context" && m.content.includes("STORY_SPA_CONTEXT_OK")));
    assert(!await page.evaluate(() => mpuIsContextPending()), "SPA pending leaked");
    assert(h.fake.chatRequests().some((r) => JSON.stringify(r.body).includes("E2E 長文テスト")), "Real context did not reach provider");
    return { pendingProtectedBag: true, realContextProvider: true };
  }, true);

  for (const spaDelay of [500, 2500]) reg(`startup-spa-owners-${spaDelay}`, async (h) => {
    const page = await fresh(h);
    await control(page, { operation: "configure", changes: { ai_probability: 100, ai_trigger_pages: "is_home,is_front_page", auto_talk_interval: 3 } });
    await page.addInitScript((spaDelay) => {
      document.addEventListener("DOMContentLoaded", () => {
        const original = mpuSetContextPending;
        window.__storyPending = [];
        window.mpuSetContextPending = function (value, owner = "startup") {
          original(value, owner);
          const pending = mpuIsContextPending(), store = mpuGetDialogStore();
          let blocked = null;
          if (!value && pending && store?.stories) {
            store.stories.config.start_chance = 1;
            blocked = !mpuStoryTalk.tryStart(store);
            store.stories.config.start_chance = 0;
          }
          __storyPending.push({ owner, value, pending, blocked, at: Date.now() });
          if (value && owner === "startup") setTimeout(() => document.dispatchEvent(new CustomEvent("moelogContentSwap")), spaDelay);
        };
      });
    }, spaDelay);
    try {
      await page.goto(h.site.url + "/", { waitUntil: "load" });
      await page.waitForFunction(() => (__storyPending || []).filter((e) => !e.value).length >= 2);
      const events = await page.evaluate(() => __storyPending);
      const releases = events.filter((e) => !e.value);
      assert(releases[0].pending && releases[0].blocked !== false, `First owner cleared the other: ${JSON.stringify(events)}`);
      assert(!releases[1].pending, `Owners leaked: ${JSON.stringify(events)}`);
      assert(releases[0].owner === (spaDelay === 500 ? "spa" : "startup"), "Wrong overlap ordering exercised");
      return { events };
    } finally { await control(page, { operation: "configure", changes: { ai_trigger_pages: "", ai_probability: 0 } }); }
  });

  for (const kind of ["turnstile", "akismet", "mbb"]) reg(`security-retained-${kind}`, async (h) => {
    const page = await fresh(h), run = await start(page, { llm: true });
    await control(page, { operation: "seed", kind });
    const providerAt = h.fake.chatRequests().length;
    await page.evaluate(() => { mpuSetBaseAutoTalkInterval(3000); startAutoTalk(); });
    await page.waitForTimeout(3500);
    const during = await control(page, { operation: "inspect" });
    assert(during[kind] && during[kind].count === 2, "Transient consumed during story");
    assert(h.fake.chatRequests().length === providerAt, "Security called provider during story");
    await finish(page); await history(h, page, run, 3);
    await page.waitForFunction(() => mpuChatHistory.some((m) => m.type === "event"), null, { timeout: 30000 });
    const after = await control(page, { operation: "inspect" });
    assert(after[kind] === false, "Transient not consumed after story");
    assert(h.fake.chatRequests().length > providerAt, "Event never reached provider");
    return { retainedDuringStory: true, consumedAfterStory: true, event: kind };
  });

  reg("history-over-40-block", async (h) => {
    const page = await fresh(h);
    await page.evaluate(() => {
      for (let i = 0; i < 25; i++) mpuChatHistory.push(
        { role: "user", content: "（独り言）", type: "synthetic", timestamp: Date.now() },
        { role: "assistant", content: "OLDER_MONOLOGUE_" + i, type: "auto_talk", timestamp: Date.now() });
      mpu_saveChatHistory();
    });
    const run = await start(page); await finish(page);
    const bounds = await page.evaluate(() => ({ memory: mpuChatHistory.length, saved: mpu_getLocal("mpu_chat_history").length, request: mpu_getChatHistoryForRequest().length }));
    assert(bounds.memory > 40 && bounds.saved === 40 && bounds.request === 40, "History storage/request window changed");
    await h.enterChat(page);
    for (const transport of ["json", "sse"]) {
      const messages = await chat(h, page, transport, "最近的故事？");
      assert(run.texts.every((text) => messages.some((m) => m.content.includes(text))), "Truncation lost recent story");
    }
    return { integrity: "block", rawHistoryCap: 40, recentStoryPreserved: true };
  });

  for (const action of ["chat", "bubble", "switch"]) reg(`short-fade-${action}`, async (h) => {
    const page = await fresh(h), run = await start(page, { speed: 10,
      steps: ["a", "bb", "ccc"].map((text) => ({ text, emoji: null })) });
    await page.waitForFunction(() => mpuChatHistory.some((m) => m.type === "auto_talk" && m.content === "a"), null, { polling: 10 });
    const opacity = await page.locator("#ukagaka_msgbox").evaluate((el) => Number(getComputedStyle(el).opacity));
    assert(opacity < 1, "Did not exercise the initial fade-in window");
    if (action === "chat") await h.enterChat(page);
    if (action === "bubble") await h.click(page, "#show_msg");
    if (action === "switch") await page.evaluate(() => mpuChange("asuna_e2e"));
    await finish(page); await page.waitForTimeout(2000); await history(h, page, run, 1); await released(h, page);
    return { initialOpacity: opacity, completedBeforeInterruption: true, partialFadeHistoryRetained: true };
  });

  reg("cancel-before-first-typing", async (h) => {
    const page = await fresh(h), run = await start(page);
    await h.enterChat(page); await page.waitForTimeout(1800);
    await history(h, page, run, 0); await released(h, page);
    assert(!(await h.probe(page)).typewriter.some((t) => run.texts.includes(t.text)), "Story typed after early chat handoff");
    return { storyTypewriterCalls: 0 };
  });

  reg("switch-dialog-failure", async (h) => {
    const page = await fresh(h); await start(page, { speed: 90 }); await partial(page);
    await page.route((url) => url.href.includes("/dialog?") && url.searchParams.get("cur_num") === "asuna_e2e", (route) =>
      route.fulfill({ status: 500, contentType: "application/json", body: JSON.stringify({ error: "E2E dialog failure" }) }));
    page.allowReportedError = true;
    await page.evaluate(() => mpuSetEnableChatMode(false)); await h.click(page, "#mpu_chat_toggle");
    await page.locator(".ukagaka-list a", { hasText: "アスナ" }).click();
    await page.waitForFunction(() => mpuCharacterGeneration > 0);
    await page.waitForTimeout(2500);
    assert(!await page.evaluate(() => mpuStoryTalkIsPlaying()), "Old character story resumed");
    assert(await page.evaluate(() => !mpuGetDialogStore()?.stories || mpuGetDialogStore().storiesGeneration !== mpuCharacterGeneration), "Old catalog remained eligible");
    await released(h, page); return { switchedViaMenu: true, failedDialogCannotReviveOldStory: true };
  });

  for (const kind of ["empty-json", "invalid-json", "empty-txt"]) reg(`fallback-${kind}`, async (h) => {
    const admin = await fresh(h), format = kind.endsWith("txt") ? "txt" : "json";
    await control(admin, { operation: "configure", changes: { external_file_format: format } });
    fs.writeFileSync(path.join(h.storyFixture.dialogs, `Frieren.${format}`), kind === "empty-json" ? '{"messages":[]}' : kind === "invalid-json" ? "{bad" : "");
    try {
      const page = await h.awakePage(); page.allowReportedError = true; page.storyErrorsAt = 0;
      await h.quietAutoTalk(page);
      const response = await page.evaluate(async (format) => {
        const res = await mpuFetch(mpuRestUrl + "dialog?file=Frieren." + format + "&cur_num=default_1").catch((error) => ({ error: error.message }));
        mpuSetAutoTalkEnabled(true); mpu_nextmsg("auto");
        return { error: !!res.error, stories: !!mpuGetDialogStore()?.stories, playing: mpuStoryTalkIsPlaying() };
      }, format);
      assert(response.error && !response.playing && !response.stories, `Invalid fallback allowed Story catalog: ${JSON.stringify(response)}`);
      await released(h, page); return { dialogRejected: true, storyNotStarted: true };
    } finally {
      h.storyFixture.restoreDialogs();
      await control(admin, { operation: "configure", changes: { external_file_format: "txt" } });
      await admin.context().close();
    }
  });

  reg("interval-independent", async (h) => {
    const page = await fresh(h), results = [];
    for (const interval of [3000, 30000]) {
      await page.evaluate((interval) => mpuSetBaseAutoTalkInterval(interval), interval);
      const run = await start(page, { cooldown: 0 }); await finish(page);
      const typed = (await h.probe(page)).typewriter.filter((t) => t.at >= run.at && run.texts.includes(t.text));
      results.push(typed.slice(1).map((t, i) => t.at - typed[i].completeAt));
    }
    results[0].forEach((gap, i) => assert(Math.abs(gap - results[1][i]) < 350, "Auto Talk interval changed Story pause"));
    await released(h, page); return { intervalsMs: [3000, 30000], gaps: results };
  });

  reg("slow-max-story", async (h) => {
    const page = await fresh(h);
    const steps = Array.from({ length: 5 }, (_, i) => ({ text: String(i + 1) + "長".repeat(119), emoji: null }));
    const run = await start(page, { speed: 200, steps });
    await finish(page, 210000); await history(h, page, run, 5); await released(h, page);
    return { steps: 5, charsPerStep: 120, speed: 200, elapsedMs: Date.now() - run.at };
  });

  reg("watchdog-recovery", async (h) => {
    const page = await fresh(h);
    await page.evaluate(() => {
      window.__storyOriginalTypewriter = mpu_typewriter;
      window.mpu_typewriter = function (text, target, speed, options) {
        return __storyOriginalTypewriter(text, target, speed, options && { ...options, onComplete: undefined });
      };
    });
    await start(page); await finish(page, 20000); await released(h, page);
    await page.evaluate(() => { window.mpu_typewriter = __storyOriginalTypewriter; });
    await h.click(page, "#mpu_ok_btn"); await h.typewriterIdle(page);
    assert(!(await h.state(page)).messageBlocking, "OK still blocked after watchdog");
    return { watchdogReleasedBlock: true, okRecovered: true };
  });
}

module.exports = { createStoryFixture, registerStoryScenarios, STORY_CONTROL_PHP };
