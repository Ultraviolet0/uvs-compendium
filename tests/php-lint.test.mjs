import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { test } from 'node:test';
import { phpLintCommand } from '../scripts/php-lint.mjs';

test('container PHP lint fails when any PHP file has a parse error',
  { skip: process.env.PHP_LINT_CONTAINER === '0' }, () => {
    mkdirSync('test-results', { recursive: true });
    const directory = mkdtempSync(join('test-results', 'php-lint-'));
    try {
      writeFileSync(join(directory, 'invalid.php'), '<?php function broken( {');
      const result = spawnSync('docker', ['compose', 'exec', '-T', 'web', 'sh', '-c', phpLintCommand],
        { encoding: 'utf8' });
      assert.equal(result.error, undefined);
      assert.notEqual(result.status, 0, `lint unexpectedly passed:\n${result.stdout}`);
      assert.match(result.stderr + result.stdout, /(?:Parse error|Errors parsing)/);
    } finally {
      rmSync(directory, { recursive: true, force: true });
    }
  });
