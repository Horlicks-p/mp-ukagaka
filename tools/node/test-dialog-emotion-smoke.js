const assert = require("assert");
const fs = require("fs");
const path = require("path");
const vm = require("vm");

const repoRoot = path.resolve(__dirname, "..", "..");

function deferred() {
  let resolve;
  let reject;
  const promise = new Promise((res, rej) => {
    resolve = res;
    reject = rej;
  });
  return { promise, resolve, reject };
}

function response(data) {
  return { ok: true, json: () => Promise.resolve(data) };
}

function loadDisplayHelpers() {
  const source = fs.readFileSync(path.join(repoRoot, "js", "ukagaka-core.js"), "utf8");
  const start = source.indexOf("function mpuGetCurrentUkagakaNum()");
  const endMarker = "window.mpuRandomBuiltInIndex = mpuRandomBuiltInIndex;";
  const end = source.indexOf(endMarker, start) + endMarker.length;
  if (start < 0 || end < endMarker.length) throw new Error("dialog display helpers not found");

  const calls = [];
  const manager = {
    cleanup: () => calls.push(["cleanup"]),
    showEmoji: (name) => calls.push(["show", name]),
  };
  const context = {
    window: { mpuEmojiManager: manager },
    document: { getElementById: () => ({ textContent: "default_1" }) },
    mpu_unescapeHTML: (value) => `clean:${value}`,
    mpu_typewriter: (...args) => calls.push(["typewriter", ...args]),
    Math,
    String,
    Object,
    Array,
  };
  vm.createContext(context);
  vm.runInContext(source.slice(start, end), context);
  return { context, calls };
}

function createEmojiRuntime() {
  const source = fs.readFileSync(path.join(repoRoot, "js", "ukagaka-emoji.js"), "utf8");
  const requests = [];
  const appended = [];
  let curNum = "a";
  const container = {
    appendChild: (element) => {
      element.parentNode = container;
      appended.push(element);
    },
    removeChild: (element) => {
      const index = appended.indexOf(element);
      if (index >= 0) appended.splice(index, 1);
      element.parentNode = null;
    },
    querySelector: () => null,
    getBoundingClientRect: () => ({ top: 0, left: 0, width: 100, height: 100 }),
  };
  const document = {
    readyState: "loading",
    addEventListener: () => {},
    getElementById: (id) => (id === "ukagaka_img" ? container : null),
    querySelector: () => null,
    createElement: () => ({ style: {}, dataset: {}, parentNode: null }),
  };
  const window = {
    addEventListener: () => {},
    mpuGetCurrentUkagakaNum: () => curNum,
  };
  const context = {
    window,
    document,
    mpuRestUrl: "https://example.test/wp-json/mp-ukagaka/v1/",
    mpuRestNonce: "nonce",
    mpuLogger: { log: () => {}, logL: () => {}, logF: () => {}, warn: () => {}, warnF: () => {} },
    fetch: (url) => {
      const request = deferred();
      requests.push({ url, request });
      return request.promise;
    },
    URLSearchParams,
    Promise,
    Error,
    setTimeout: () => 1,
    clearTimeout: () => {},
  };
  vm.createContext(context);
  vm.runInContext(source, context);
  return {
    context,
    requests,
    appended,
    setCurNum: (value) => { curNum = value; },
  };
}

