import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { existsSync, lstatSync, readFileSync, readdirSync, realpathSync, statSync, writeFileSync } from 'node:fs';
import { dirname, isAbsolute, join, relative, resolve, sep } from 'node:path';
import { test } from 'node:test';
import { buildRuntime, runtimeFiles } from '../scripts/build-runtime.mjs';

const root = resolve('build/runtime');

function packageFiles(directory = root) {
  return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const full = join(directory, entry.name);
    assert.ok(!entry.isSymbolicLink(), `Symlink in runtime package: ${full}`);
    return entry.isDirectory() ? packageFiles(full) : [relative(root, full).split(sep).join('/')];
  }).sort();
}

function fingerprint() {
  return packageFiles().map((file) => {
    const full = join(root, file);
    const digest = createHash('sha256').update(readFileSync(full)).digest('hex');
    return [file, digest, statSync(full).mtimeMs];
  });
}

test('runtime package contains only reviewed site files and cannot escape its root', () => {
  buildRuntime();
  assert.deepEqual(packageFiles(), runtimeFiles);
  for (const file of packageFiles()) {
    const full = resolve(root, file);
    const within = relative(root, full);
    assert.ok(within && within !== '..' && !within.startsWith(`..${sep}`) && !isAbsolute(within), file);
    assert.ok(lstatSync(full).isFile(), file);
    assert.equal(realpathSync(full), full, file);
  }
  for (const file of [
    '.github/workflows/ci.yml', 'docs/development.md', 'tests/site.test.mjs',
    'scripts/check.mjs', 'AGENTS.md', 'README.md', 'Dockerfile', 'compose.yaml',
    'package.json', 'package-lock.json', '.env.example', '.dockerignore', '.gitignore',
  ]) assert.ok(!existsSync(join(root, file)), `${file} was packaged`);
});

test('public entries, references, and literal runtime dependencies are packaged', () => {
  for (const file of [
    '.htaccess', 'index.php', 'calculators/index.php',
    ...['premium-item-checker', 'hellfire-item-price', 'shop-qlvl', 'warrior-repair', 'hellfire-damage']
      .map((name) => `calculators/${name}/index.php`),
    'guides/index.php', 'guides/template/index.php', 'guides/shopping/index.php',
    'guides/fast-character-development/index.php', 'guides/max-shopping-video/index.php',
    'shopqlvl/index.php',
    'reference/jarulf162.pdf', 'reference/d1-hf-shrines.pdf',
    'reference/hellfire-shopping-differences.pdf',
    'images/shopping-guide-header.png', 'videos/warlord-of-blood.mp4',
    'favicon.ico', 'uvicon-32x32.png', 'uvicon-48x48.png',
  ]) assert.ok(existsSync(join(root, file)), `Missing public file: ${file}`);

  for (const file of runtimeFiles) {
    if (!/\.(?:php|css|mjs)$/.test(file)) continue;
    const content = readFileSync(join(root, file), 'utf8');
    if (file.endsWith('.php')) {
      for (const match of content.matchAll(/\b(?:site_url|asset_version)\s*\(\s*['"]([^'"]+)['"]/g)) {
        const path = match[1].replace(/^\//, '');
        const target = path.endsWith('/') ? `${path}index.php` : path;
        assert.ok(existsSync(join(root, target)), `${file} refers to missing ${target}`);
      }
      for (const match of content.matchAll(/['"]((?:css|js|calculators|guides|images|videos|reference)\/[^'"]+\.(?:css|js|mjs|png|mp4|pdf|ico))['"]/g)) {
        assert.ok(existsSync(join(root, match[1])), `${file} refers to missing ${match[1]}`);
      }
    }
    if (file.endsWith('.mjs')) {
      for (const match of content.matchAll(/\bfrom\s+['"](\.[^'"]+\.mjs)['"]/g)) {
        const dependency = resolve(root, dirname(file), match[1]);
        assert.ok(existsSync(dependency), `${file} imports missing ${match[1]}`);
      }
    }
    if (file.endsWith('.css')) {
      for (const match of content.matchAll(/url\(\s*['"]?([^)'"?]+)/g)) {
        const path = match[1];
        if (/^(?:data:|https?:|\/\/|#)/.test(path)) continue;
        assert.ok(existsSync(resolve(root, dirname(file), path)), `${file} refers to missing ${path}`);
      }
    }
  }
});

test('rebuilding removes stale files and reproduces the same package', () => {
  const first = fingerprint();
  writeFileSync(join(root, 'stale-file.txt'), 'must not survive');
  buildRuntime();
  assert.ok(!existsSync(join(root, 'stale-file.txt')));
  assert.deepEqual(fingerprint(), first);
});

test('a failed build removes the previous package', () => {
  runtimeFiles.push('missing-runtime-file.php');
  try {
    assert.throws(() => buildRuntime(), /Missing or unsafe runtime file/);
    assert.ok(!existsSync(root));
  } finally {
    runtimeFiles.pop();
    buildRuntime();
  }
});
