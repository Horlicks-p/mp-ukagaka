/**
 * Settings-page previews for the dialogue colour themes.
 *
 * Boots a disposable Playground once, shows the character with a fixed line,
 * and for each theme sets data-mpu-dialog-theme on #mp_ukagaka (the same
 * attribute the server writes) and screenshots the dialogue and character to
 * images/dialog-themes/previews/<theme>.png. Rerun after changing the dialogue
 * look or adding a theme:
 *
 *   npm --prefix tools/node run capture:dialog-themes
 *
 * build-dialog-themes.js --check fails if a theme has no preview.
 */
const fs = require("fs");
const path = require("path");
const { chromium } = require("playwright");
const { startPlayground } = require("../e2e/lib/playground");

const repoRoot = path.resolve(__dirname, "..", "..");
const outDir = path.join(repoRoot, "images", "dialog-themes", "previews");
const THEMES = ["default", "sapphire", "crimson", "forest"];
const LINE = "そうだね。もう少しだけ、ここにいようか。魔導書の続きは、また後で読めばいいし。";

// Keep the character awake (sleeping hides the dialogue).
function zoneForLocalHour(hour) {
  let offset = hour - new Date().getUTCHours();
  if (offset > 14) offset -= 24;
  if (offset < -12) offset += 24;
  return offset === 0 ? "UTC" : `Etc/GMT${offset > 0 ? "-" : "+"}${Math.abs(offset)}`;
}

(async () => {
  const php = `<?php
require '/wordpress/wp-load.php';
$o = get_option('mp_ukagaka');
if (!is_array($o) || empty($o)) { $o = mpu_default_opt(); }
$o = array_merge($o, array('ai_enabled' => false, 'auto_talk' => false, 'typewriter_speed' => 10));
update_option('mp_ukagaka', $o);
update_option('timezone_string', '${zoneForLocalHour(15)}');
update_option('gmt_offset', '');
`;
  const site = await startPlayground({
    port: 9540,
    blueprint: {
      landingPage: "/",
      login: true,
      steps: [
        { step: "activatePlugin", pluginPath: "mp-ukagaka/mp-ukagaka.php" },
        { step: "runPHP", code: php },
      ],
    },
  });
  const browser = await chromium.launch({ channel: "msedge" });
  try {
    const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
    await page.goto(site.url + "/", { waitUntil: "networkidle", timeout: 120000 });
    await page.waitForFunction(() => {
      const box = document.getElementById("ukagaka_msgbox");
      return box && getComputedStyle(box).visibility === "visible";
    }, null, { timeout: 60000 });
    await page.waitForTimeout(1500);
    // Only the character and its dialogue, on plain white like the settings card.
    await page.addStyleTag({
      content: "body{background:#fff!important} body>*:not(#mp_ukagaka):not(script){visibility:hidden!important}"
        + " #ukagaka-dock{display:none!important} #ukagaka_msgbox::before{animation:none!important}",
    });
    await page.evaluate((line) => {
      document.querySelectorAll("body *").forEach((el) => {
        if (!el.closest("#mp_ukagaka") && !el.contains(document.getElementById("mp_ukagaka"))) el.style.visibility = "hidden";
      });
      jQuery("#ukagaka_msgbox").css({ display: "", visibility: "visible" });
      jQuery("#ukagaka_msg").stop(true, true).text(line);
    }, LINE);

    fs.mkdirSync(outDir, { recursive: true });
    for (const theme of THEMES) {
      await page.evaluate((t) => { document.getElementById("mp_ukagaka").dataset.mpuDialogTheme = t; }, theme);
      await page.waitForLoadState("networkidle");
      await page.waitForTimeout(600);
      const clip = await page.evaluate(() => {
        const rects = ["ukagaka_msgbox", "ukagaka_img"].map((id) => document.getElementById(id).getBoundingClientRect());
        document.querySelectorAll("#mp_ukagaka [class*='decoration'], #mp_ukagaka [class*='frieren-decor']").forEach((el) => {
          const r = el.getBoundingClientRect();
          if (r.width && r.height) rects.push(r);
        });
        const pad = 12;
        const left = Math.floor(Math.min(...rects.map((r) => r.left))) - pad;
        const top = Math.floor(Math.min(...rects.map((r) => r.top))) - pad;
        const right = Math.ceil(Math.max(...rects.map((r) => r.right))) + pad;
        const bottom = Math.ceil(Math.max(...rects.map((r) => r.bottom))) + pad;
        return { x: Math.max(0, left), y: Math.max(0, top), width: right - Math.max(0, left), height: bottom - Math.max(0, top) };
      });
      const file = path.join(outDir, theme + ".png");
      await page.screenshot({ path: file, clip });
      console.log(`${path.relative(repoRoot, file)} ${clip.width}x${clip.height} ${fs.statSync(file).size} bytes`);
    }
  } finally {
    await browser.close();
    await site.stop();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
