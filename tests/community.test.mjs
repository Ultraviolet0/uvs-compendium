import assert from 'node:assert/strict';
import { before, beforeEach, test } from 'node:test';
import {
  ADMIN, Client, activeMember, adminClient, appBase, capturedMail, clearRateLimits, resetDatabase,
  signup, sleep, tinyPng, totp, userIdFor, waitForApp,
} from './support/app.mjs';

let admin;

before(async () => {
  await waitForApp();
  resetDatabase();
  admin = await adminClient();
});

// Every test starts with fresh throttling counters; the throttling test fills them deliberately.
beforeEach(() => clearRateLimits());

const flat = (html) => html.replace(/\s+/g, ' ');

test('signup creates a pending account, signs it in, and keeps the email private', async () => {
  clearRateLimits();
  const client = new Client();
  const result = await signup(client, 'NewHero', 'newhero@example.test');
  assert.equal(result.status, 303);
  assert.match(result.location, /\/account\/$/);
  const dashboard = await client.get('/account/');
  assert.equal(dashboard.status, 200);
  assert.match(dashboard.text, /awaiting approval/i);
  const cookie = client.setCookieHeaders.find((header) => /uvs_session=/.test(header));
  assert.match(cookie, /HttpOnly/i);
  assert.match(cookie, /SameSite=Lax/i);
  assert.equal(dashboard.headers.get('cache-control'), 'private, no-store');
  // Pending members have no public profile.
  assert.equal((await new Client().get('/members/NewHero/')).status, 404);
});

test('signup rejects duplicates, invalid input, bots, and failed Turnstile checks', async () => {
  clearRateLimits();
  await signup(new Client(), 'Duplicate', 'dup@example.test');
  const sameName = await signup(new Client(), 'DUPLICATE', 'other@example.test');
  assert.equal(sameName.status, 422);
  assert.match(sameName.text, /username is already taken/);
  const sameEmail = await signup(new Client(), 'Unique', 'DUP@example.test');
  assert.equal(sameEmail.status, 422);
  assert.match(sameEmail.text, /email address cannot be used/);
  const invalid = await signup(new Client(), 'x', 'not-an-email', 'short');
  assert.equal(invalid.status, 422);
  assert.match(invalid.text, /Usernames are 3–24 characters/);
  assert.match(invalid.text, /valid email/);
  assert.match(invalid.text, /at least 10 characters/);
  const reserved = await signup(new Client(), 'Administrator', 'adm@example.test');
  assert.match(reserved.text, /reserved/);

  clearRateLimits();
  const honeypot = await signup(new Client(), 'BotOne', 'bot1@example.test', undefined, { fields: { website: 'https://spam.example' } });
  assert.equal(honeypot.status, 422);
  const fast = await signup(new Client(), 'BotTwo', 'bot2@example.test', undefined, { wait: 0 });
  assert.equal(fast.status, 422);
  assert.match(fast.text, /quicker than expected/);
  const turnstile = await signup(new Client(), 'BotThree', 'bot3@example.test', undefined, { fields: { 'cf-turnstile-response': 'forged-token' } });
  assert.equal(turnstile.status, 422);
  assert.match(turnstile.text, /anti-bot check did not pass/);
  const passing = await signup(new Client(), 'HumanFour', 'human4@example.test');
  assert.equal(passing.status, 303, 'the offline test verifier accepts its documented token');
  const missingConsent = await signup(new Client(), 'NoConsent', 'nc@example.test', undefined, { fields: { accept_privacy: '' } });
  assert.equal(missingConsent.status, 422);
});

test('sign-in, generic failures, session fixation defence, and logout', async () => {
  clearRateLimits();
  const client = new Client();
  await client.get('/account/login/');
  const before = client.cookies.get('uvs_session');
  const wrong = await client.login('NewHero', 'not the right password');
  assert.equal(wrong.status, 422);
  assert.match(wrong.text, /username or email and password combination is not correct/);
  const unknown = await new Client().login('NobodyAtAll', 'not the right password');
  assert.match(unknown.text, /username or email and password combination is not correct/);
  const ok = await client.login('newhero@example.test', 'correct horse battery staple');
  assert.equal(ok.status, 303);
  assert.notEqual(client.cookies.get('uvs_session'), before, 'session id changes at sign-in');

  const noToken = await client.request('/account/logout/', { method: 'POST' });
  assert.equal(noToken.status, 403);
  const out = await client.post('/account/logout/', {}, { page: '/account/' });
  assert.equal(out.status, 303);
  const after = await client.get('/account/');
  assert.equal(after.status, 303);
  assert.match(after.location, /\/account\/login\/\?next=%2Faccount%2F/);
});

test('login throttling', async () => {
  clearRateLimits();
  const client = new Client();
  let last;
  for (let attempt = 0; attempt < 9; attempt += 1) {
    last = await client.login('NewHero', `wrong password ${attempt}`);
  }
  assert.equal(last.status, 429);
  assert.match(last.text, /Too many sign-in attempts/);
  const correct = await client.login('NewHero', 'correct horse battery staple');
  assert.equal(correct.status, 429, 'the account stays locked during the window');
  clearRateLimits();
});

