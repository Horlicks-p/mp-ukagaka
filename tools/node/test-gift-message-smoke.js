/**
 * Gift message attachment smoke test.
 *
 * ghost/Frieren/frieren-interactions.js の giveItem() を vm 上で実行し、
 * 附言の送信・入力欄のライフサイクル・history 書き込みを検証する。
 */

const fs = require("fs");
const path = require("path");
const vm = require("vm");

const root = path.resolve(__dirname, "../..");
const source = fs.readFileSync(
  path.join(root, "ghost/Frieren/frieren-interactions.js"),
  "utf8",
);

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

/**
 * giveItem() を単独で動かせる最小ハーネスを作る。
 *
 * @param {object} options
 * @param {string} options.inputValue チャット入力欄の初期値。
 * @param {Function} options.fetchImpl mpuFetch の実装。
 * @returns {object} manager と観測用の状態。
 */
function createHarness({ inputValue = "", fetchImpl }) {
  const input = {
    value: inputValue,
    events: [],
    dispatchEvent(event) {
      this.events.push(event.type);
      return true;
    },
  };

  const calls = [];
  const manager = {};
  const windowStub = {
    mpuFrierenManager: manager,
    mpuChatHistory: [],
    mpuMessageBlocking: false,
  };

  const context = vm.createContext({
    window: windowStub,
    document: {
      getElementById: (id) => (id === "mpu_user_input" ? input : null),
      querySelectorAll: () => [],
      addEventListener: () => {},
    },
    jQuery: () => ({ is: () => false }),
    FormData,
    Event,
    Error,
    setTimeout,
    mpuAiEnabled: true,
    mpuRestUrl: "https://example.test/wp-json/mp-ukagaka/v1/",
    mpu_getOrCreateChatSessionId: () => "session-abc",
    mpu_getChatHistoryForRequest: () => [],
    mpu_saveChatHistory: () => {},
    mpu_waitForTypewriterComplete: (callback) => callback(),
    mpuFetch: async (url, options) => {
      calls.push({ url, options });
      return fetchImpl(url, options);
    },
  });

  vm.runInContext(source, context);

  return { manager, input, calls, windowStub };
}

function successResponse() {
  return {
    msg: "……ありがとう。",
    user_anchor: "（メルクーアプリンを差し出した）\n発言：旅の途中で見つけたんだ",
  };
}

function formValues(call) {
  const body = call.options.body;
  return {
    item_id: body.get("item_id"),
    session_id: body.get("session_id"),
    history: body.get("history"),
    message: body.get("message"),
    hasMessage: body.has("message"),
  };
}

async function testEmptyMessageKeepsCurrentContract() {
  const harness = createHarness({
    inputValue: "   ",
    fetchImpl: async () => ({ msg: "……", user_anchor: "（メルクーアプリンを差し出した）" }),
  });

  await harness.manager.giveItem("merkur_pudding");

  assert(harness.calls.length === 1, "empty message must still send exactly one give request");
  const sent = formValues(harness.calls[0]);
  assert(!sent.hasMessage, "blank input must not append a message field");
  assert(sent.item_id === "merkur_pudding", "item_id must be sent unchanged");
  assert(sent.session_id === "session-abc", "session_id must be sent unchanged");
  assert(sent.history === "[]", "history must be sent unchanged");
  assert(harness.input.value === "   ", "whitespace-only input must be left untouched");
  assert(harness.input.events.length === 0, "blank input must not fire an input event");
}

async function testMessageIsSentWithTheSameGiveRequest() {
  const harness = createHarness({
    inputValue: "  旅の途中で見つけたんだ  ",
    fetchImpl: async () => successResponse(),
  });

  await harness.manager.giveItem("merkur_pudding");

  assert(harness.calls.length === 1, "gift + message must be a single request");
  assert(
    harness.calls[0].url.endsWith("touch/give"),
    "message must not be routed to /chat/user",
  );
  const sent = formValues(harness.calls[0]);
  assert(sent.message === "旅の途中で見つけたんだ", "message must be trimmed before sending");
  assert(sent.item_id === "merkur_pudding", "item_id must accompany the message");
  assert(harness.input.value === "", "input must be cleared on send");
  assert(harness.input.events.includes("input"), "clearing must notify input listeners");

  const history = harness.windowStub.mpuChatHistory;
  assert(history.length === 2, "one give turn must add exactly two history entries");
  assert(
    history[0].role === "user" &&
      history[0].type === "synthetic" &&
      history[0].content === successResponse().user_anchor,
    "the synthetic anchor must be the backend string, verbatim",
  );
  assert(
    history[1].role === "assistant" && history[1].type === "give",
    "the reply must be stored as a give entry",
  );
}

