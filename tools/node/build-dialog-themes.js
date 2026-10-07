/**
 * Dialogue colour theme images.
 *
 * The default theme's images/msgbox-*.svg are the source. Each other theme is
 * the same file with every colour swapped through the table below, written to
 * images/dialog-themes/<theme>/<name>.svg. Only colours change; the geometry
 * is never edited by hand.
 *
 *   node tools/node/build-dialog-themes.js          write the theme images
 *   node tools/node/build-dialog-themes.js --check  fail if anything is stale
 *                                                   or unsafe (used by verify)
 *
 * --check also confirms that every source colour has a mapping in every theme,
 * that a theme image differs from its source only in fill/stroke colours and
 * opacity, that no image carries scripts, raster data or external references,
 * and that css/mpu_style.css points each theme at its own four images.
 *
 * The theme colours were derived once from the default palette by role (dark
 * leather, brass, paper, inner line): each keeps its offset in OKLCH lightness,
 * chroma and hue from its role's anchor, moved onto the theme's anchor.
 */
const fs = require("fs");
const path = require("path");

const repoRoot = path.resolve(__dirname, "..", "..");
const imagesDir = path.join(repoRoot, "images");
const outRoot = path.join(imagesDir, "dialog-themes");
const cssFile = path.join(repoRoot, "css", "mpu_style.css");

const IMAGES = {
  frame: "msgbox-frame.svg",
  nameplate: "msgbox-nameplate.svg",
  sparkle: "msgbox-sparkle.svg",
  hexagram: "msgbox-hexagram.svg",
};

// Source colour (default theme) -> theme colour, grouped by role.
const PALETTES = {
  sapphire: {
    // dark leather: frame-dark anchor
    "#2a1d12": "#182530", // outer edge
    "#4a3728": "#2e4150", // inner dark ring
    "#3d2d20": "#263643", // frame band
    "#1e140e": "#101b21", // plate outline
    "#2f2924": "#292e33", // plate fill
    "#1c1713": "#171c20", // hard shadow
    // brass: accent anchor
    "#c69d63": "#8faebe",
    "#b58d58": "#809eac",
    "#97734a": "#69838d",
    "#8a6843": "#607780",
    "#a8824f": "#7692a0",
    "#b29a6e": "#90a4b2", // sparkle rays
    "#e6d3a3": "#c8dbeb", // sparkle core
    // paper and inner line
    "#ecdabe": "#dfe6e4",
    "#c9aa7c": "#a9bcc4",
  },
  crimson: {
    "#2a1d12": "#331c1a",
    "#4a3728": "#553432",
    "#3d2d20": "#472b29",
    "#1e140e": "#241415",
    "#2f2924": "#352a29",
    "#1c1713": "#211817",
    "#c69d63": "#b37868",
    "#b58d58": "#a16a5c",
    "#97734a": "#81534b",
    "#8a6843": "#734943",
    "#a8824f": "#945f53",
    "#b29a6e": "#a07766",
    "#e6d3a3": "#d7ad97",
    "#ecdabe": "#ead9cf",
    "#c9aa7c": "#c99682",
  },
  forest: {
    "#2a1d12": "#1a261f",
    "#4a3728": "#324239",
    "#3d2d20": "#29372f",
    "#1e140e": "#141b15",
    "#2f2924": "#292e2b",
    "#1c1713": "#171b19",
    "#c69d63": "#91a478",
    "#b58d58": "#83936a",
    "#97734a": "#6d7754",
    "#8a6843": "#636c4b",
    "#a8824f": "#788860",
    "#b29a6e": "#899c7e",
    "#e6d3a3": "#bbd4b6",
    "#ecdabe": "#e2e2ce",
    "#c9aa7c": "#aeb58a",
  },
};

