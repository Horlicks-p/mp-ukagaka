"use strict";

/**
 * Trace a pixel-art PNG into an SVG of axis-aligned rectangles.
 *
 * The think bubble and its tail are hand-drawn pixel art. Redrawing them with
 * smooth vector primitives loses the staircase that makes them read as pixel
 * art at all, so instead every source pixel is carried over verbatim: pixels
 * of equal colour are merged into the largest rectangles that tile them, and
 * the result is emitted as one <path> per colour with shape-rendering set to
 * crispEdges. The output therefore rasterises to the source bitmap at 1x and
 * stays hard-edged at any higher device pixel ratio, which is what a PNG
 * cannot do without image-rendering: pixelated.
 *
 * Both source images are drawn at a uniform 75% alpha, flattened. That is
 * reproduced by an outer <g opacity>, so the strokes and the fill under them
 * composite once, exactly as they do in the bitmap.
 *
 * Usage:
 *   node tools/node/trace-pixel-svg.js <png> [--grey N] [--alpha N] [--halo N] [--uri]
 *
 * --grey / --alpha quantise the palette before merging, which trades a little
 * fidelity for far fewer rectangles. --halo N drops pixels outside the outline
 * whose luminance is N or lighter-than-N, removing the baked drop shadow.
 * --uri prints a percent-encoded data URI ready to paste into a CSS url().
 *
 * Kept as a general tool; nothing in the build runs it. The shipped
 * images/think-bubble.svg and think-tail.svg are not this script's output:
 * they were later redrawn as two-colour outlines. The source PNGs are no longer
 * in the tree; main still has them at b102b5f^ (images/think-bubble.png,
 * images/think-tail.png), traced with --grey 8 --alpha 8 --halo 224.
 */

const fs = require("node:fs");
const path = require("node:path");
const { PNG } = require("./node_modules/pngjs");

const BAKED_ALPHA = 191; // both bitmaps are flattened art at 75% alpha

function quantise(value, step, max) {
  if (step <= 1) return value;
  return Math.min(Math.round(value / step) * step, max);
}

/**
 * Flood the region outside the drawing's outline, 4-connected, stopping at any
 * pixel dark enough to be a stroke. Both bitmaps carry a light drop shadow
 * baked outside the outline on the bottom-right; it is invisible on a white
 * page and reads as a white rim on a dark one, so callers drop the exterior
 * pixels lighter than a threshold and keep the darker ones, which are the
 * stroke's own pixel-art shading rather than shadow.
 *
 * inkBelow has to be loose enough to close the outline: the tail's rings are
 * drawn with mid-grey pixels at the diagonals, so a barrier of only the near
 * black pixels leaks into the ring and eats its white centre.
 */
function exteriorMask(lum, alpha, width, height, inkBelow) {
  const isInk = (i) => alpha[i] > 0 && lum[i] < inkBelow;
  const seen = new Uint8Array(width * height);
  const stack = [];
  const push = (x, y) => {
    const i = y * width + x;
    if (!seen[i] && !isInk(i)) {
      seen[i] = 1;
      stack.push(i);
    }
  };
  for (let x = 0; x < width; x += 1) {
    push(x, 0);
    push(x, height - 1);
  }
  for (let y = 0; y < height; y += 1) {
    push(0, y);
    push(width - 1, y);
  }
  while (stack.length > 0) {
    const i = stack.pop();
    const x = i % width;
    const y = (i - x) / width;
    if (x > 0) push(x - 1, y);
    if (x + 1 < width) push(x + 1, y);
    if (y > 0) push(x, y - 1);
    if (y + 1 < height) push(x, y + 1);
  }
  return seen;
}

/** Greedy maximal-rectangle tiling: widest run first, then grown downwards. */
function tile(key, width, height, empty) {
  const used = new Uint8Array(width * height);
  const rects = [];
  for (let y = 0; y < height; y += 1) {
    for (let x = 0; x < width; x += 1) {
      const at = y * width + x;
      if (used[at] || key[at] === empty) continue;
      const k = key[at];
      let x2 = x;
      while (x2 + 1 < width && !used[at + (x2 + 1 - x)] && key[at + (x2 + 1 - x)] === k) x2 += 1;
      let y2 = y;
      grow: while (y2 + 1 < height) {
        const row = (y2 + 1) * width;
        for (let i = x; i <= x2; i += 1) {
          if (key[row + i] !== k || used[row + i]) break grow;
        }
        y2 += 1;
      }
      for (let yy = y; yy <= y2; yy += 1) {
        used.fill(1, yy * width + x, yy * width + x2 + 1);
      }
      rects.push([x, y, x2 - x + 1, y2 - y + 1, k]);
    }
  }
  return rects;
}

