const assert = require("assert");
const fs = require("fs");
const path = require("path");
const vm = require("vm");

const repoRoot = path.resolve(__dirname, "..", "..");

function loadSelection(nextMode) {
  const source = fs.readFileSync(path.join(repoRoot, "js", "ukagaka-base.js"), "utf8");
  const start = source.indexOf("function mpuShuffleBuiltInBag(");
  const endMarker = "function mpu_handle_error(";
  const end = source.indexOf(endMarker, start);
  if (start < 0 || end < 0) throw new Error("built-in line selection not found in ukagaka-base.js");

  const state = { dialog: {} };
  const context = {
    mpuGetState: () => state,
    mpuNextMode: nextMode,
    Math,
    Number,
    Array,
  };
  vm.createContext(context);
  vm.runInContext(source.slice(start, end), context);
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

// Sequential mode is unchanged.
{
  const ctx = loadSelection("sequential");
  const s = store(3);
  assert.strictEqual(ctx.mpu_selectNextMessage(s, 0), 1);
  assert.strictEqual(ctx.mpu_selectNextMessage(s, 1), 2);
  assert.strictEqual(ctx.mpu_selectNextMessage(s, 2), 0);
}

console.log("Shuffle bag smoke tests passed.");
