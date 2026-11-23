const assert = require("assert");
const fs = require("fs");
const path = require("path");
const vm = require("vm");

const repoRoot = path.resolve(__dirname, "..", "..");

function slice(file, startMarker, endMarker) {
  const source = fs.readFileSync(path.join(repoRoot, "js", file), "utf8");
  const start = source.indexOf(startMarker);
  const end = source.indexOf(endMarker, start);
  if (start < 0 || end < 0) throw new Error(`${startMarker} not found in ${file}`);
  return source.slice(start, end);
}

// msgnum: what #ukagaka_msgnum holds (null = element missing).
function loadSelection(nextMode, msgnum = null) {
  const state = { dialog: {} };
  const context = {
    mpuGetState: () => state,
    mpuNextMode: nextMode,
    document: {
      getElementById: (id) => (id === "ukagaka_msgnum" && msgnum !== null ? { textContent: msgnum } : null),
    },
    Math,
    Number,
    Array,
    parseInt,
  };
  vm.createContext(context);
  vm.runInContext(slice("ukagaka-base.js", "function mpuShuffleBuiltInBag(", "function mpu_handle_error("), context);
  vm.runInContext(slice("ukagaka-core.js", "function mpuRandomBuiltInIndex(", "window.mpuGetCurrentUkagakaNum ="), context);
  return context;
}

function store(length) {
  return { msg: Array.from({ length }, (_, i) => `line ${i}`) };
}

// Every round says each line exactly once.
{
  const ctx = loadSelection("random");
  const s = store(7);
  for (let round = 0; round < 200; round++) {
    const seen = new Set();
    for (let i = 0; i < 7; i++) seen.add(ctx.mpuDrawBuiltInIndex(s));
    assert.strictEqual(seen.size, 7, `round ${round} repeated a line`);
  }
}

// No line twice in a row, including where one round ends and the next begins.
for (const length of [2, 3, 5]) {
  const ctx = loadSelection("random");
  const s = store(length);
  let previous = ctx.mpuDrawBuiltInIndex(s);
  for (let i = 0; i < 5000; i++) {
    const next = ctx.mpuDrawBuiltInIndex(s);
    assert.notStrictEqual(next, previous, `length ${length}: same line twice in a row`);
    previous = next;
  }
}

// One line or none.
{
  const ctx = loadSelection("random");
  const one = store(1);
  for (let i = 0; i < 5; i++) assert.strictEqual(ctx.mpuDrawBuiltInIndex(one), 0);
  assert.strictEqual(ctx.mpuDrawBuiltInIndex(store(0)), -1);
  assert.strictEqual(ctx.mpuDrawBuiltInIndex(null), -1);
  assert.strictEqual(ctx.mpuDrawBuiltInIndex({ msg: "x" }), -1);
}

// New dialogue data (character switch, reload) starts a new bag and avoids the line on screen.
{
  const ctx = loadSelection("random");
  const first = store(4);
  ctx.mpuDrawBuiltInIndex(first);
  ctx.mpuDrawBuiltInIndex(first);

  for (let i = 0; i < 500; i++) {
    const replaced = store(4);
    const onScreen = i % 4;
    assert.notStrictEqual(ctx.mpu_selectNextMessage(replaced, onScreen), onScreen);
    const rest = new Set([0, 1, 2]
      .map(() => ctx.mpuDrawBuiltInIndex(replaced)));
    assert.strictEqual(rest.size, 3, "a replaced store must get a fresh, full round");
  }
}

// A store whose line count changed in place also starts over instead of returning stale indexes.
{
  const ctx = loadSelection("random");
  const s = store(5);
  ctx.mpuDrawBuiltInIndex(s);
  s.msg.length = 2;
  for (let i = 0; i < 20; i++) assert.ok(ctx.mpuDrawBuiltInIndex(s) < 2);
}

// mpuRandomBuiltInIndex (greeting / page-aware / leaving chat) avoids the line on screen
// when it is the first draw, e.g. default_msg = 1 showed line 0 on load.
for (const shown of ["0", "1", "2"]) {
  for (let i = 0; i < 300; i++) {
    const ctx = loadSelection("random", shown);
    assert.notStrictEqual(ctx.mpuRandomBuiltInIndex(store(3)), Number(shown), `wrapper repeated on-screen line ${shown}`);
  }
}
{
  const ctx = loadSelection("random", "0");
  for (let i = 0; i < 200; i++) assert.strictEqual(loadSelection("random", "0").mpuRandomBuiltInIndex(store(2)), 1);
  // It draws from the same bag as auto talk.
  const s = store(4);
  const seen = new Set([ctx.mpuRandomBuiltInIndex(s), ctx.mpu_selectNextMessage(s, 0), ctx.mpuDrawBuiltInIndex(s), ctx.mpuRandomBuiltInIndex(s)]);
  assert.strictEqual(seen.size, 4, "wrapper and auto talk must share one round");
}
// No usable msgnum: still draws normally.
for (const shown of [null, "", "abc", "9"]) {
  const ctx = loadSelection("random", shown);
  const index = ctx.mpuRandomBuiltInIndex(store(3));
  assert.ok(index >= 0 && index < 3, `msgnum ${JSON.stringify(shown)} broke the draw`);
}

// Sequential mode is unchanged.
{
  const ctx = loadSelection("sequential");
  const s = store(3);
  assert.strictEqual(ctx.mpu_selectNextMessage(s, 0), 1);
  assert.strictEqual(ctx.mpu_selectNextMessage(s, 1), 2);
  assert.strictEqual(ctx.mpu_selectNextMessage(s, 2), 0);
}

console.log("Shuffle bag smoke tests passed.");
