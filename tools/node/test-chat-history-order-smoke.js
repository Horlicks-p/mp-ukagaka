/**
 * Guards where a chat reply lands in history and how a failed turn is undone.
 *
 * A reply can arrive after other entries were appended (the exit line pushed 5 s
 * after leaving chat, or a gift sent while waiting), and reopening chat swaps
 * window.mpuChatHistory for a copy read back from storage. These cases decide
 * whether the reply stays next to the turn it answers.
 */
const fs = require("fs");
const path = require("path");
const vm = require("vm");

const root = path.resolve(__dirname, "../..");

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

function load() {
  let stored = null;
  const context = vm.createContext({
    window: { crypto: { randomUUID: () => "00000000-0000-4000-8000-000000000000" }, sessionStorage: null },
    mpu_getLocal: () => stored,
    mpu_setLocal: (key, value) => { stored = JSON.parse(JSON.stringify(value)); },
    mpu_delLocal: () => { stored = null; },
  });
  vm.runInContext(fs.readFileSync(path.join(root, "js/ukagaka-chat-history.js"), "utf8"), context);
  return context;
}

function roles(context) {
  return context.window.mpuChatHistory.map((m) => `${m.role}:${m.content}`).join(" | ");
}

// 1. Reply after other entries: inserted right after its user turn.
{
  const c = load();
  const user = { role: "user", content: "question", type: "chat", timestamp: 1 };
  c.window.mpuChatHistory = [user];
  c.window.mpuChatHistory.push({ role: "user", content: "（独り言）", type: "synthetic", timestamp: 2 });
  c.window.mpuChatHistory.push({ role: "assistant", content: "exit line", type: "auto_talk", timestamp: 3 });
  c.mpu_insertChatReply(user, { role: "assistant", content: "answer", type: "chat", timestamp: 4 });
  assert(roles(c) === "user:question | assistant:answer | user:（独り言） | assistant:exit line",
    `reply not placed after its turn: ${roles(c)}`);
}

// 2. History replaced by a storage copy (reopening chat): matched by content.
{
  const c = load();
  const user = { role: "user", content: "question", type: "chat", timestamp: 10 };
  c.window.mpuChatHistory = [user, { role: "user", content: "（独り言）", type: "synthetic", timestamp: 11 }];
  c.mpu_saveChatHistory();
  c.mpu_loadChatHistory();
  assert(c.window.mpuChatHistory[0] !== user, "precondition: reload must produce new objects");
  c.mpu_insertChatReply(user, { role: "assistant", content: "answer", type: "chat", timestamp: 12 });
  assert(roles(c) === "user:question | assistant:answer | user:（独り言）",
    `reply not placed after its turn once the array was replaced: ${roles(c)}`);
}

// 3. User turn gone (trimmed by the 40-entry cap): appended rather than lost.
{
  const c = load();
  const user = { role: "user", content: "question", type: "chat", timestamp: 20 };
  c.window.mpuChatHistory = [{ role: "user", content: "other", type: "chat", timestamp: 21 }];
  c.mpu_insertChatReply(user, { role: "assistant", content: "answer", type: "chat", timestamp: 22 });
  assert(roles(c) === "user:other | assistant:answer", `reply lost when its turn is gone: ${roles(c)}`);
}

// 4. Rolling back a failed turn removes that turn, not whatever is last.
{
  const c = load();
  const user = { role: "user", content: "question", type: "chat", timestamp: 30 };
  c.window.mpuChatHistory = [user,
    { role: "user", content: "（独り言）", type: "synthetic", timestamp: 31 },
    { role: "assistant", content: "exit line", type: "auto_talk", timestamp: 32 }];
  c.mpu_removeChatHistoryEntry(user);
  assert(roles(c) === "user:（独り言） | assistant:exit line", `wrong entry rolled back: ${roles(c)}`);
  c.mpu_removeChatHistoryEntry(user);
  assert(c.window.mpuChatHistory.length === 2, "rolling back twice removed another entry");
}

console.log("chat history order smoke tests passed");
