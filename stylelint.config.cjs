"use strict";

module.exports = {
  extends: ["stylelint-config-standard"],
  rules: {
    "declaration-no-important": true,
    // Every token is an internal implementation detail (plan section 11.1):
    // there is no public theming API, so the prefix has to say so. Inheriting
    // stylelint-config-standard's plain kebab-case pattern would accept
    // --ink-30 or --mpu-color-ink, and the latter reads as a contract the
    // plugin does not offer. Widen this only alongside a decision to publish.
    "custom-property-pattern": "^mpu-internal-[a-z0-9]+(-[a-z0-9]+)*$",
    "no-descending-specificity": null,
    "property-no-vendor-prefix": null,
    "selector-class-pattern": null,
    "selector-id-pattern": null,
  },
};
