const assert = require("assert");
const fs = require("fs");
const path = require("path");
const vm = require("vm");

const repoRoot = path.resolve(__dirname, "..", "..");
const baseSource = fs.readFileSync(
  path.join(repoRoot, "js", "ukagaka-base.js"),
  "utf8"
);
const blockStart = baseSource.indexOf("// Initial visual readiness latch");
const blockEnd = baseSource.indexOf("function mpuGetState()", blockStart);

if (blockStart === -1 || blockEnd === -1) {
  throw new Error("Unable to locate visual-ready latch in js/ukagaka-base.js");
}

function loadLatch(timeoutMs = 30) {
  const elements = {
    ukagaka_img: { style: { visibility: "hidden", display: "block" } },
    ukagaka_msgbox: { style: { visibility: "hidden", display: "none" } },
  };
  const events = [];
  const document = {
    getElementById: (id) => elements[id] || null,
    dispatchEvent: (event) => events.push(event),
  };
  function CustomEvent(type, options) {
    this.type = type;
    this.detail = options.detail;
  }
  const context = {
    window: { MPU_VISUAL_READY_TIMEOUT_MS: timeoutMs },
    document,
    CustomEvent,
    Promise,
    Number,
    String,
    setTimeout,
    clearTimeout,
  };
  vm.createContext(context);
  vm.runInContext(baseSource.slice(blockStart, blockEnd), context);
  return { context, elements, events };
}

async function run() {
  let fixture = loadLatch();
  let resolved = false;
  fixture.context.window.mpuWaitForVisualReady().then(() => {
    resolved = true;
  });
  await new Promise((resolve) => setTimeout(resolve, 5));
  assert.strictEqual(resolved, false, "waiter resolved before ready signal");

  fixture.context.window.mpuMarkVisualReady("frieren");
  const firstResult = await fixture.context.window.mpuVisualReadyPromise;
  fixture.context.window.mpuMarkVisualReady("generic-single");
  const duplicateResult = await fixture.context.window.mpuVisualReadyPromise;
  assert.strictEqual(firstResult.source, "frieren");
  assert.strictEqual(duplicateResult.source, "frieren");
  assert.strictEqual(fixture.events.length, 1, "duplicate signal emitted another event");

  fixture = loadLatch(10);
  const timeoutResult = await fixture.context.window.mpuWaitForVisualReady();
  assert.strictEqual(timeoutResult.source, "timeout");
  assert.strictEqual(timeoutResult.timedOut, true);
  assert.strictEqual(fixture.elements.ukagaka_img.style.visibility, "visible");
  assert.strictEqual(fixture.elements.ukagaka_msgbox.style.visibility, "visible");
  assert.strictEqual(
    fixture.elements.ukagaka_msgbox.style.display,
    "none",
    "timeout overrode the visitor's hidden-dialog preference"
  );

  const renderChecks = [
    ["js/ukagaka-core.js", "mpuWaitForVisualReady", "mpuMessageBlocking || mpuAiContextInProgress || mpuGreetInProgress"],
    ["js/ukagaka-greeting.js", "mpuWaitForVisualReady", "mpuAiContextInProgress || mpuMessageBlocking"],
    ["js/ukagaka-context.js", "mpuWaitForVisualReady", "!mpuAiContextInProgress || !mpuMessageBlocking || mpuGreetInProgress"],
  ];
  for (const [relativePath, waitPattern, guardPattern] of renderChecks) {
    const source = fs.readFileSync(path.join(repoRoot, relativePath), "utf8");
    const waitIndex = source.indexOf(waitPattern);
    const guardIndex = source.indexOf(guardPattern, waitIndex);
    assert.ok(waitIndex !== -1, `${relativePath} does not wait for visual readiness`);
    assert.ok(
      guardIndex > waitIndex,
      `${relativePath} does not re-check its competing-flow guard after the wait`
    );
  }

  const greetingSource = fs.readFileSync(
    path.join(repoRoot, "js", "ukagaka-greeting.js"),
    "utf8"
  );
  const featuresSource = fs.readFileSync(
    path.join(repoRoot, "js", "ukagaka-features.js"),
    "utf8"
  );
  const contextSource = fs.readFileSync(
    path.join(repoRoot, "js", "ukagaka-context.js"),
    "utf8"
  );
  assert.ok(
    greetingSource.includes("resolve(false);"),
    "skipped greeting does not report that it was not displayed"
  );
  assert.strictEqual(
    (featuresSource.match(/greetingDisplayed !== false/g) || []).length,
    2,
    "both first-visit cookie paths must preserve retry after a skipped greeting"
  );
  assert.ok(
    contextSource.includes("if (mpuAiContextInProgress) {") &&
      contextSource.includes("mpuSetMessageBlocking(false);") &&
      contextSource.includes("mpuSetAiContextInProgress(false);"),
    "context skip path does not defensively release flags it still owns"
  );

  console.log("visual-ready smoke tests passed");
}

run().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
