import { execFileSync } from 'node:child_process';
import { join } from 'node:path';
import { applicationFiles, buildRuntime } from './build-runtime.mjs';
import { phpLintCommand } from './php-lint.mjs';

const runtimeRoot = buildRuntime();
// A separate Compose project: its own database, storage volumes, and ports.
const environment = {
  ...process.env,
  COMPOSE_PROJECT_NAME: 'uvs-compendium-runtime-test',
  SITE_ROOT: './build/runtime',
  SITE_PORT: '8081',
  TEST_SITE_PORT: '8083',
  SITE_URL: 'http://127.0.0.1:8083',
  APP_URL: 'http://127.0.0.1:8083',
};

function run(command, args, env = environment) {
  execFileSync(command, args, { env, stdio: 'inherit' });
}

try {
  // COMPOSE_NO_BUILD=1 reuses an already built local image instead of rebuilding it.
  run('docker', ['compose', 'up', '-d', '--wait', '--force-recreate', process.env.COMPOSE_NO_BUILD === '1' ? '--no-build' : '--build', 'db', 'web', 'web-test']);
  run('docker', ['compose', 'exec', '-T', 'web', 'sh', '-c',
    'test -f .htaccess && test -f reference/jarulf162.pdf && test -f vendor/autoload.php && test -f src/.htaccess'
    + ' && test ! -e package.json && test ! -e AGENTS.md && test ! -e composer.json && test ! -e tests && test ! -e docs'
    + ' && test ! -e vendor/phpunit && test ! -e storage']);
  run('docker', ['compose', 'exec', '-T', 'web', 'sh', '-c', phpLintCommand]);
  for (const file of applicationFiles().filter((path) => /\.(?:js|mjs)$/.test(path))) {
    run(process.execPath, ['--check', join(runtimeRoot, file)]);
  }
  // The packaged application, served as its own document root, must pass the
  // static site checks and the full community flow end to end.
  run(process.execPath, ['--test', '--test-concurrency=1', 'tests/site.test.mjs', 'tests/community.test.mjs', 'tests/community-browser.test.mjs']);
} finally {
  run('docker', ['compose', 'down', '--volumes']);
}