async function testFailureRestoresTheMessage() {
  const harness = createHarness({
    inputValue: "君にあげる",
    fetchImpl: async () => {
      throw new Error("Failed to fetch");
    },
  });

  await harness.manager.giveItem("merkur_pudding");

  assert(harness.input.value === "君にあげる", "a failed give must not lose the message");
  assert(
    harness.windowStub.mpuChatHistory.length === 0,
    "a failed give must not write history",
  );
}

async function testFailureDoesNotOverwriteNewInput() {
  let harness;
  harness = createHarness({
    inputValue: "君にあげる",
    fetchImpl: async () => {
      // 応答待ちの間にユーザーが新しく入力した状況を再現する。
      harness.input.value = "やっぱりこっち";
      throw new Error("Failed to fetch");
    },
  });

  await harness.manager.giveItem("merkur_pudding");

  assert(
    harness.input.value === "やっぱりこっち",
    "restoring a failed message must never overwrite newer input",
  );
}

async function testConcurrentGiveIsBlocked() {
  const harness = createHarness({
    inputValue: "君にあげる",
    fetchImpl: async () => successResponse(),
  });
  harness.manager.giveItemInProgress = true;

  await harness.manager.giveItem("merkur_pudding");

  assert(harness.calls.length === 0, "an in-flight give must block a second send");
  assert(harness.input.value === "君にあげる", "a blocked give must not touch the input");
}

/**
 * Enter と ✅ ボタンは同じ「送信」操作なので、ピッカーが開いている間は
 * 両方とも贈与でなければならない。片方だけ割り当てると、添え書きを書いて
 * ボタンを押した回だけ品物が消え、台詞が普通のチャットとして飛ぶ。
 *
 * この配線は giveItem() の外側にあり上のハーネスからは届かないので、
 * 意図が消えていないことをソース側で確かめる。
 */
function testBothSendGesturesAreBoundToTheGive() {
  assert(
    /container\.addEventListener\(\s*"keydown",[\s\S]*?event\.key !== "Enter"[\s\S]*?this\.giveItem\(item\.id\);[\s\S]*?true,?\s*\);/.test(
      source,
    ),
    "Enter must be captured before the chat keypress handler and routed to the give",
  );
  assert(
    /document\.addEventListener\(\s*"click",[\s\S]*?closest\("#mpu_ok_btn"\)[\s\S]*?this\.giveItem\(item\.id\);[\s\S]*?true,?\s*\);/.test(
      source,
    ),
    "the ✅ button must be captured while the picker is open and routed to the give",
  );
  // #mpu_ok_btn は #ukagaka_chat_input の外にあるので container 上では捕まらない。
  // capture フェーズでなければ ukagaka-chat-events.js の click ハンドラが先に走る。
  assert(
    !/container\.addEventListener\(\s*"click"/.test(source),
    "the ✅ button lives outside the picker container, so container-level click binding cannot reach it",
  );
}

async function main() {
  const tests = [
    ["empty message keeps the current contract", testEmptyMessageKeepsCurrentContract],
    ["message rides along with the give request", testMessageIsSentWithTheSameGiveRequest],
    ["failure restores the message", testFailureRestoresTheMessage],
    ["failure does not overwrite newer input", testFailureDoesNotOverwriteNewInput],
    ["concurrent give is blocked", testConcurrentGiveIsBlocked],
    ["Enter and the ✅ button both give", testBothSendGesturesAreBoundToTheGive],
  ];

  for (const [name, run] of tests) {
    await run();
    console.log(`  ✓ ${name}`);
  }

  console.log("gift message smoke test passed");
}

main().catch((error) => {
  console.error(`gift message smoke test failed: ${error.message}`);
  process.exit(1);
});
