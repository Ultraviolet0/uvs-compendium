import { execFileSync } from 'node:child_process';
import { readdirSync } from 'node:fs';
import { join } from 'node:path';

const excluded = new Set(['.git', 'node_modules', 'vendor', 'test-results', 'playwright-report', 'coverage']);
function files(directory) {
  return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    if (excluded.has(entry.name)) return [];
    const path = join(directory, entry.name);
    return entry.isDirectory() ? files(path) : [path];
  });
}

const sourceFiles = files('.');
const phpFiles = sourceFiles.filter((path) => path.endsWith('.php'));
const jsFiles = sourceFiles.filter((path) => /\.(?:js|mjs)$/.test(path));

if (process.env.PHP_LINT_CONTAINER !== '0') {
  execFileSync('docker', ['compose', 'exec', '-T', 'web', 'sh', '-c',
    "find . -path './node_modules' -prune -o -name '*.php' -exec php -l {} \\;"], { stdio: 'inherit' });
} else {
  for (const path of phpFiles) {
    execFileSync(process.env.PHP_BIN || 'php', ['-n', '-l', path], { stdio: 'inherit' });
  }
}

for (const path of jsFiles) {
  execFileSync(process.execPath, ['--check', path], { stdio: 'inherit' });
}

console.log(`Syntax OK: ${phpFiles.length} PHP files; ${jsFiles.length} JavaScript files.`);
execFileSync(process.execPath, ['--test', 'tests/site.test.mjs'], { stdio: 'inherit' });
