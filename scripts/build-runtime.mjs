import { copyFileSync, existsSync, lstatSync, mkdirSync, realpathSync, renameSync, rmSync, statSync, utimesSync } from 'node:fs';
import { dirname, isAbsolute, join, relative, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const repositoryRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const buildRoot = join(repositoryRoot, 'build');
const runtimeRoot = join(buildRoot, 'runtime');
const stagingRoot = join(buildRoot, '.runtime-staging');

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
  'calculators/hellfire-damage/index.php',
  'calculators/hellfire-damage/calculator.php',
  'calculators/hellfire-damage/css/styles.css',
  'calculators/hellfire-damage/js/scripts.js',
  'calculators/hellfire-item-price/index.php',
  'calculators/hellfire-item-price/calculator.php',
  'calculators/hellfire-item-price/css/styles.css',
  'calculators/hellfire-item-price/js/scripts.js',
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
  'js/in-page-navigation.js',
  'js/scripts.js',
  'reference/d1-hf-shrines.pdf',
  'reference/hellfire-shopping-differences.pdf',
  'reference/jarulf162.pdf',
  'shopqlvl/index.php',
  'videos/warlord-of-blood.mp4',
].sort();

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

export function buildRuntime() {
  if (existsSync(buildRoot) && lstatSync(buildRoot).isSymbolicLink()) {
    throw new Error(`Refusing symlinked build directory: ${buildRoot}`);
  }
  mkdirSync(buildRoot, { recursive: true });
  // Even validation failure must not leave an older package ready to deploy.
  removeBuildDirectory(runtimeRoot);
  removeBuildDirectory(stagingRoot);
  if (new Set(runtimeFiles).size !== runtimeFiles.length) throw new Error('Duplicate runtime file');
  const realRepositoryRoot = realpathSync(repositoryRoot);
  for (const file of runtimeFiles) {
    const source = resolve(repositoryRoot, file);
    if (!inside(repositoryRoot, source) || !existsSync(source)
      || !lstatSync(source).isFile() || !inside(realRepositoryRoot, realpathSync(source))) {
      throw new Error(`Missing or unsafe runtime file: ${file}`);
    }
  }

  try {
    for (const file of runtimeFiles) {
      const source = resolve(repositoryRoot, file);
      const destination = resolve(stagingRoot, file);
      if (!inside(stagingRoot, destination)) throw new Error(`Unsafe runtime path: ${file}`);
      mkdirSync(dirname(destination), { recursive: true });
      copyFileSync(source, destination);
      const modified = statSync(source).mtime;
      utimesSync(destination, modified, modified);
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
  console.log(`Built ${runtimeFiles.length} runtime files in ${buildRuntime()}`);
}
