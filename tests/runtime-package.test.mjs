import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { existsSync, lstatSync, mkdirSync, readFileSync, readdirSync, realpathSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { dirname, isAbsolute, join, relative, resolve, sep } from 'node:path';
import { test } from 'node:test';
import { applicationFiles, buildRuntime, generatedFiles, privateDirectories, runtimeFiles } from '../scripts/build-runtime.mjs';

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

// Established independently of the builder: the committed PHP files of the
// application directories, straight from Git.
function committedApplicationPhp() {
  return execFileSync('git', ['ls-files', '-z', '--', 'src', 'templates', 'migrations'], { encoding: 'utf8' })
    .split('\0').filter((file) => file.endsWith('.php'));
}

test('runtime package contains only reviewed site files and cannot escape its root', () => {
  buildRuntime();
  const files = packageFiles();
  const expected = [...new Set([...runtimeFiles, ...committedApplicationPhp(), ...generatedFiles])].sort();
  assert.deepEqual(applicationFiles(), [...new Set([...runtimeFiles, ...committedApplicationPhp()])].sort());
  assert.deepEqual(files.filter((file) => !file.startsWith('vendor/') || file === 'vendor/.htaccess'), expected);
  const tracked = new Set(execFileSync('git', ['ls-files', '-z'], { encoding: 'utf8' }).split('\0'));
  for (const file of files) {
    if (file.startsWith('vendor/') || generatedFiles.includes(file)) continue;
    assert.ok(tracked.has(file), `${file} is packaged but not tracked by Git`);
  }
  const vendor = files.filter((file) => file.startsWith('vendor/'));
  assert.ok(vendor.includes('vendor/autoload.php'));
  assert.ok(vendor.some((file) => file.startsWith('vendor/league/commonmark/src/')));
  for (const devOnly of ['vendor/phpunit/', 'vendor/sebastian/', 'vendor/bin/']) {
    assert.ok(!vendor.some((file) => file.startsWith(devOnly)), `${devOnly} must not be packaged`);
  }
  assert.ok(!vendor.some((file) => /(?:^|\/)\.git(?:\/|$)/.test(file)), 'no VCS metadata in vendor');
  for (const directory of privateDirectories) {
    assert.match(readFileSync(join(root, directory, '.htaccess'), 'utf8'), /Require all denied/, directory);
  }
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
    'composer.json', 'composer.lock', 'phpunit.xml.dist', 'docker/mariadb/01-databases.sql', 'tests/php/bootstrap.php',
    'tests/community.test.mjs', 'storage', 'docs/configuration.md',
  ]) assert.ok(!existsSync(join(root, file)), `${file} was packaged`);
});

test('public entries, references, and literal runtime dependencies are packaged', () => {
  for (const file of [
    '.htaccess', 'index.php', 'calculators/index.php',
    ...['premium-item-checker', 'item-price', 'shop-qlvl', 'warrior-repair', 'damage',
      'hellfire-item-price', 'hellfire-damage']
      .map((name) => `calculators/${name}/index.php`),
    'guides/index.php', 'guides/template/index.php', 'guides/shopping/index.php',
    'guides/fast-character-development/index.php', 'guides/max-shopping-video/index.php',
    'shopqlvl/index.php',
    'reference/jarulf162.pdf', 'reference/d1-hf-shrines.pdf',
    'reference/hellfire-shopping-differences.pdf',
    'images/shopping-guide-header.png', 'images/godly-plate-of-the-whale.png',
    'videos/warlord-of-blood.mp4',
    'favicon.ico', 'uvicon-32x32.png', 'uvicon-48x48.png',
    'router.php', 'bin/console', 'privacy/index.php', 'css/app.css', 'js/theme.js', 'js/app.js', 'js/guide-editor.js',
    'src/bootstrap.php', 'src/Http/Kernel.php', 'templates/guides/editor.php', 'migrations/0001_community_schema.php',
  ]) assert.ok(existsSync(join(root, file)), `Missing public file: ${file}`);

  for (const file of applicationFiles()) {
    if (!/\.(?:php|css|mjs)$/.test(file)) continue;
    const content = readFileSync(join(root, file), 'utf8');
    if (file.endsWith('.php')) {
      for (const match of content.matchAll(/\b(?:site_url|asset_version)\s*\(\s*['"]([^'"]+)['"]/g)) {
        const path = match[1].replace(/^\//, '');
        // Community URLs are served by router.php rather than by files.
        if (/^(?:account|members|admin|media)\//.test(path)) continue;
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

test('packaged guide update dates come from Git history', () => {
  const manifest = readFileSync(join(root, 'includes/guide-update-dates.php'), 'utf8');
  for (const file of [
    'guides/fast-character-development/index.php',
    'guides/shopping/index.php',
    'guides/template/index.php',
  ]) {
    const date = execFileSync('git', ['log', '-1', '--format=%cs', '--', file], {
      encoding: 'utf8',
    }).trim();
    assert.match(date, /^\d{4}-\d{2}-\d{2}$/);
    assert.ok(manifest.includes(`'${file}' => '${date}'`), `${file}: incorrect package update date`);
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

test('an untracked PHP file in an application directory stops the build', () => {
  const strays = ['src/ZzUntrackedRuntimeFixture.php', 'templates/zz-untracked-fixture/page.php'];
  const notes = 'src/zz-untracked-notes.txt';
  try {
    for (const stray of strays) {
      mkdirSync(dirname(resolve(stray)), { recursive: true });
      writeFileSync(resolve(stray), '<?php // never reviewed, never committed\n');
      assert.throws(() => buildRuntime(), (error) => /Untracked PHP file in application directory/.test(error.message)
        && error.message.includes(stray), stray);
      assert.ok(!existsSync(root), 'no deployable package is left behind');
      rmSync(resolve(stray), { force: true });
    }
    // Untracked non-PHP files are not code and are never published either.
    writeFileSync(resolve(notes), 'scratch');
    buildRuntime();
    assert.ok(!existsSync(join(root, notes)));
  } finally {
    for (const stray of strays) rmSync(resolve(stray), { force: true });
    rmSync(resolve('templates/zz-untracked-fixture'), { recursive: true, force: true });
    rmSync(resolve(notes), { force: true });
    buildRuntime();
  }
  for (const stray of strays) assert.ok(!existsSync(join(root, stray)), `${stray} was packaged`);
});
