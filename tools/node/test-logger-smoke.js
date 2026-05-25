const assert = require("assert");
const fs = require("fs");
const path = require("path");
const vm = require("vm");

function loadLogger(l10n, debugMode) {
  const sourcePath = path.resolve(__dirname, "..", "..", "js", "ukagaka-base.js");
  const source = fs.readFileSync(sourcePath, "utf8");
  const start = source.indexOf("const mpuLogger =");
  const end = source.indexOf("// 向後兼容：保留 debugLog 函數", start);

  if (start === -1 || end === -1) {
    throw new Error("Unable to locate mpuLogger block in js/ukagaka-base.js");
  }

  const calls = [];
  const context = {
    window: { mpuL10n: l10n },
    mpuIsDebugMode: () => debugMode,
    console: {
      log: (...args) => calls.push(["log", ...args]),
      warn: (...args) => calls.push(["warn", ...args]),
      error: (...args) => calls.push(["error", ...args]),
      info: (...args) => calls.push(["info", ...args]),
      debug: (...args) => calls.push(["debug", ...args]),
    },
  };

  vm.createContext(context);
  vm.runInContext(
    `${source.slice(start, end)}\nglobalThis.__mpuLogger = mpuLogger;`,
    context
  );

  return { logger: context.__mpuLogger, calls };
}

let fixture = loadLogger(
  {
    logs: { alwaysKey: "always translated", emptyKey: "" },
    logsDebug: { debugKey: "debug translated" },
  },
  true
);

assert.strictEqual(fixture.logger.t("alwaysKey", "fallback"), "always translated");
assert.strictEqual(fixture.logger.t("debugKey", "fallback"), "debug translated");
assert.strictEqual(fixture.logger.t("missingKey", "fallback"), "fallback");
assert.strictEqual(fixture.logger.t("missingNoFallback"), "missingNoFallback");
assert.strictEqual(fixture.logger.t("emptyKey", "fallback"), "");
assert.deepStrictEqual(fixture.calls[0], [
  "debug",
  "[MP Ukagaka i18n missing]",
  "missingKey",
]);
assert.deepStrictEqual(fixture.calls[1], [
  "debug",
  "[MP Ukagaka i18n missing]",
  "missingNoFallback",
]);

fixture = loadLogger({ logs: {}, logsDebug: {} }, true);
assert.strictEqual(fixture.logger.tFormat("key", "fmt %d 秒", 60), "fmt 60 秒");
assert.strictEqual(fixture.logger.tFormat("key", "fmt %s %s", "a", "b"), "fmt a b");
assert.strictEqual(fixture.logger.tFormat("key", "fmt %d", 1, 2, 3), "fmt 1");
assert.strictEqual(fixture.logger.tFormat("key", "fmt %d %d", 1), "fmt 1 ");
assert.strictEqual(
  fixture.logger.tFormat("key", "%2$s は %1$s", "対象", "値"),
  "値 は 対象"
);
assert.strictEqual(
  fixture.logger.tFormat("key", "単価：$100 / %s", "件"),
  "単価：$100 / 件"
);
assert.strictEqual(
  fixture.logger.tFormat("key", "fmt %s %s", [60, "sec"]),
  "fmt 60,sec "
);

fixture = loadLogger({ logs: {}, logsDebug: {} }, true);
fixture.logger.logL("key", "fmt %d 秒", 60);
assert.deepStrictEqual(fixture.calls[1], ["log", "[MP Ukagaka]", "fmt %d 秒", 60]);

fixture = loadLogger({ logs: {}, logsDebug: {} }, false);
fixture.logger.logL("key", "debug gated");
fixture.logger.errorL("key", "always output");
assert.deepStrictEqual(fixture.calls, [["error", "[MP Ukagaka ERROR]", "always output"]]);

console.log("logger smoke tests passed");
