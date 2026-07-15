const fs = require("fs");
const path = require("path");

const root = path.resolve(__dirname, "../..");

function read(relativePath) {
  return fs.readFileSync(path.join(root, relativePath), "utf8");
}

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

const historySource = read("js/ukagaka-chat-history.js");
assert(
  historySource.includes("const MPU_MAX_CHAT_HISTORY = 40"),
  "chat persistence window must remain 40 raw entries"
);
assert(
  historySource.includes("function mpu_getChatHistoryForRequest()"),
  "shared request-history helper is missing"
);

const requestSources = [
  "js/ukagaka-chat-send.js",
  "js/ukagaka-core.js",
  "js/ukagaka-context.js",
  "js/ukagaka-greeting.js",
  "ghost/Frieren/frieren-interactions.js",
];
for (const relativePath of requestSources) {
  assert(
    read(relativePath).includes("mpu_getChatHistoryForRequest"),
    `${relativePath} does not use the shared request-history window`
  );
}

const integritySource = read("includes/llm/chat-integrity.php");
assert(
  integritySource.includes("array_slice($normalized, -40)"),
  "backend checksum raw window does not match frontend persistence"
);

const restChatSource = read("includes/rest/class-mpu-rest-chat.php");
assert(
  restChatSource.includes("verify($chat_session_id, $integrity_history)"),
  "chat integrity is still verified after the 20-entry LLM context slice"
);

console.log("chat integrity window smoke tests passed");
