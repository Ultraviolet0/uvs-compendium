import { execFileSync } from 'node:child_process';
import { readdirSync } from 'node:fs';
import { join } from 'node:path';
import { phpLintCommand } from './php-lint.mjs';

const excluded = new Set(['.git', 'build', 'node_modules', 'vendor', 'test-results', 'playwright-report', 'coverage', 'storage']);
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
const useContainer = process.env.PHP_LINT_CONTAINER !== '0';

if (useContainer) {
  execFileSync('docker', ['compose', 'exec', '-T', 'web', 'sh', '-c', phpLintCommand],
    { stdio: 'inherit' });
} else {
  for (const path of phpFiles) {
    execFileSync(process.env.PHP_BIN || 'php', ['-n', '-l', path], { stdio: 'inherit' });
  }
}

for (const path of jsFiles) {
  execFileSync(process.execPath, ['--check', path], { stdio: 'inherit' });
}

console.log(`Syntax OK: ${phpFiles.length} PHP files; ${jsFiles.length} JavaScript files.`);

// PHP unit and database-integration tests run in the isolated test service
// (UVS_ENV=test, database uvs_test). Set PHPUNIT=0 to skip them.
if (process.env.PHPUNIT !== '0') {
  if (useContainer) {
    execFileSync('docker', ['compose', 'exec', '-T', '-u', 'www-data', 'web-test', 'vendor/bin/phpunit'], { stdio: 'inherit' });
  } else {
    execFileSync(process.env.PHP_BIN || 'php', ['vendor/bin/phpunit'], { stdio: 'inherit' });
  }
}

const testFiles = readdirSync('tests').filter((name) => name.endsWith('.test.mjs'))
  .map((name) => join('tests', name));
// Files share the disposable test database, so they run one at a time.
execFileSync(process.execPath, ['--test', '--test-concurrency=1', ...testFiles], { stdio: 'inherit' });
