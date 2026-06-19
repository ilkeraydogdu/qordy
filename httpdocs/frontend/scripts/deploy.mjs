#!/usr/bin/env node
/**
 * QORDY frontend post-build deploy step.
 *
 * Vite already writes the bundle straight into ../public/app, so this
 * script's only job is to:
 *   1. Sanity-check the output
 *   2. Restore correct Plesk file ownership so PHP can serve the files
 *
 * Run via `npm run deploy` from /frontend.
 */
import { execSync } from "node:child_process";
import { existsSync, readdirSync, statSync } from "node:fs";
import { resolve, dirname } from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = dirname(fileURLToPath(import.meta.url));
const out = resolve(__dirname, "../../public/app");
const indexHtml = resolve(out, "index.html");
const assetsDir = resolve(out, "assets");

if (!existsSync(indexHtml) || !existsSync(assetsDir)) {
  console.error(`✗ Build output missing at ${out}. Did vite build succeed?`);
  process.exit(1);
}

const assetCount = readdirSync(assetsDir).length;
const sizeKb = (statSync(indexHtml).size / 1024).toFixed(2);
console.log(`✓ Bundle ready at ${out}`);
console.log(`  index.html: ${sizeKb} KB`);
console.log(`  assets: ${assetCount} file(s)`);

// Match existing Plesk vhost ownership so apache/php-fpm can serve assets.
const owner = "qordy.com_jckqwoy6r4j:psacln";
try {
  execSync(`chown -R ${owner} ${out}`, { stdio: "inherit" });
  execSync(`chmod -R u+rwX,go+rX ${out}`, { stdio: "inherit" });
  console.log(`✓ Ownership normalised to ${owner}`);
} catch (err) {
  console.warn(`⚠ Could not chown — run as root if needed (${err.message})`);
}

console.log(`\nDeploy complete. React SPA is live at https://qordy.com/`);
