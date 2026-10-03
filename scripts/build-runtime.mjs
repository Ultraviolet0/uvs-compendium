import { execFileSync } from 'node:child_process';
import { copyFileSync, existsSync, lstatSync, mkdirSync, readdirSync, realpathSync, renameSync, rmSync, statSync, utimesSync, writeFileSync } from 'node:fs';
import { dirname, isAbsolute, join, relative, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const repositoryRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const buildRoot = join(repositoryRoot, 'build');
const runtimeRoot = join(buildRoot, 'runtime');
const stagingRoot = join(buildRoot, '.runtime-staging');
const guideDatesFile = 'includes/guide-update-dates.php';
const datedGuides = [
  'guides/fast-character-development/index.php',
  'guides/shopping/index.php',
  'guides/template/index.php',
];

// Exact, reviewed runtime files. New files in these directories are not published automatically.
export const runtimeFiles = [
  '.htaccess',
  'index.php',
  'favicon.ico',
  'uvicon-32x32.png',
  'uvicon-48x48.png',
  'calculators/index.php',
  'calculators/breadcrumbs.php',
  'calculators/css/styles.css',
  'calculators/town-level.mjs',
  'calculators/damage/index.php',
  'calculators/hellfire-damage/index.php',
  'calculators/hellfire-damage/calculator.php',
  'calculators/hellfire-damage/css/styles.css',
  'calculators/hellfire-damage/js/scripts.js',
  'calculators/hellfire-item-price/index.php',
  'calculators/hellfire-item-price/calculator.php',
  'calculators/hellfire-item-price/css/styles.css',
  'calculators/hellfire-item-price/js/scripts.js',
  'calculators/item-price/index.php',
  'calculators/premium-item-checker/index.php',
  'calculators/premium-item-checker/calculator.php',
  'calculators/premium-item-checker/css/styles.css',
  'calculators/premium-item-checker/js/availability.mjs',
  'calculators/premium-item-checker/js/calculate.mjs',
  'calculators/premium-item-checker/js/data.mjs',
  'calculators/premium-item-checker/js/price.mjs',
  'calculators/premium-item-checker/js/rules.mjs',
  'calculators/premium-item-checker/js/scripts.mjs',
  'calculators/shop-qlvl/index.php',
  'calculators/shop-qlvl/calculator.php',
  'calculators/shop-qlvl/css/styles.css',
  'calculators/shop-qlvl/js/scripts.mjs',
  'calculators/shop-qlvl/js/scripts.js',
  'calculators/warrior-repair/index.php',
  'calculators/warrior-repair/calculator.php',
  'calculators/warrior-repair/css/styles.css',
  'calculators/warrior-repair/js/scripts.js',
  'css/in-page-navigation.css',
  'css/styles.css',
  'guides/index.php',
  'guides/css/styles.css',
  'guides/fast-character-development/index.php',
  'guides/max-shopping-video/index.php',
  'guides/shopping/index.php',
  'guides/template/index.php',
  'images/shopping-guide-header.png',
  'images/godly-plate-of-the-whale.png',
  'includes/public_footer.php',
  'includes/public_header.php',
  'includes/helpers.php',
  'includes/account_navigation.php',
  guideDatesFile,
  'css/app.css',
  'js/app.js',
  'js/guide-editor.js',
  'js/in-page-navigation.js',
  'js/scripts.js',
  'js/theme.js',
  'js/turnstile.js',
  'privacy/index.php',
  'router.php',
  'bin/console',
  'reference/d1-hf-shrines.pdf',
  'reference/hellfire-shopping-differences.pdf',
  'reference/jarulf162.pdf',
  'shopqlvl/index.php',
  'videos/warlord-of-blood.mp4',
].sort();

// Reviewed application directories: every PHP file committed inside is server
// code that the front controller loads. Nothing else in them is published, and
// only files tracked by Git are: a stray, untracked PHP file in one of these
// directories (a local experiment, a merge leftover) stops the build instead
// of being deployed.
export const applicationDirectories = ['src', 'templates', 'migrations'];

// Server-only directories receive a deny-all .htaccess as a second line of
// defence behind the root rewrite rules.
export const privateDirectories = ['bin', 'includes', 'migrations', 'src', 'templates', 'vendor'];
const denyAll = '# Server-side code only; never served over HTTP.\nRequire all denied\n';

/** Files Git tracks under the given paths (repository-relative, forward slashes). */
export function trackedFiles(paths = []) {
  const output = execFileSync('git', ['ls-files', '-z', '--', ...paths], { cwd: repositoryRoot, encoding: 'utf8' });
  return output.split('\0').filter((file) => file !== '');
}

function filesOnDisk(directory) {
  const root = join(repositoryRoot, directory);
  if (!existsSync(root)) return [];
  return readdirSync(root, { withFileTypes: true }).flatMap((entry) => {
    const path = join(directory, entry.name);
    if (entry.isSymbolicLink()) throw new Error(`Refusing symlink in application directory: ${path}`);
    if (entry.isDirectory()) return filesOnDisk(path);
    return [path.split(sep).join('/')];
  });
}

/** Refuses PHP files in the application directories that Git does not track. */
export function assertNoUntrackedApplicationCode() {
  const tracked = new Set(trackedFiles(applicationDirectories));
  const untracked = applicationDirectories.flatMap(filesOnDisk)
    .filter((file) => file.endsWith('.php') && !tracked.has(file));
  if (untracked.length > 0) {
    throw new Error(`Untracked PHP file in application directory (commit or remove it): ${untracked.join(', ')}`);
  }
}

/** Every reviewed source file the package contains, excluding vendor/ and generated files. */
export function applicationFiles() {
  const application = trackedFiles(applicationDirectories).filter((file) => file.endsWith('.php'));
  return [...runtimeFiles, ...application].sort();
}

/** Files generated during the build rather than copied from the repository. */
export const generatedFiles = [guideDatesFile, ...privateDirectories.map((directory) => `${directory}/.htaccess`)].sort();

function lockDate() {
  const date = execFileSync('git', ['log', '-1', '--format=%cI', '--', 'composer.lock'], { cwd: repositoryRoot, encoding: 'utf8' }).trim();
  return date === '' ? new Date('2026-01-01T00:00:00Z') : new Date(date);
}

function composerAvailable() {
  try {
    execFileSync(process.env.COMPOSER_BIN || 'composer', ['--version'], { stdio: 'ignore' });
    return true;
  } catch {
    return false;
  }
}

// Package metadata, tests, and documentation are not needed on the server.
// Source (git) installs include them; dist archives usually omit them.
const prunedVendorNames = new Set(['.git', '.github', 'tests', 'test', 'Tests', 'docs', 'doc', 'examples']);

/** Removes VCS data, tests, docs, dotfiles, and symlinks; stamps a fixed time. */
function normalizeVendor(directory, time, depth = 0) {
  for (const entry of readdirSync(directory, { withFileTypes: true })) {
    const path = join(directory, entry.name);
    const packageLevel = depth >= 2;
    if (entry.isSymbolicLink() || (entry.name.startsWith('.') && entry.name !== '.htaccess')
      || (packageLevel && entry.isDirectory() && prunedVendorNames.has(entry.name) && depth === 2)) {
      rmSync(path, { recursive: true, force: true });
      continue;
    }
    if (entry.isDirectory()) normalizeVendor(path, time, depth + 1);
    utimesSync(path, time, time);
  }
}

/**
 * Installs production dependencies (no dev packages) from composer.lock into
 * the staging tree. Uses a local Composer when available, otherwise the
 * project's Docker tools image.
 */
function installVendor() {
  for (const file of ['composer.json', 'composer.lock']) {
    copyFileSync(join(repositoryRoot, file), join(stagingRoot, file));
  }
  const args = ['install', '--no-dev', '--prefer-dist', '--optimize-autoloader', '--no-interaction', '--no-progress', '--no-scripts'];
  // RUNTIME_COMPOSER=docker uses the project's pinned PHP image; the default uses a local Composer when present.
  if (process.env.RUNTIME_COMPOSER !== 'docker' && composerAvailable()) {
    execFileSync(process.env.COMPOSER_BIN || 'composer', [...args, `--working-dir=${stagingRoot}`], { stdio: ['ignore', 'ignore', 'inherit'] });
  } else {
    const workdir = `/app/${relative(repositoryRoot, stagingRoot).split(sep).join('/')}`;
    // Run as the invoking user so the build can later stamp and delete the files.
    const user = typeof process.getuid === 'function' ? ['--user', `${process.getuid()}:${process.getgid()}`] : [];
    execFileSync('docker', ['compose', 'run', '--rm', '--no-deps', '-T', ...user, '-w', workdir, 'composer', ...args],
      { cwd: repositoryRoot, stdio: ['ignore', 'ignore', 'inherit'] });
  }
  for (const file of ['composer.json', 'composer.lock']) {
    rmSync(join(stagingRoot, file));
  }
  const vendor = join(stagingRoot, 'vendor');
  for (const devOnly of ['phpunit', 'sebastian', 'myclabs', 'nikic', 'phar-io', 'theseer']) {
    if (existsSync(join(vendor, devOnly))) throw new Error(`Development dependency ${devOnly} in runtime vendor`);
  }
  // Console entry points of dependencies are not needed on the server.
  rmSync(join(vendor, 'bin'), { recursive: true, force: true });
  normalizeVendor(vendor, lockDate());
}

function inside(parent, child) {
  const path = relative(parent, child);
  return path !== '' && path !== '..' && !path.startsWith(`..${sep}`) && !isAbsolute(path);
}

function removeBuildDirectory(path) {
  if (!inside(buildRoot, path)) throw new Error(`Unsafe build path: ${path}`);
  if (existsSync(path) && lstatSync(path).isSymbolicLink()) {
    throw new Error(`Refusing symlinked build directory: ${path}`);
  }
  rmSync(path, { recursive: true, force: true });
}

function writeGuideDates() {
  const entries = datedGuides.map((file) => {
    const date = execFileSync('git', ['log', '-1', '--format=%cs', '--', file], {
      cwd: repositoryRoot, encoding: 'utf8',
    }).trim();
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) {
      throw new Error(`Cannot determine last committed guide update for ${file}; build from a full Git checkout`);
    }
    return [file, date];
  });
  const destination = resolve(stagingRoot, guideDatesFile);
  mkdirSync(dirname(destination), { recursive: true });
  writeFileSync(destination, `<?php\nreturn [\n${entries.map(([file, date]) => `  '${file}' => '${date}',`).join('\n')}\n];\n`);
  const newestDate = entries.map(([, date]) => date).sort().at(-1);
  const modified = new Date(`${newestDate}T00:00:00Z`);
  utimesSync(destination, modified, modified);
}

