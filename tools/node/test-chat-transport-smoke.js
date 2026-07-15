const fs = require("fs");
const path = require("path");
const vm = require("vm");

const root = path.resolve(__dirname, "../..");

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

function storage() {
  const values = new Map();
  return {
    getItem(key) { return values.has(key) ? values.get(key) : null; },
    setItem(key, value) { values.set(key, String(value)); },
  };
}

function historyContext(sessionStorage, id) {
  const window = {
    crypto: { randomUUID: () => id },
    sessionStorage,
  };
  const context = vm.createContext({
    window,
    mpu_getLocal: () => null,
    mpu_setLocal: () => {},
    mpu_delLocal: () => {},
  });
  vm.runInContext(
    fs.readFileSync(path.join(root, "js/ukagaka-chat-history.js"), "utf8"),
    context,
  );
  return context;
}

async function main() {
  const firstTabStorage = storage();
  const firstTab = historyContext(firstTabStorage, "11111111-1111-4111-8111-111111111111");
  const secondTab = historyContext(storage(), "22222222-2222-4222-8222-222222222222");
  const firstId = vm.runInContext("mpu_getOrCreateChatSessionId()", firstTab);
  const secondId = vm.runInContext("mpu_getOrCreateChatSessionId()", secondTab);
  assert(firstId !== secondId, "separate tabs must not share a checksum session");

  const errors = [];
  const sseWindow = {};
  const sseContext = vm.createContext({
    window: sseWindow,
    AbortController,
    TextDecoder,
    Error,
    fetch: async () => ({
      ok: true,
      headers: { get: () => "text/event-stream" },
      body: { getReader: () => ({ read: async () => ({ done: true }) }) },
    }),
    mpuLogger: { log: () => {} },
  });
  vm.runInContext(
    fs.readFileSync(path.join(root, "js/ukagaka-chat-sse.js"), "utf8"),
    sseContext,
  );
  sseContext.handlers = { onError: (error) => errors.push(error) };
  await vm.runInContext("mpuFetchSSE('/stream', {}, handlers)", sseContext);
  assert(errors.length === 1, "an SSE EOF without done/error must notify the caller");
  assert(errors[0].code === "mpu_sse_incomplete", "an early SSE EOF must be identifiable");

  sseContext.fetch = async () => { throw new Error("network failed"); };
  await vm.runInContext("mpuFetchSSE('/stream', {}, handlers)", sseContext);
  assert(errors.length === 2, "a network failure must notify the caller without rejecting twice");

  console.log("chat transport smoke tests passed");
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