const HEX = /#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b/g;
const UNSAFE = [
  [/<script/i, "<script>"],
  [/<image/i, "<image>"],
  [/<foreignObject/i, "<foreignObject>"],
  [/\bhref\s*=/i, "href"],
  [/url\s*\(/i, "url()"],
  [/data:/i, "data: URI"],
  [/\son[a-z]+\s*=/i, "event handler"],
  [/<style/i, "<style>"],
];

function recolor(source, palette, label) {
  return source.replace(HEX, (color) => {
    const key = color.toLowerCase();
    if (!palette[key]) {
      throw new Error(`${label}: no mapping for source colour ${color}`);
    }
    return palette[key];
  });
}

// What must stay identical between a source and its theme copy: everything
// except colour values and opacity values.
function geometryOf(svg) {
  return svg
    .replace(HEX, "#")
    .replace(/(fill-opacity|stroke-opacity|opacity)="[^"]*"/g, '$1=""');
}

function build() {
  const out = {};
  for (const [theme, palette] of Object.entries(PALETTES)) {
    for (const [name, file] of Object.entries(IMAGES)) {
      const source = fs.readFileSync(path.join(imagesDir, file), "utf8");
      out[path.join(outRoot, theme, name + ".svg")] = recolor(source, palette, `${theme}/${name}`);
    }
  }
  return out;
}

function check(outputs) {
  const errors = [];
  const sourceColors = new Set();

  for (const [name, file] of Object.entries(IMAGES)) {
    const source = fs.readFileSync(path.join(imagesDir, file), "utf8");
    for (const [pattern, what] of UNSAFE) {
      if (pattern.test(source)) errors.push(`${file}: contains ${what}`);
    }
    (source.match(HEX) || []).forEach((c) => sourceColors.add(c.toLowerCase()));

    for (const theme of Object.keys(PALETTES)) {
      const target = path.join(outRoot, theme, name + ".svg");
      const rel = path.relative(repoRoot, target).replace(/\\/g, "/");
      if (!fs.existsSync(target)) {
        errors.push(`${rel}: missing (run build-dialog-themes.js)`);
        continue;
      }
      const onDisk = fs.readFileSync(target, "utf8");
      if (onDisk !== outputs[target]) errors.push(`${rel}: stale (run build-dialog-themes.js)`);
      if (geometryOf(onDisk) !== geometryOf(source)) errors.push(`${rel}: geometry differs from ${file}`);
      for (const [pattern, what] of UNSAFE) {
        if (pattern.test(onDisk)) errors.push(`${rel}: contains ${what}`);
      }
      const leftover = (onDisk.match(HEX) || []).filter((c) => sourceColors.has(c.toLowerCase()) && !Object.values(PALETTES[theme]).includes(c.toLowerCase()));
      if (leftover.length) errors.push(`${rel}: still uses default colours ${[...new Set(leftover)].join(", ")}`);
    }
  }

  for (const theme of Object.keys(PALETTES)) {
    const unused = Object.keys(PALETTES[theme]).filter((c) => !sourceColors.has(c));
    if (unused.length) errors.push(`${theme}: mapping for colours no source uses: ${unused.join(", ")}`);
  }

  const css = fs.readFileSync(cssFile, "utf8");
  for (const name of Object.keys(IMAGES)) {
    const prop = `--mpu-internal-dialog-${name}-image`;
    if (!css.includes(`var(${prop})`)) errors.push(`mpu_style.css: ${prop} is never used`);
  }
  for (const file of Object.values(IMAGES)) {
    const uses = css.split(`url("../images/${file}")`).length - 1;
    if (uses !== 1) errors.push(`mpu_style.css: ${file} should appear once (the default token), found ${uses}`);
  }
  for (const theme of Object.keys(PALETTES)) {
    const block = css.match(new RegExp(`#mp_ukagaka\\[data-mpu-dialog-theme="${theme}"\\]\\s*\\{([^}]*)\\}`));
    if (!block) {
      errors.push(`mpu_style.css: no block for theme ${theme}`);
      continue;
    }
    for (const name of Object.keys(IMAGES)) {
      const want = `--mpu-internal-dialog-${name}-image: url("../images/dialog-themes/${theme}/${name}.svg");`;
      if (!block[1].includes(want)) errors.push(`mpu_style.css: ${theme} should set ${want}`);
    }
    const others = (block[1].match(/dialog-themes\/([a-z]+)\//g) || []).filter((m) => m !== `dialog-themes/${theme}/`);
    if (others.length) errors.push(`mpu_style.css: ${theme} points at another theme's images`);
  }

  return errors;
}

const outputs = build();
if (process.argv.includes("--check")) {
  const errors = check(outputs);
  if (errors.length) {
    console.error("Dialogue theme images check failed:\n  " + errors.join("\n  "));
    process.exit(1);
  }
  console.log(`Dialogue theme images OK (${Object.keys(outputs).length} files).`);
} else {
  for (const [file, svg] of Object.entries(outputs)) {
    fs.mkdirSync(path.dirname(file), { recursive: true });
    fs.writeFileSync(file, svg);
  }
  console.log(`Wrote ${Object.keys(outputs).length} dialogue theme images.`);
}