test('CSRF and cross-origin protections on state-changing requests', async () => {
  const client = new Client();
  await client.login('NewHero', 'correct horse battery staple');
  const missing = await client.request('/account/profile/', {
    method: 'POST', body: new URLSearchParams({ bio: 'x' }), headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
  assert.equal(missing.status, 403);
  assert.match(missing.text, /form expired or could not be verified/);
  const forged = await client.post('/account/profile/', { bio: 'x' }, { token: 'a'.repeat(64) });
  assert.equal(forged.status, 403);
  const crossSite = await client.post('/account/profile/', { bio: 'x' }, { page: '/account/profile/', headers: { Origin: 'https://evil.example' } });
  assert.equal(crossSite.status, 403);
  const anonymous = await new Client().post('/account/theme/', { theme: 'light' }, { token: 'b'.repeat(64) });
  assert.equal(anonymous.status, 403);
  const valid = await client.post('/account/profile/', { bio: 'Rogue main.', preferred_game: 'hellfire' }, { page: '/account/profile/' });
  assert.equal(valid.status, 303);
});

test('authorization: anonymous, pending, member, and admin boundaries', async () => {
  const anonymous = new Client();
  const adminAnon = await anonymous.get('/admin/');
  assert.equal(adminAnon.status, 303);
  assert.match(adminAnon.location, /account\/login/);
  assert.equal((await anonymous.get('/account/guides/new/')).status, 303);

  const pending = new Client();
  await pending.login('NewHero', 'correct horse battery staple');
  assert.equal((await pending.get('/account/profile/')).status, 200, 'pending members manage their profile');
  assert.equal((await pending.get('/account/security/')).status, 200);
  assert.equal((await pending.get('/account/guides/new/')).status, 403);
  const create = await pending.post('/account/guides/new/', { title: 'Sneaky', summary: '', body: 'x' }, { page: '/account/' });
  assert.equal(create.status, 403);
  assert.equal((await pending.get('/admin/')).status, 403);
  assert.equal((await pending.get('/admin/users/')).status, 403);
  const settings = await pending.post('/admin/settings/', { auto_approve_accounts: '1' }, { page: '/account/' });
  assert.equal(settings.status, 403);

  const { client: member } = await activeMember(admin, 'PlainMember');
  assert.equal((await member.get('/account/guides/new/')).status, 200);
  for (const path of ['/admin/', '/admin/users/', '/admin/guides/', '/admin/settings/', '/admin/audit/']) {
    assert.equal((await member.get(path)).status, 403, path);
  }
  const promote = await member.post('/admin/users/1/role/', { role: 'admin', confirm: '1' }, { page: '/account/' });
  assert.equal(promote.status, 403);
  assert.equal((await admin.get('/admin/')).status, 200);
});

test('account approval, auto-approval, rejection, suspension, and reactivation', async () => {
  clearRateLimits();
  const waiting = new Client();
  await signup(waiting, 'Applicant', 'applicant@example.test');
  const queue = await admin.get('/admin/users/?status=pending');
  assert.match(queue.text, /Applicant/);
  const id = await userIdFor(admin, 'Applicant');

  const unconfirmedReject = await admin.post(`/admin/users/${id}/status/`, { action: 'reject' }, { page: `/admin/users/${id}/` });
  assert.equal(unconfirmedReject.status, 422, 'rejection requires confirmation');
  const rejected = await admin.post(`/admin/users/${id}/status/`, { action: 'reject', confirm: '1', reason: 'Spam' }, { page: `/admin/users/${id}/` });
  assert.equal(rejected.status, 303);
  const blocked = await new Client().login('Applicant', 'correct horse battery staple');
  assert.match(blocked.text, /not approved/);
  assert.equal((await waiting.get('/account/')).status, 303, 'existing sessions end when an account is rejected');

  const toggle = await admin.post('/admin/settings/', {
    registrations_enabled: '1', auto_approve_accounts: '1', guide_submissions_enabled: '1',
    media_max_upload_mb: '8', media_quota_mb: '50', media_max_images_per_guide: '20',
  }, { page: '/admin/settings/' });
  assert.equal(toggle.status, 303);
  const auto = new Client();
  await signup(auto, 'AutoApproved', 'auto@example.test');
  assert.match((await auto.get('/account/')).text, /Member/);
  assert.doesNotMatch((await auto.get('/account/')).text, /awaiting approval/);
  assert.equal((await new Client().get('/members/AutoApproved/')).status, 200);
  const stillRejected = await admin.get(`/admin/users/${id}/`);
  assert.match(stillRejected.text, /status-badge[^>]*>Rejected/, 'auto-approval never reactivates rejected accounts');
  await admin.post('/admin/settings/', {
    registrations_enabled: '1', guide_submissions_enabled: '1',
    media_max_upload_mb: '8', media_quota_mb: '50', media_max_images_per_guide: '20',
  }, { page: '/admin/settings/' });

  const { client: member, id: memberId } = await activeMember(admin, 'Troublemaker');
  assert.equal((await member.get('/account/')).status, 200);
  const suspended = await admin.post(`/admin/users/${memberId}/status/`, { action: 'suspend', confirm: '1', reason: 'Cooling off' }, { page: `/admin/users/${memberId}/` });
  assert.equal(suspended.status, 303);
  assert.equal((await member.get('/account/')).status, 303, 'suspension revokes sessions immediately');
  const refused = await new Client().login('Troublemaker', 'correct horse battery staple');
  assert.equal(refused.status, 403);
  assert.match(refused.text, /suspended\. Reason: Cooling off/);
  assert.equal((await new Client().get('/members/Troublemaker/')).status, 404);
  await admin.post(`/admin/users/${memberId}/status/`, { action: 'reactivate' }, { page: `/admin/users/${memberId}/` });
  assert.equal((await new Client().login('Troublemaker', 'correct horse battery staple')).status, 303);

  const audit = await admin.get('/admin/audit/');
  for (const label of ['Account rejected', 'Account suspended', 'Account reactivated', 'Setting changed', 'Account approved']) {
    assert.match(audit.text, new RegExp(label), label);
  }
  assert.doesNotMatch(audit.text, /correct horse battery staple/);
});

test('profiles: optional fields, validation, privacy, and characters', async () => {
  const { client } = await activeMember(admin, 'Profiled');
  const invalid = await client.post('/account/profile/', { website_url: 'javascript:alert(1)', discord_handle: 'bad handle!' }, { page: '/account/profile/' });
  assert.equal(invalid.status, 422);
  assert.match(invalid.text, /full web address/);
  const saved = await client.post('/account/profile/', {
    bio: 'Hellfire monk player. <script>alert(1)</script>', preferred_game: 'hellfire',
    website_url: 'https://example.com/uv', discord_handle: 'profiled_01',
  }, { page: '/account/profile/' });
  assert.equal(saved.status, 303);

  const badClass = await client.post('/account/characters/', { name: 'Zealot', class: 'monk', game: 'diablo' }, { page: '/account/characters/' });
  assert.equal(badClass.status, 422);
  assert.match(badClass.text, /Hellfire classes/);
  for (const [name, cls, game, level] of [['Aldric', 'warrior', 'diablo', '30'], ['Shade', 'monk', 'hellfire', '45']]) {
    const added = await client.post('/account/characters/', { name, class: cls, game, level, play_mode: 'multi', platform: 'devilutionx', notes: 'Main' }, { page: '/account/characters/' });
    assert.equal(added.status, 303, name);
  }
  const list = await client.get('/account/characters/');
  const ids = [...list.text.matchAll(/id="character-(\d+)"/g)].map((match) => match[1]);
  assert.equal(ids.length, 2);
  await client.post(`/account/characters/${ids[1]}/move/`, { direction: 'up' }, { page: '/account/characters/' });
  const reordered = await client.get('/account/characters/');
  assert.ok(reordered.text.indexOf('Shade') < reordered.text.indexOf('Aldric'), 'moved up');
  const edited = await client.post(`/account/characters/${ids[0]}/`, { name: 'Aldric II', class: 'warrior', game: 'diablo', level: '31' }, { page: `/account/characters/${ids[0]}/edit/` });
  assert.equal(edited.status, 303);

  const other = await activeMember(admin, 'Snoop');
  assert.equal((await other.client.get(`/account/characters/${ids[0]}/edit/`)).status, 404);
  const steal = await other.client.post(`/account/characters/${ids[0]}/delete/`, {}, { page: '/account/characters/' });
  assert.equal(steal.status, 404);

  const profile = await new Client().get('/members/profiled/');
  assert.equal(profile.status, 301);
  const page = await new Client().get('/members/Profiled/');
  assert.equal(page.status, 200);
  assert.match(page.text, /Hellfire monk player\. &lt;script&gt;/);
  assert.match(page.text, /Aldric II/);
  assert.match(page.text, /Shade/);
  assert.match(page.text, /profiled_01/);
  assert.match(page.text, /href="https:\/\/example\.com\/uv" rel="nofollow ugc noopener noreferrer"/);
  assert.doesNotMatch(page.text, /profiled@example\.test/i, 'email never appears publicly');
  assert.doesNotMatch(page.text, /admin_note|status_reason/);

  await client.post(`/account/characters/${ids[1]}/delete/`, {}, { page: '/account/characters/' });
  assert.doesNotMatch((await client.get('/account/characters/')).text, new RegExp(`id="character-${ids[1]}"`));

  const directory = await new Client().get('/members/?q=profiled');
  assert.match(directory.text, /Profiled/);
  assert.doesNotMatch(directory.text, /@example\.test/);
});

const guideBody = [
  '## Getting started', '', 'Shop at **character level 25** for the best [premium items](/calculators/premium-item-checker/).', '',
  '<script>alert("xss")</script>', '', '[evil](javascript:alert(1))', '', '<iframe src="https://evil.example"></iframe>', '',
  '> [!TIP]', '> Sell junk to Griswold first.', '', '```youtube', 'https://www.youtube.com/watch?v=c8MaZZezeMQ', '```', '',
  'Lorem ipsum dolor sit amet, consectetur adipiscing elit. '.repeat(5),
].join('\n');

let guideId;
let author;

test('guide drafting, autosave, conflicts, and private previews', async () => {
  ({ client: author } = await activeMember(admin, 'GuideWriter'));
  const created = await author.post('/account/guides/new/', {
    title: 'Premium Shopping Basics', summary: 'A practical introduction to Griswold premium shopping.', body: guideBody, applies_to: 'hellfire',
  }, { page: '/account/guides/new/' });
  assert.equal(created.status, 303);
  guideId = created.location.match(/guides\/(\d+)\/edit/)[1];
  const editor = await author.get(`/account/guides/${guideId}/edit/`);
  assert.match(editor.text, /status-badge[^>]*>Draft/);
  const lock = editor.text.match(/name="lock_version" value="(\d+)"/)[1];

  const autosave = await author.post(`/account/guides/${guideId}/save/`, {
    title: 'Premium Shopping Basics', summary: 'A practical introduction to Griswold premium shopping.', body: `${guideBody}\n\nAutosaved paragraph.`, lock_version: lock,
  }, { page: `/account/guides/${guideId}/edit/`, headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' } });
  assert.equal(autosave.status, 200);
  const saved = JSON.parse(autosave.text);
  assert.equal(saved.ok, true);
  assert.equal(saved.lock_version, Number(lock) + 1);
  const stale = await author.post(`/account/guides/${guideId}/save/`, { title: 'Stale', summary: '', body: 'x', lock_version: lock },
    { page: `/account/guides/${guideId}/edit/`, headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' } });
  assert.equal(stale.status, 409);

  const preview = await author.post('/account/guides/preview/', { body: '## Hi\n\n<b>bold</b> [x](javascript:alert(1))', guide_id: guideId },
    { page: `/account/guides/${guideId}/edit/`, headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' } });
  const rendered = JSON.parse(preview.text);
  assert.match(rendered.html, /<h2>Hi<\/h2>/);
  assert.match(rendered.html, /&lt;b&gt;bold&lt;\/b&gt;/);
  assert.doesNotMatch(rendered.html, /javascript:/);

  assert.equal((await new Client().get('/guides/premium-shopping-basics/')).status, 404, 'drafts are not public');
  assert.equal((await new Client().get(`/account/guides/${guideId}/preview/`)).status, 303);
  const stranger = (await activeMember(admin, 'Stranger')).client;
  assert.equal((await stranger.get(`/account/guides/${guideId}/preview/`)).status, 404);
  assert.equal((await stranger.get(`/account/guides/${guideId}/edit/`)).status, 404);
  const hijack = await stranger.post(`/account/guides/${guideId}/save/`, { title: 'Hijacked', summary: '', body: 'x', lock_version: '1' }, { page: '/account/' });
  assert.equal(hijack.status, 404);
  assert.equal((await author.get(`/account/guides/${guideId}/preview/`)).status, 200);
});

test('guide images: upload, privacy before publication, validation, and ownership', async () => {
  const uploaded = await author.upload(`/account/guides/${guideId}/media/`, 'image', 'map.png', 'image/png', tinyPng, { alt: 'Tristram map' },
    { page: `/account/guides/${guideId}/edit/`, json: true });
  assert.equal(uploaded.status, 200, uploaded.text);
  const media = JSON.parse(uploaded.text);
  assert.match(media.public_id, /^[A-Za-z0-9_-]{22}$/);
  assert.equal(media.markdown, `![Tristram map](media:${media.public_id})`);
  const ownView = await author.get(new URL(media.url, appBase).pathname);
  assert.equal(ownView.status, 200);
  assert.match(ownView.headers.get('content-type'), /^image\/(webp|jpeg)$/);
  assert.equal(ownView.headers.get('x-content-type-options'), 'nosniff');
  assert.match(ownView.headers.get('content-security-policy'), /sandbox/);
  assert.equal((await new Client().get(new URL(media.url, appBase).pathname)).status, 404, 'draft images are private');

  const svg = await author.upload(`/account/guides/${guideId}/media/`, 'image', 'x.svg', 'image/svg+xml',
    Buffer.from('<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>'), {}, { page: `/account/guides/${guideId}/edit/`, json: true });
  assert.equal(svg.status, 422);
  assert.match(JSON.parse(svg.text).error, /SVG/);
  const text = await author.upload(`/account/guides/${guideId}/media/`, 'image', 'shell.png', 'image/png',
    Buffer.from('<?php system($_GET["c"]); ?>'), {}, { page: `/account/guides/${guideId}/edit/`, json: true });
  assert.equal(text.status, 422);
  await admin.post('/admin/settings/', {
    registrations_enabled: '1', guide_submissions_enabled: '1', media_max_upload_mb: '1', media_quota_mb: '50', media_max_images_per_guide: '20',
  }, { page: '/admin/settings/' });
  const big = Buffer.concat([tinyPng, Buffer.alloc(1_200_000, 1)]);
  const oversized = await author.upload(`/account/guides/${guideId}/media/`, 'image', 'big.png', 'image/png', big, {}, { page: `/account/guides/${guideId}/edit/`, json: true });
  assert.equal(oversized.status, 422);
  assert.match(JSON.parse(oversized.text).error, /too large/);
  await admin.post('/admin/settings/', {
    registrations_enabled: '1', guide_submissions_enabled: '1', media_max_upload_mb: '8', media_quota_mb: '50', media_max_images_per_guide: '20',
  }, { page: '/admin/settings/' });

  const thief = (await activeMember(admin, 'ImageThief')).client;
  const steal = await thief.post(`/account/media/${media.public_id}/delete/`, {}, { page: '/account/', headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' } });
  assert.equal(steal.status, 422);
  assert.equal((await author.get(new URL(media.url, appBase).pathname)).status, 200, 'image survived the attempt');

  // Reference the image in the guide body so it is published with it.
  const editor = await author.get(`/account/guides/${guideId}/edit/`);
  const lock = editor.text.match(/name="lock_version" value="(\d+)"/)[1];
  await author.post(`/account/guides/${guideId}/save/`, {
    title: 'Premium Shopping Basics', summary: 'A practical introduction to Griswold premium shopping.',
    body: `${guideBody}\n\n${media.markdown}`, applies_to: 'hellfire', lock_version: lock,
  }, { page: `/account/guides/${guideId}/edit/` });
  globalThis.guideMedia = media;
});

test('moderation: submit, request changes, resubmit, publish, hide, reject, and revisions', async () => {
  const submitted = await author.post(`/account/guides/${guideId}/submit/`, {}, { page: `/account/guides/${guideId}/edit/` });
  assert.equal(submitted.status, 303);
  const locked = await author.get(`/account/guides/${guideId}/edit/`);
  assert.match(locked.text, /Editing is locked/);
  const blockedSave = await author.post(`/account/guides/${guideId}/save/`, { title: 'Changed in review', summary: '', body: 'x', lock_version: '99' }, { page: `/account/guides/${guideId}/edit/` });
  assert.notEqual(blockedSave.status, 303, 'the reviewed version cannot be changed silently');

  const queue = await admin.get('/admin/guides/?filter=queue');
  assert.match(queue.text, /Premium Shopping Basics/);
  const review = await admin.get(`/admin/guides/${guideId}/`);
  assert.equal(review.status, 200);
  assert.match(review.text, /&lt;script&gt;alert/);
  assert.doesNotMatch(review.text, /<script>alert\("xss"\)/);

  const noNote = await admin.post(`/admin/guides/${guideId}/action/`, { action: 'request_changes', note: '' }, { page: `/admin/guides/${guideId}/` });
  assert.equal(noNote.status, 422);
  await admin.post(`/admin/guides/${guideId}/action/`, { action: 'request_changes', note: 'Please add a Wirt section.' }, { page: `/admin/guides/${guideId}/` });
  const needsChanges = await author.get('/account/');
  assert.match(needsChanges.text, /Please add a Wirt section\./);
  assert.match((await author.get(`/account/guides/${guideId}/edit/`)).text, /status-badge[^>]*>Needs changes/);

  const editor = await author.get(`/account/guides/${guideId}/edit/`);
  const lock = editor.text.match(/name="lock_version" value="(\d+)"/)[1];
  const resubmit = await author.post(`/account/guides/${guideId}/save/`, {
    title: 'Premium Shopping Basics', summary: 'A practical introduction to Griswold premium shopping.',
    body: `${guideBody}\n\n## Wirt\n\nWirt sells one item at a time.\n\n${globalThis.guideMedia.markdown}`, applies_to: 'hellfire', lock_version: lock, intent: 'submit',
  }, { page: `/account/guides/${guideId}/edit/` });
  assert.equal(resubmit.status, 303);

  const published = await admin.post(`/admin/guides/${guideId}/action/`, { action: 'approve_publish', note: 'Thanks!' }, { page: `/admin/guides/${guideId}/` });
  assert.equal(published.status, 303);
  const page = await new Client().get('/guides/premium-shopping-basics/');
  assert.equal(page.status, 200);
  const html = flat(page.text);
  assert.match(html, /<h1 class="guide-title">Premium Shopping Basics<\/h1>/);
  assert.match(html, /href="\.\.\/\.\.\/members\/GuideWriter\/">GuideWriter<\/a>/);
  assert.match(html, /youtube-nocookie\.com\/embed\/c8MaZZezeMQ/);
  assert.match(html, /guide-callout-tip/);
  assert.match(html, /Wirt sells one item/);
  assert.doesNotMatch(html, /<script>alert|<iframe src="https:\/\/evil|href="javascript/);
  assert.equal(page.headers.get('x-content-type-options'), 'nosniff');
  assert.match(page.headers.get('content-security-policy'), /frame-src https:\/\/www\.youtube-nocookie\.com/);
  assert.equal((await new Client().get(new URL(globalThis.guideMedia.url, appBase).pathname)).status, 200, 'published images are public');

  const index = await new Client().get('/guides/');
  assert.match(index.text, /id="community-guides"/);
  assert.match(index.text, /Premium Shopping Basics/);
  assert.match((await new Client().get('/members/GuideWriter/')).text, /Premium Shopping Basics/);

  const revisions = await admin.get(`/admin/guides/${guideId}/`);
  const revisionIds = [...revisions.text.matchAll(/revisions\/(\d+)\//g)].map((match) => match[1]);
  assert.ok(revisionIds.length >= 2, 'submission and resubmission revisions exist');
  const diff = await admin.get(`/admin/guides/${guideId}/revisions/${revisionIds[0]}/`);
  assert.equal(diff.status, 200);
  assert.match(diff.text, /Wirt sells one item/);
  assert.match(diff.text, /diff-add/);

  // Editing a live guide never changes the published page until it is approved again.
  const live = await author.get(`/account/guides/${guideId}/edit/`);
  const liveLock = live.text.match(/name="lock_version" value="(\d+)"/)[1];
  await author.post(`/account/guides/${guideId}/save/`, {
    title: 'Premium Shopping Basics', summary: 'A practical introduction to Griswold premium shopping.',
    body: 'UNREVIEWED UPDATE TEXT '.repeat(20), lock_version: liveLock,
  }, { page: `/account/guides/${guideId}/edit/` });
  assert.doesNotMatch((await new Client().get('/guides/premium-shopping-basics/')).text, /UNREVIEWED UPDATE TEXT/);

  const unconfirmedHide = await admin.post(`/admin/guides/${guideId}/action/`, { action: 'hide' }, { page: `/admin/guides/${guideId}/` });
  assert.equal(unconfirmedHide.status, 422);
  await admin.post(`/admin/guides/${guideId}/action/`, { action: 'hide', confirm: '1' }, { page: `/admin/guides/${guideId}/` });
  assert.equal((await new Client().get('/guides/premium-shopping-basics/')).status, 404);
  assert.equal((await new Client().get(new URL(globalThis.guideMedia.url, appBase).pathname)).status, 404, 'hidden guide images are private again');
  await admin.post(`/admin/guides/${guideId}/action/`, { action: 'restore' }, { page: `/admin/guides/${guideId}/` });
  assert.equal((await new Client().get('/guides/premium-shopping-basics/')).status, 200);

  // A separate submission is rejected outright.
  const rejectedDraft = await author.post('/account/guides/new/', {
    title: 'Rejected Idea', summary: 'This one is going to be turned down.', body: 'Words '.repeat(60),
  }, { page: '/account/guides/new/' });
  const rejectedId = rejectedDraft.location.match(/guides\/(\d+)\/edit/)[1];
  await author.post(`/account/guides/${rejectedId}/submit/`, {}, { page: `/account/guides/${rejectedId}/edit/` });
  await admin.post(`/admin/guides/${rejectedId}/action/`, { action: 'reject', confirm: '1', note: 'Off topic.' }, { page: `/admin/guides/${rejectedId}/` });
  assert.equal((await new Client().get('/guides/rejected-idea/')).status, 404);
  const rejectedEditor = await author.get(`/account/guides/${rejectedId}/edit/`);
  assert.match(rejectedEditor.text, /status-badge[^>]*>Rejected/);
  assert.match(rejectedEditor.text, /can no longer be edited/);
});

test('slug protection keeps curated guides and system routes first', async () => {
  const draft = await author.post('/account/guides/new/', { title: 'Shopping', summary: '', body: '' }, { page: '/account/guides/new/' });
  const id = draft.location.match(/guides\/(\d+)\/edit/)[1];
  const editor = await author.get(`/account/guides/${id}/edit/`);
  assert.doesNotMatch(editor.text, /<code>\/guides\/shopping\/<\/code>/);
  const curated = await new Client().get('/guides/shopping/');
  assert.equal(curated.status, 200);
  assert.match(curated.text, /UV's Shopping/);
  assert.equal((await new Client().get('/guides/does-not-exist/')).status, 404);
  assert.equal((await new Client().get('/guides/template/')).status, 200);
});

test('admin guide deletion is soft first and permanent only with the exact slug', async () => {
  const draft = await author.post('/account/guides/new/', { title: 'Delete Me Later', summary: 'A guide that will be removed by an admin.', body: 'Text '.repeat(60) }, { page: '/account/guides/new/' });
  const id = draft.location.match(/guides\/(\d+)\/edit/)[1];
  assert.equal((await admin.post(`/admin/guides/${id}/action/`, { action: 'delete' }, { page: `/admin/guides/${id}/` })).status, 422);
  await admin.post(`/admin/guides/${id}/action/`, { action: 'delete', confirm: '1' }, { page: `/admin/guides/${id}/` });
  assert.match((await admin.get('/admin/guides/?filter=deleted')).text, /Delete Me Later/);
  assert.equal((await author.get(`/account/guides/${id}/edit/`)).status, 404);
  const wrong = await admin.post(`/admin/guides/${id}/action/`, { action: 'purge', confirm: '1', confirmation: 'nope' }, { page: `/admin/guides/${id}/` });
  assert.equal(wrong.status, 422);
  const purged = await admin.post(`/admin/guides/${id}/action/`, { action: 'purge', confirm: '1', confirmation: 'delete-me-later' }, { page: `/admin/guides/${id}/` });
  assert.equal(purged.status, 303);
  assert.equal((await admin.get(`/admin/guides/${id}/`)).status, 404);
  assert.match((await admin.get('/admin/audit/')).text, /Guide permanently deleted/);
});

test('avatars are processed, public only for active members, and removable', async () => {
  const { client } = await activeMember(admin, 'Avatarist');
  const uploaded = await client.upload('/account/profile/avatar/', 'avatar', 'me.png', 'image/png', tinyPng, {}, { page: '/account/profile/' });
  assert.equal(uploaded.status, 303);
  const profile = await new Client().get('/members/Avatarist/');
  const src = profile.text.match(/<img class="avatar avatar-xl" src="([^"]+)"/)?.[1];
  assert.ok(src, 'avatar rendered');
  const path = new URL(src, new URL('/members/Avatarist/', appBase)).pathname;
  const image = await new Client().get(path);
  assert.equal(image.status, 200);
  assert.match(image.headers.get('cache-control'), /public/);
  const svg = await client.upload('/account/profile/avatar/', 'avatar', 'me.svg', 'image/svg+xml', Buffer.from('<svg xmlns="http://www.w3.org/2000/svg"/>'), {}, { page: '/account/profile/' });
  assert.equal(svg.status, 422);
  await client.post('/account/profile/avatar/delete/', {}, { page: '/account/profile/' });
  assert.equal((await new Client().get(path)).status, 404);
});

test('password reset via captured email is single use and revokes sessions', async () => {
  clearRateLimits();
  const { client: existing } = await activeMember(admin, 'Forgetful');
  const request = await new Client().post('/account/password/forgot/', {
    email: 'forgetful@example.test', 'cf-turnstile-response': 'XXXX.DUMMY.TOKEN.XXXX',
  }, { page: '/account/password/forgot/' });
  assert.equal(request.status, 303);
  const unknown = await new Client().post('/account/password/forgot/', {
    email: 'nobody@example.test', 'cf-turnstile-response': 'XXXX.DUMMY.TOKEN.XXXX',
  }, { page: '/account/password/forgot/' });
  assert.equal(unknown.status, 303, 'same response for unknown addresses');
  const mail = capturedMail();
  const link = [...mail.matchAll(/https?:\/\/\S+\/account\/password\/reset\/\?token=([A-Za-z0-9_-]{43})/g)].at(-1);
  assert.ok(link, 'reset email captured');
  assert.doesNotMatch(mail, /nobody@example\.test/);

  const browser = new Client();
  const landing = await browser.get(`/account/password/reset/?token=${link[1]}`);
  assert.equal(landing.status, 303, 'token is moved out of the URL');
  assert.doesNotMatch(landing.location, /token=/);
  const form = await browser.get('/account/password/reset/');
  assert.match(form.text, /Choose a new password/);
  const mismatch = await browser.post('/account/password/reset/', { password: 'a fresh new passphrase', password_confirmation: 'different' }, { page: '/account/password/reset/' });
  assert.equal(mismatch.status, 422);
  const reset = await browser.post('/account/password/reset/', { password: 'a fresh new passphrase', password_confirmation: 'a fresh new passphrase' }, { page: '/account/password/reset/' });
  assert.equal(reset.status, 303);
  assert.equal((await existing.get('/account/')).status, 303, 'old sessions are signed out');
  assert.equal((await new Client().login('Forgetful', 'a fresh new passphrase')).status, 303);
  const reuse = new Client();
  await reuse.get(`/account/password/reset/?token=${link[1]}`);
  assert.match((await reuse.get('/account/password/reset/')).text, /invalid, has expired, or was already used/);
});

test('two-factor authentication enrolment, sign-in, replay protection, and recovery codes', async () => {
  clearRateLimits();
  const { client } = await activeMember(admin, 'Guarded');
  const setup = await client.get('/account/security/two-factor/');
  const secret = setup.text.match(/id="setup-key">([A-Z2-7 ]+)</)[1].replace(/\s/g, '');
  const wrong = await client.post('/account/security/two-factor/', { code: '000000', password: 'correct horse battery staple' }, { page: '/account/security/two-factor/' });
  assert.equal(wrong.status, 422);
  const enabled = await client.post('/account/security/two-factor/', { code: totp(secret), password: 'correct horse battery staple' }, { page: '/account/security/two-factor/' });
  assert.equal(enabled.status, 200);
  const codes = [...enabled.text.matchAll(/<code>([a-z0-9]{5}-[a-z0-9]{5})<\/code>/g)].map((match) => match[1]);
  assert.equal(codes.length, 10);
  assert.equal(enabled.headers.get('cache-control'), 'private, no-store');
  assert.equal((await client.get('/account/')).status, 200, 'the enrolling session stays signed in');

  const login = new Client();
  const step1 = await login.login('Guarded', 'correct horse battery staple');
  assert.equal(step1.status, 303);
  assert.match(step1.location, /login\/verify/);
  assert.equal((await login.get('/account/')).status, 303, 'not signed in before the second factor');
  // The enrolment consumed the current step, so wait for a fresh one if needed.
  let code = totp(secret);
  const reused = await login.post('/account/login/verify/', { code }, { page: '/account/login/verify/' });
  if (reused.status === 303) {
    // The enrolment happened in an earlier step; replay the same code from another sign-in.
    const replay = new Client();
    await replay.login('Guarded', 'correct horse battery staple');
    const again = await replay.post('/account/login/verify/', { code }, { page: '/account/login/verify/' });
    assert.equal(again.status, 422, 'a TOTP code cannot be used twice');
  } else {
    assert.equal(reused.status, 422, 'the enrolment code cannot be replayed');
    await sleep(31_000 - (Date.now() % 30_000));
    code = totp(secret);
    const verified = await login.post('/account/login/verify/', { code }, { page: '/account/login/verify/' });
    assert.equal(verified.status, 303);
  }

  const recovery = new Client();
  await recovery.login('Guarded', 'correct horse battery staple');
  assert.equal((await recovery.post('/account/login/verify/', { code: codes[0] }, { page: '/account/login/verify/' })).status, 303);
  const again = new Client();
  await again.login('Guarded', 'correct horse battery staple');
  assert.equal((await again.post('/account/login/verify/', { code: codes[0] }, { page: '/account/login/verify/' })).status, 422, 'recovery codes are single use');
});

test('direct access to private files, configuration, and sources is denied', async () => {
  for (const path of [
    '/src/App.php', '/src/bootstrap.php', '/vendor/autoload.php', '/templates/auth/login.php', '/migrations/0001_community_schema.php',
    '/bin/console', '/composer.json', '/composer.lock', '/phpunit.xml.dist', '/includes/helpers.php', '/includes/account_navigation.php',
    '/storage/', '/docker/mariadb/01-databases.sql', '/tests/community.test.mjs', '/.env', '/router.php',
  ]) {
    const response = await new Client().get(path);
    assert.ok([403, 404].includes(response.status), `${path}: ${response.status}`);
    assert.doesNotMatch(response.text, /<\?php|SQLSTATE|password/i, path);
  }
});

test('friendly errors never leak internals', async () => {
  const notFound = await new Client().get('/members/no-such-member/');
  assert.equal(notFound.status, 404);
  assert.match(notFound.text, /Page not found/);
  const badId = await admin.get('/admin/guides/999999/');
  assert.equal(badId.status, 404);
  const method = await new Client().request('/account/login/', { method: 'PUT' });
  assert.equal(method.status, 405);
  for (const response of [notFound, badId]) {
    assert.doesNotMatch(response.text, /Stack trace|\/var\/www|SQLSTATE|Uvs\\/);
  }
  const slashless = await new Client().get('/members');
  assert.equal(slashless.status, 301);
  assert.match(slashless.location, /\/members\/$/);
});

test('admin dashboard shows queues and counts; audit log is readable', async () => {
  const dashboard = await admin.get('/admin/');
  assert.equal(dashboard.status, 200);
  for (const label of ['Pending accounts', 'Guides in review', 'Needs changes', 'Published guides', 'Suspended']) {
    assert.match(dashboard.text, new RegExp(label));
  }
  const users = await admin.get('/admin/users/?q=GuideWriter');
  assert.match(users.text, /GuideWriter/);
  assert.match(users.text, /guidewriter@example\.test/, 'admins can see emails');
  const settings = await admin.get('/admin/settings/');
  assert.match(settings.text, /Automatically approve new accounts/);
  assert.match(settings.text, /Test mode/);
  assert.doesNotMatch(settings.text, /local-test-only|local-test-key/);
  assert.equal(ADMIN.username, 'TestAdmin');
});
