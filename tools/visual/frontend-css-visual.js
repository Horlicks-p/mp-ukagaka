"use strict";

const fs = require("node:fs/promises");
const path = require("node:path");
const { pathToFileURL } = require("node:url");
const { chromium } = require("../node/node_modules/playwright");
const { PNG } = require("../node/node_modules/pngjs");

const mode = process.argv[2];
if (!new Set(["baseline", "compare"]).has(mode)) {
  throw new Error("Usage: node tools/visual/frontend-css-visual.js <baseline|compare>");
}

const root = path.resolve(__dirname, "../..");
const outputRoot = path.resolve(
  root,
  process.env.MPU_VISUAL_OUTPUT || "output/playwright/frontend-css",
);
const url = process.env.MPU_VISUAL_URL || "http://127.0.0.1/wordpress/";
const theme = (process.env.MPU_VISUAL_THEME || "local-theme").replace(
  /[^a-z0-9_-]+/gi,
  "-",
);
const viewport = { width: 1440, height: 900 };
const baselineDir = path.join(outputRoot, "baseline", theme);
const currentDir = path.join(outputRoot, "current", theme);
const diffDir = path.join(outputRoot, "diff", theme);
const targetDir = mode === "baseline" ? baselineDir : currentDir;
const masks = [
  "#ukagaka_img",
  ".frieren-decoration",
  ".frieren-emoji",
  ".mpu-thinking",
];

const states = [
  ["normal", () => {}],
  [
    "chat",
    () => {
      const messageBox = document.querySelector("#ukagaka_msgbox");
      const chatInput = document.querySelector("#ukagaka_chat_input");
      messageBox?.classList.add("chat-mode");
      if (messageBox) messageBox.style.display = "flex";
      if (chatInput) chatInput.style.display = "flex";
    },
  ],
  [
    "gift",
    () => {
      document.querySelector(".mpu-gift-picker")?.removeAttribute("hidden");
    },
  ],
  [
    "think-system",
    () => {},
  ],
  [
    "canvas-only",
    () => {
      document
        .querySelectorAll(".frieren-decoration, #frieren_idle_apng")
        .forEach((element) => {
          element.style.visibility = "hidden";
        });
    },
  ],
];

async function preparePage(page) {
  await page.goto(url, { waitUntil: "domcontentloaded" });
  await page.evaluate(async () => {
    if (window.mpuVisualReadyPromise) await window.mpuVisualReadyPromise;
    if (document.fonts?.ready) await document.fonts.ready;
    const lastTimer = window.setTimeout(() => {}, 0);
    for (let timer = 0; timer <= lastTimer; timer += 1) {
      window.clearTimeout(timer);
      window.clearInterval(timer);
      window.cancelAnimationFrame(timer);
    }
    const root = document.querySelector("#mp_ukagaka");
    let branch = root;
    while (branch?.parentElement) {
      const parent = branch.parentElement;
      [...parent.children]
        .filter((element) => element !== branch)
        .forEach((element) => element.remove());
      if (parent === document.body) break;
      branch = parent;
    }
  });
  await page.addStyleTag({
    content: `
      *, *::before, *::after {
        animation-delay: 0s !important;
        animation-duration: 0s !important;
        caret-color: transparent !important;
        transition: none !important;
      }
      html, body {
        background: #101418 !important;
      }
      #ukagaka_msgbox {
        opacity: 1 !important;
        visibility: visible !important;
      }
      html { cursor: none !important; }
    `,
  });
}

