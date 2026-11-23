/** Use a disposable native Edge profile without Playwright's focus override. */
const fs = require("node:fs");
const os = require("node:os");
const path = require("node:path");
const { spawn } = require("node:child_process");

async function startVisibilityBrowser(chromium) {
  const executable = [process.env["ProgramFiles(x86)"], process.env.ProgramFiles, process.env.LOCALAPPDATA]
    .filter(Boolean).map((base) => path.join(base, "Microsoft/Edge/Application/msedge.exe")).find(fs.existsSync);
  if (!executable) throw Error("Microsoft Edge executable not found for native visibility test");
  const profile = fs.mkdtempSync(path.join(os.tmpdir(), "mpu-visibility-e2e-"));
  const child = spawn(executable, ["--remote-debugging-port=0", `--user-data-dir=${profile}`,
    "--no-first-run", "--no-default-browser-check", "--window-size=1280,900",
    "--disable-background-networking", "about:blank"], { stdio: "ignore", windowsHide: false });
  let browser;
  let spawnError;
  child.once("error", (error) => { spawnError = error; });
  const stop = async () => {
    if (browser) await browser.close().catch(() => {});
    if (child.pid && child.exitCode === null) {
      await new Promise((resolve) => {
        child.once("exit", resolve); child.kill();
        setTimeout(resolve, 5000).unref();
      });
    }
    const resolved = fs.realpathSync(profile), temp = fs.realpathSync(os.tmpdir());
    if (path.dirname(resolved) !== temp || !path.basename(resolved).startsWith("mpu-visibility-e2e-")) throw Error("Unsafe browser profile cleanup path");
    fs.rmSync(resolved, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
  };
  try {
    const activePort = path.join(profile, "DevToolsActivePort");
    const until = Date.now() + 30000;
    while (!fs.existsSync(activePort)) {
      if (spawnError) throw spawnError;
      if (child.exitCode !== null || Date.now() > until) throw Error("Native Edge did not expose DevToolsActivePort");
      await new Promise((resolve) => setTimeout(resolve, 100));
    }
    const [port, endpoint] = fs.readFileSync(activePort, "utf8").trim().split(/\r?\n/);
    // Ordinary Playwright sessions force focus emulation on every page. The
    // public noDefaults option leaves the native default context untouched.
    browser = await chromium.connectOverCDP(`ws://127.0.0.1:${port}${endpoint}`, { noDefaults: true });
    return { browser, context: browser.contexts()[0], stop };
  } catch (error) { await stop(); throw error; }
}

module.exports = { startVisibilityBrowser };