function assertDisplaySiteInventory() {
  const jsDir = path.join(repoRoot, "js");
  const files = fs.readdirSync(jsDir).filter((name) => name.endsWith(".js"));
  const direct = [];
  let helperCalls = 0;
  for (const file of files) {
    const source = fs.readFileSync(path.join(jsDir, file), "utf8");
    helperCalls += (source.match(/mpuDisplayBuiltInMessage\s*\(/g) || []).length;
    const lines = source.split(/\r?\n/);
    lines.forEach((line, index) => {
      if (/(?:store|dialogStore)\.msg\s*\[|msgArr\s*\[|resp\.msg\s*\[/.test(line)) {
        direct.push(`${file}:${index + 1}:${line.trim()}`);
      }
    });
  }
  assert.strictEqual(direct.length, 1, `dialog-store indexing bypasses the shared helper:\n${direct.join("\n")}`);
  assert.match(direct[0], /^ukagaka-core\.js:\d+:const output = mpu_unescapeHTML\(String\(store\.msg\[index\]/);
  assert.strictEqual(helperCalls, 13, "expected one helper definition plus exactly twelve physical display call sites");
}

async function run() {
  const display = loadDisplayHelpers();
  const store = { msg: ["hello"], msg_emojis: ["laugh.png"], auto_msg: "!" };
  const shown = display.context.mpuDisplayBuiltInMessage(store, 0);
  assert.strictEqual(shown, "clean:hello!");
  assert.deepStrictEqual(display.calls.slice(0, 3), [
    ["cleanup"],
    ["show", "laugh.png"],
    ["typewriter", "clean:hello!", "#ukagaka_msg", null, undefined],
  ]);

  display.calls.length = 0;
  display.context.mpuDisplayBuiltInMessage({ msg: ["plain"] }, 0);
  assert.deepStrictEqual(display.calls[0], ["cleanup"]);
  assert.strictEqual(display.calls.some((call) => call[0] === "show"), false);

  display.calls.length = 0;
  const missing = display.context.mpuDisplayBuiltInMessage({ msg: ["plain"] }, 4);
  assert.strictEqual(missing, "");
  assert.deepStrictEqual(display.calls, [
    ["cleanup"],
    ["typewriter", "", "#ukagaka_msg", null, undefined],
  ], "an invalid index must clear stale text and emoji state");
  assertDisplaySiteInventory();

  let runtime = createEmojiRuntime();
  const sameA = runtime.context.window.loadEmojiConfig("a");
  const sameB = runtime.context.window.loadEmojiConfig("a");
  assert.strictEqual(sameA, sameB, "same character did not reuse the in-flight request");
  assert.strictEqual(runtime.requests.length, 1);
  runtime.requests[0].request.resolve(response({ success: true, baseUrl: "/a/", personalityId: "A" }));
  await sameA;
  assert.strictEqual(runtime.context.window.mpuEmojiConfig.baseUrl, "/a/");

  runtime = createEmojiRuntime();
  const oldRequest = runtime.context.window.loadEmojiConfig("a");
  runtime.setCurNum("b");
  runtime.context.window.invalidateEmojiConfig();
  const newRequest = runtime.context.window.loadEmojiConfig("b");
  runtime.requests[1].request.resolve(response({ success: true, baseUrl: "/b/", personalityId: "B" }));
  await newRequest;
  runtime.requests[0].request.resolve(response({ success: true, baseUrl: "/a/", personalityId: "A" }));
  await oldRequest;
  assert.strictEqual(runtime.context.window.mpuEmojiConfig.baseUrl, "/b/", "stale config overwrote current character");

  runtime = createEmojiRuntime();
  const failed = runtime.context.window.loadEmojiConfig("a");
  runtime.requests[0].request.reject(new Error("offline"));
  await assert.rejects(failed, /offline/);
  const retry = runtime.context.window.loadEmojiConfig("a");
  assert.strictEqual(runtime.requests.length, 2, "failed request could not be retried");
  runtime.requests[1].request.resolve(response({ success: true, baseUrl: "/a/" }));
  await retry;

  runtime = createEmojiRuntime();
  runtime.context.window.mpuEmojiManager.showEmoji("laugh.png");
  runtime.context.window.mpuEmojiManager.cleanup();
  runtime.requests[0].request.resolve(response({ success: true, baseUrl: "/a/" }));
  await runtime.requests[0].request.promise;
  await Promise.resolve();
  assert.strictEqual(runtime.appended.length, 0, "cleaned-up pending emoji rendered later");

  console.log("dialog emotion smoke tests passed");
}

run().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