async function capture(page, name, setup) {
  await preparePage(page);
  if (name === "gift") {
    await page.locator("#mpu_gift_picker").waitFor({ state: "attached" });
  }
  await page.evaluate(setup);
  await page.waitForTimeout(name === "chat" ? 350 : 50);
  await page.evaluate((stateName) => {
    if (document.activeElement instanceof HTMLElement) {
      document.activeElement.blur();
    }
    const messageBox = document.querySelector("#ukagaka_msgbox");
    messageBox?.classList.remove("mpu-main-bubble-dimmed");
    if (messageBox) messageBox.style.opacity = "1";
    const message = document.querySelector("#ukagaka_msg");
    if (message) message.textContent = "MP Ukagaka visual baseline";
    const bubble = document.querySelector("#ukagaka_think");
    if (bubble && stateName !== "think-system") {
      bubble.hidden = true;
      bubble.style.display = "none";
      bubble.classList.remove("is-visible");
    }
    if (bubble && stateName === "think-system") {
      bubble.hidden = false;
      bubble.style.display = "block";
      bubble.classList.add("is-visible");
      bubble.textContent = "Visual thinking";
      const spinner = document.createElement("span");
      spinner.className = "mpu-thinking";
      bubble.append(spinner);
    }
    if (stateName === "gift") {
      const picker = document.querySelector("#mpu_gift_picker");
      if (picker) {
        picker.hidden = false;
        picker.style.display = "block";
      }
    }
  }, name);

  const geometry = await page.evaluate(() => {
    const rectangle = (element) => {
      if (!element) return null;
      const value = element.getBoundingClientRect();
      return {
        x: value.x,
        y: value.y,
        width: value.width,
        height: value.height,
      };
    };
    const selectors = [
      "#ukagaka_shell",
      "#ukagaka-dock",
      "#ukagaka_img",
      "#ukagaka_msgbox",
      "#ukagaka_msg",
      "#ukagaka_think",
      ".mpu-gift-picker",
    ];
    return Object.fromEntries(
      selectors.map((selector) => [
        selector,
        rectangle(document.querySelector(selector)),
      ]),
    );
  });

  const maskLocators = [];
  for (const selector of masks) {
    const locator = page.locator(selector);
    if ((await locator.count()) > 0) maskLocators.push(locator);
  }
  const clip = await page.evaluate(() => {
    const elements = [
      "#ukagaka_shell",
      "#ukagaka_msgbox",
      "#ukagaka_think",
      ".mpu-gift-picker",
    ]
      .map((selector) => document.querySelector(selector))
      .filter((element) => {
        if (!element) return false;
        const style = getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        return style.display !== "none" && rect.width > 0 && rect.height > 0;
      });
    const rectangles = elements.map((element) => element.getBoundingClientRect());
    const padding = 4;
    const left = Math.max(0, Math.floor(Math.min(...rectangles.map((r) => r.left))) - padding);
    const top = Math.max(0, Math.floor(Math.min(...rectangles.map((r) => r.top))) - padding);
    const right = Math.min(
      innerWidth,
      Math.ceil(Math.max(...rectangles.map((r) => r.right))) + padding,
    );
    const bottom = Math.min(
      innerHeight,
      Math.ceil(Math.max(...rectangles.map((r) => r.bottom))) + padding,
    );
    return { x: left, y: top, width: right - left, height: bottom - top };
  });
  await page.screenshot({
    path: path.join(targetDir, `${name}.png`),
    caret: "hide",
    clip,
    mask: maskLocators,
    fullPage: false,
  });
  return geometry;
}

async function compareImages(pixelmatch, name) {
  const expectedPath = path.join(baselineDir, `${name}.png`);
  const actualPath = path.join(currentDir, `${name}.png`);
  const [expected, actual] = await Promise.all([
    fs.readFile(expectedPath).then((buffer) => PNG.sync.read(buffer)),
    fs.readFile(actualPath).then((buffer) => PNG.sync.read(buffer)),
  ]);
  if (expected.width !== actual.width || expected.height !== actual.height) {
    throw new Error(`${name}: screenshot dimensions differ`);
  }
  const diff = new PNG({ width: expected.width, height: expected.height });
  const changed = pixelmatch(
    expected.data,
    actual.data,
    diff.data,
    expected.width,
    expected.height,
    { threshold: 0, includeAA: true },
  );
  await fs.writeFile(path.join(diffDir, `${name}.png`), PNG.sync.write(diff));
  return changed;
}

async function main() {
  await fs.mkdir(targetDir, { recursive: true });
  if (mode === "compare") await fs.mkdir(diffDir, { recursive: true });

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport,
    deviceScaleFactor: 1,
    colorScheme: "light",
    reducedMotion: "no-preference",
  });
  const page = await context.newPage();
  const geometry = {};
  try {
    for (const [name, setup] of states) {
      geometry[name] = await capture(page, name, setup);
    }
  } finally {
    await browser.close();
  }

  const metadata = {
    url,
    theme,
    viewport,
    deviceScaleFactor: 1,
    colorScheme: "light",
    reducedMotion: "no-preference",
    animationFreeze: "CSS duration 0s plus cleared page timers",
    masks,
    pixelThresholdPerChannel: 0,
    maximumDifferentPixels: 0,
    maximumDifferentPixelRatio: 0,
    geometry,
  };
  await fs.writeFile(
    path.join(targetDir, "metadata.json"),
    `${JSON.stringify(metadata, null, 2)}\n`,
  );

  if (mode === "compare") {
    const pixelmatchPath = path.join(
      root,
      "tools/node/node_modules/pixelmatch/index.js",
    );
    const { default: pixelmatch } = await import(
      pathToFileURL(pixelmatchPath).href
    );
    const failures = [];
    for (const [name] of states) {
      const changed = await compareImages(pixelmatch, name);
      if (changed !== 0) failures.push(`${name}: ${changed} pixels`);
    }
    if (failures.length > 0) {
      throw new Error(`Visual differences found:\n${failures.join("\n")}`);
    }
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