function shortHex(level) {
  const hex = level.toString(16).padStart(2, "0");
  return hex[0] === hex[1] ? `#${hex[0].repeat(3)}` : `#${hex.repeat(3)}`;
}

function trim(value) {
  return String(Number(value.toFixed(3)));
}

function trace(file, { grey = 1, alpha = 1, drop = 0, halo = 0, inkBelow = 160 } = {}) {
  const png = PNG.sync.read(fs.readFileSync(file));
  const { width, height, data } = png;
  const lum = new Uint8Array(width * height);
  const opacity = new Uint8Array(width * height);
  for (let i = 0; i < width * height; i += 1) {
    lum[i] = data[i * 4];
    opacity[i] = data[i * 4 + 3];
  }
  const outside = halo > 0 ? exteriorMask(lum, opacity, width, height, inkBelow) : null;
  const key = new Int32Array(width * height);
  for (let i = 0; i < width * height; i += 1) {
    const a = data[i * 4 + 3];
    if (a <= drop || (outside && outside[i] && lum[i] >= halo)) {
      key[i] = -1;
      continue;
    }
    // The art is greyscale, so one channel carries the colour.
    const g = quantise(data[i * 4], grey, 255);
    key[i] = g * 1000 + Math.min(quantise(a, alpha, 255), BAKED_ALPHA);
  }

  const groups = new Map();
  for (const [x, y, w, h, k] of tile(key, width, height, -1)) {
    if (!groups.has(k)) groups.set(k, []);
    groups.get(k).push([x, y, w, h]);
  }

  const body = [...groups.entries()]
    .sort((a, b) => a[0] - b[0])
    .map(([k, rects]) => {
      const fill = shortHex(Math.floor(k / 1000));
      const opacity = Math.min((k % 1000) / BAKED_ALPHA, 1);
      const fade = opacity >= 0.995 ? "" : ` opacity='${trim(opacity)}'`;
      const d = rects
        .map(([x, y, w, h]) => `M${x},${y}h${w}v${h}h-${w}z`)
        .join("");
      return `<path fill='${fill}'${fade} d='${d}'/>`;
    })
    .join("");

  return {
    svg:
      `<svg xmlns='http://www.w3.org/2000/svg' width='${width}' height='${height}'` +
      ` viewBox='0 0 ${width} ${height}' shape-rendering='crispEdges'>` +
      `<g opacity='.75'>${body}</g></svg>`,
    rects: [...groups.values()].reduce((n, r) => n + r.length, 0),
    colours: groups.size,
  };
}

/**
 * Percent-encode only what a CSS url() cannot carry raw. encodeURIComponent
 * would escape every quote, comma and slash too, which roughly doubles the
 * length of a path-heavy drawing for no benefit.
 */
function toDataUri(svg) {
  return `data:image/svg+xml,${svg.replace(/[%<>#"{}|\^`\[\]\s]/g, (ch) =>
    `%${ch.charCodeAt(0).toString(16).toUpperCase().padStart(2, "0")}`,
  )}`;
}

module.exports = { trace, toDataUri };

if (require.main === module) {
  const args = process.argv.slice(2);
  const file = args.find((a) => !a.startsWith("--"));
  if (!file) throw new Error("Usage: node tools/node/trace-pixel-svg.js <png> [--grey N] [--alpha N] [--uri]");
  const flag = (name, fallback) => {
    const i = args.indexOf(`--${name}`);
    return i === -1 ? fallback : Number(args[i + 1]);
  };
  const result = trace(path.resolve(file), {
    grey: flag("grey", 1),
    alpha: flag("alpha", 1),
    drop: flag("drop", 0),
    halo: flag("halo", 0),
    inkBelow: flag("ink-below", 160),
  });
  const out = args.includes("--uri") ? toDataUri(result.svg) : result.svg;
  process.stdout.write(`${out}\n`);
  process.stderr.write(
    `${path.basename(file)}: ${result.rects} rects, ${result.colours} colours, ${out.length} bytes\n`,
  );
}
