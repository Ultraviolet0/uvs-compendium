import { execFileSync } from 'node:child_process';
import { join } from 'node:path';
import { buildRuntime, runtimeFiles } from './build-runtime.mjs';
import { phpLintCommand } from './php-lint.mjs';

const runtimeRoot = buildRuntime();
const environment = {
  ...process.env,
  COMPOSE_PROJECT_NAME: 'uvs-compendium-runtime-test',
  SITE_ROOT: './build/runtime',
  SITE_PORT: '8081',
  SITE_URL: 'http://127.0.0.1:8081',
};

function run(command, args, env = environment) {
  execFileSync(command, args, { env, stdio: 'inherit' });
}

try {
  run('docker', ['compose', 'up', '-d', '--build']);
  run('docker', ['compose', 'exec', '-T', 'web', 'sh', '-c',
    'test -f .htaccess && test -f reference/jarulf162.pdf && test ! -e package.json && test ! -e AGENTS.md']);
  run('docker', ['compose', 'exec', '-T', 'web', 'sh', '-c', phpLintCommand]);
  for (const file of runtimeFiles.filter((path) => /\.(?:js|mjs)$/.test(path))) {
    run(process.execPath, ['--check', join(runtimeRoot, file)]);
  }
  run(process.execPath, ['--test', 'tests/site.test.mjs']);
} finally {
  run('docker', ['compose', 'down']);
}
