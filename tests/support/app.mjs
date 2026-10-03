/*
  Helpers for tests of the community application. They drive the isolated
  web-test service (database uvs_test, its own storage volume); nothing here
  can reach development or production data.
*/
import { execFileSync } from 'node:child_process';
import { createHmac } from 'node:crypto';

export const appBase = process.env.APP_URL || process.env.SITE_URL || 'http://localhost:8082';
const service = process.env.APP_SERVICE || 'web-test';

export function consoleCommand(args, input) {
  return execFileSync('docker', ['compose', 'exec', '-T', '-u', 'www-data', service, 'php', 'bin/console', ...args], {
    input, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe'],
  });
}

export function containerShell(script) {
  return execFileSync('docker', ['compose', 'exec', '-T', '-u', 'www-data', service, 'sh', '-c', script], { encoding: 'utf8' });
}

export const ADMIN = { username: 'TestAdmin', email: 'test-admin@example.test', password: 'test admin passphrase only' };

export function resetDatabase() {
  consoleCommand(['db:reset-test']);
  consoleCommand(['admin:create', `--username=${ADMIN.username}`, `--email=${ADMIN.email}`, '--password-stdin'], `${ADMIN.password}\n`);
}

export function clearRateLimits() {
  consoleCommand(['rate-limits:clear']);
}

export function capturedMail() {
  return containerShell('cat /var/uvs/storage/mail/*.eml 2>/dev/null || true');
}

export const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/** Minimal cookie-aware HTTP client that never follows redirects. */
export class Client {
  constructor() {
    this.cookies = new Map();
    this.setCookieHeaders = [];
  }

  cookieHeader() {
    return [...this.cookies].map(([name, value]) => `${name}=${value}`).join('; ');
  }

  remember(response) {
    const headers = response.headers.getSetCookie?.() || [];
    this.setCookieHeaders.push(...headers);
    for (const header of headers) {
      const [pair] = header.split(';');
      const index = pair.indexOf('=');
      const name = pair.slice(0, index).trim();
      const value = pair.slice(index + 1).trim();
      if (/expires=Thu, 01 Jan 1970|Max-Age=0/i.test(header) || value === '' || value === 'deleted') {
        this.cookies.delete(name);
      } else {
        this.cookies.set(name, value);
      }
    }
  }

  async request(path, { method = 'GET', body, headers = {} } = {}) {
    const response = await fetch(new URL(path, appBase), {
      method, body, redirect: 'manual',
      headers: { ...(this.cookies.size ? { Cookie: this.cookieHeader() } : {}), ...headers },
    });
    this.remember(response);
    const text = await response.text();
    return { status: response.status, headers: response.headers, text, location: response.headers.get('location') };
  }

  get(path, headers) {
    return this.request(path, { headers });
  }

  async token(pagePath) {
    const page = await this.get(pagePath);
    const match = page.text.match(/name="_csrf" value="([a-f0-9]{64})"/) || page.text.match(/name="csrf-token" content="([a-f0-9]{64})"/);
    if (!match) throw new Error(`No CSRF token on ${pagePath} (status ${page.status})`);
    return match[1];
  }

  /** Submits a form with the CSRF token from pagePath (or the target itself). */
  async post(path, fields = {}, { page, token, headers } = {}) {
    const csrf = token ?? await this.token(page || path);
    const body = new URLSearchParams({ _csrf: csrf, ...fields });
    return this.request(path, { method: 'POST', body, headers: { 'Content-Type': 'application/x-www-form-urlencoded', ...headers } });
  }

  async upload(path, field, filename, type, bytes, fields = {}, { page, json = false } = {}) {
    const form = new FormData();
    form.append('_csrf', await this.token(page));
    for (const [key, value] of Object.entries(fields)) form.append(key, value);
    form.append(field, new Blob([bytes], { type }), filename);
    return this.request(path, { method: 'POST', body: form, headers: json ? { Accept: 'application/json', 'X-Requested-With': 'fetch' } : {} });
  }

  async login(identifier, password) {
    return this.post('/account/login/', { identifier, password }, { page: '/account/login/' });
  }
}

/** Registers through the real signup form, honouring the minimum form time. */
export async function signup(client, username, email, password = 'correct horse battery staple', extra = {}) {
  const page = await client.get('/account/signup/');
  const csrf = page.text.match(/name="_csrf" value="([a-f0-9]{64})"/)?.[1];
  const started = page.text.match(/name="form_started" value="([^"]+)"/)?.[1];
  const turnstile = page.text.match(/name="cf-turnstile-response" value="([^"]+)"/)?.[1] ?? '';
  await sleep(extra.wait ?? 1100);
  const fields = {
    _csrf: csrf, form_started: started, username, email, password, password_confirmation: password,
    accept_privacy: '1', website: '', 'cf-turnstile-response': turnstile, ...extra.fields,
  };
  return client.request('/account/signup/', {
    method: 'POST', body: new URLSearchParams(fields), headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
}

export async function adminClient() {
  const client = new Client();
  const result = await client.login(ADMIN.username, ADMIN.password);
  if (result.status !== 303) throw new Error(`Admin sign-in failed: ${result.status}`);
  return client;
}

export async function userIdFor(admin, username) {
  const page = await admin.get(`/admin/users/?q=${encodeURIComponent(username)}`);
  const match = page.text.match(/href="[^"]*admin\/users\/(\d+)\/">([^<]+)</);
  if (!match) throw new Error(`User ${username} not found in admin list`);
  return match[1];
}

/** Creates an approved member and returns a signed-in client. */
export async function activeMember(admin, username, password = 'correct horse battery staple') {
  const client = new Client();
  const result = await signup(client, username, `${username.toLowerCase()}@example.test`, password);
  if (result.status !== 303) throw new Error(`Signup for ${username} failed: ${result.status}`);
  const id = await userIdFor(admin, username);
  const approved = await admin.post(`/admin/users/${id}/status/`, { action: 'approve' }, { page: `/admin/users/${id}/` });
  if (approved.status !== 303) throw new Error(`Approval of ${username} failed: ${approved.status}`);
  return { client, id };
}

const base32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
export function totp(secret, time = Date.now(), offsetSteps = 0) {
  let bits = '';
  for (const char of secret.replace(/\s+/g, '').toUpperCase()) bits += base32.indexOf(char).toString(2).padStart(5, '0');
  const key = Buffer.from(bits.match(/.{8}/g).map((byte) => parseInt(byte, 2)));
  const counter = Buffer.alloc(8);
  counter.writeBigUInt64BE(BigInt(Math.floor(time / 1000 / 30) + offsetSteps));
  const digest = createHmac('sha1', key).update(counter).digest();
  const offset = digest[digest.length - 1] & 0xf;
  const code = (digest.readUInt32BE(offset) & 0x7fffffff) % 1_000_000;
  return String(code).padStart(6, '0');
}

/** A small valid PNG (1x1 transparent pixel). */
export const tinyPng = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', 'base64');

export async function waitForApp() {
  for (let attempt = 0; attempt < 60; attempt += 1) {
    try {
      const response = await fetch(new URL('/account/login/', appBase), { signal: AbortSignal.timeout(2000) });
      if (response.status === 200) return;
    } catch {
      // still starting
    }
    await sleep(500);
  }
  throw new Error(`Community application did not start at ${appBase}`);
}