export function buildRuntime() {
  if (existsSync(buildRoot) && lstatSync(buildRoot).isSymbolicLink()) {
    throw new Error(`Refusing symlinked build directory: ${buildRoot}`);
  }
  mkdirSync(buildRoot, { recursive: true });
  // Even validation failure must not leave an older package ready to deploy.
  removeBuildDirectory(runtimeRoot);
  removeBuildDirectory(stagingRoot);
  if (new Set(runtimeFiles).size !== runtimeFiles.length) throw new Error('Duplicate runtime file');
  assertNoUntrackedApplicationCode();
  const sources = applicationFiles();
  const tracked = new Set(trackedFiles());
  const realRepositoryRoot = realpathSync(repositoryRoot);
  for (const file of sources) {
    if (file === guideDatesFile) continue;
    const source = resolve(repositoryRoot, file);
    if (!inside(repositoryRoot, source) || !existsSync(source)
      || !lstatSync(source).isFile() || !inside(realRepositoryRoot, realpathSync(source))) {
      throw new Error(`Missing or unsafe runtime file: ${file}`);
    }
    if (!tracked.has(file)) throw new Error(`Runtime file is not tracked by Git: ${file}`);
  }
  // Release builds can additionally insist that the packaged files match the commit exactly.
  if (process.env.RUNTIME_REQUIRE_CLEAN === '1') {
    const changed = execFileSync('git', ['status', '--porcelain', '--', ...sources.filter((file) => file !== guideDatesFile)],
      { cwd: repositoryRoot, encoding: 'utf8' }).trim();
    if (changed !== '') throw new Error(`Runtime files have uncommitted changes:\n${changed}`);
  }

  try {
    for (const file of sources) {
      if (file === guideDatesFile) continue;
      const source = resolve(repositoryRoot, file);
      const destination = resolve(stagingRoot, file);
      if (!inside(stagingRoot, destination)) throw new Error(`Unsafe runtime path: ${file}`);
      mkdirSync(dirname(destination), { recursive: true });
      copyFileSync(source, destination);
      const modified = statSync(source).mtime;
      utimesSync(destination, modified, modified);
    }
    writeGuideDates();
    installVendor();
    const generatedTime = lockDate();
    for (const directory of privateDirectories) {
      const file = join(stagingRoot, directory, '.htaccess');
      writeFileSync(file, denyAll);
      utimesSync(file, generatedTime, generatedTime);
    }
    // A complete staging tree becomes the only deployable output.
    renameSync(stagingRoot, runtimeRoot);
  } catch (error) {
    removeBuildDirectory(stagingRoot);
    throw error;
  }
  return runtimeRoot;
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  console.log(`Built ${applicationFiles().length} reviewed files plus production dependencies in ${buildRuntime()}`);
}
