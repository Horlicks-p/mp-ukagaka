/**
 * Boots a disposable WordPress Playground with this checkout mounted as the
 * plugin. Every run starts from an empty SQLite site, so tests never touch a
 * real database. The CLI is pinned in tools/node/package.json; later releases
 * refuse to start on Node 24.13.
 */
const fs = require("node:fs");
const os = require("node:os");
const path = require("node:path");
const { spawn } = require("node:child_process");

const root = path.resolve(__dirname, "../../..");
const cliEntry = path.join(root, "tools/node/node_modules/@wp-playground/cli/wp-playground.js");

function startPlayground({ port, wp = "7.1.2", php = "8.3", blueprint, bootTimeoutMs = 240000, log = () => {} }) {
  if (!fs.existsSync(cliEntry)) {
    throw new Error("Playground CLI is missing. Run: npm --prefix tools/node install");
  }

  const workDir = fs.mkdtempSync(path.join(os.tmpdir(), "mpu-e2e-"));
  const blueprintPath = path.join(workDir, "blueprint.json");
  fs.writeFileSync(blueprintPath, JSON.stringify(blueprint, null, 2));

  const args = [
    cliEntry,
    "server",
    `--port=${port}`,
    `--wp=${wp}`,
    `--php=${php}`,
    "--login",
    "--mount-dir", root, "/wordpress/wp-content/plugins/mp-ukagaka",
    `--blueprint=${blueprintPath}`,
  ];
  const child = spawn(process.execPath, args, { cwd: workDir, stdio: ["ignore", "pipe", "pipe"] });

  let output = "";
  const stop = () => new Promise((resolve) => {
    if (child.exitCode !== null) {
      resolve();
      return;
    }
    child.once("exit", () => resolve());
    child.kill();
    setTimeout(() => {
      if (child.exitCode === null) child.kill("SIGKILL");
    }, 5000);
  });

  return new Promise((resolve, reject) => {
    const timer = setTimeout(() => {
      stop();
      reject(new Error(`Playground did not become ready within ${bootTimeoutMs} ms.\n${output}`));
    }, bootTimeoutMs);

    const onData = (chunk) => {
      const text = chunk.toString();
      output += text;
      log(text);
      const match = output.match(/WordPress is running on (http:\/\/\S+)/);
      if (match) {
        clearTimeout(timer);
        child.stdout.off("data", onData);
        child.stderr.off("data", onData);
        resolve({ url: match[1].replace(/\/$/, ""), stop, output: () => output });
      }
    };
    child.stdout.on("data", onData);
    child.stderr.on("data", onData);
    child.once("exit", (code) => {
      clearTimeout(timer);
      reject(new Error(`Playground exited early (code ${code}).\n${output}`));
    });
  });
}

module.exports = { startPlayground, root };
