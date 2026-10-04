# Accounts, guide publishing, and administration

How the community features behave for members and administrators.

## Accounts and approval

1. A visitor creates an account at `/account/signup/` (username, email, password; Turnstile, a honeypot, and a minimum form time guard against bots). They are signed in straight away.
2. **Automatic approval off (default):** the account is **Pending**. Pending members can sign in, set up their profile and avatar, list characters, change their password or email, and enable two-factor authentication. They cannot write or submit guides and have no public profile.
3. An administrator approves, rejects, or suspends the account under **Admin → Users** (or straight from the dashboard). Approval keeps the member signed in; their session identifier is rotated on their next request. If email is configured, they receive an approval notice.
4. **Automatic approval on:** new signups are **Active** immediately. Turning it on never changes existing pending, rejected, or suspended accounts.

| Status | Sign in | Profile & security | Write/submit guides | Public profile |
| --- | --- | --- | --- | --- |
| Pending | ✓ | ✓ | | |
| Active | ✓ | ✓ | ✓ | ✓ |
| Rejected | | | | |
| Suspended | | | | |

Suspension and rejection sign the member out everywhere at once and can include a short message shown to them. Reactivation restores access. Each change is recorded in the audit log, and administrators can keep private notes on any account.

## Writing a guide

Active members open **My guides → Write a guide**. The editor offers:

- a formatting toolbar (headings, bold, italic, links, lists, quotes, code blocks, callouts, tables, images, YouTube) with keyboard shortcuts (Ctrl/⌘ + B, I, K, S) and arrow-key navigation;
- **Write / Preview / Side by side** views — the preview is rendered by the server with exactly the same rules as the public page;
- autosave every 30 seconds (and when the tab is hidden), with a warning if the guide changed in another window, and a prompt before leaving with unsaved changes;
- image uploads (alt text encouraged) inserted as `![Description](media:ID)`;
- a formatting help panel.

Markdown summary:

````markdown
## Section heading
### Subheading
**bold**, _italic_, [link](https://example.com)

> [!TIP]
> A tip box. Also [!NOTE] and [!WARNING].

```youtube
https://youtu.be/VIDEO_ID
Optional caption
```
````

Raw HTML, scripts, iframes, and links to unsupported schemes are shown as text or dropped. The guide's address is `/guides/<slug>/`; the slug follows the title until the guide is first published and is fixed afterwards. Curated guides (for example `/guides/shopping/`) always keep their URLs.

## Guide states

| State shown | Meaning | Author can |
| --- | --- | --- |
| Draft | Private working copy | edit, preview, submit, delete |
| In review | Submitted; locked | withdraw back to draft |
| Needs changes | Returned with an administrator note | edit and resubmit |
| Approved | Approved, not yet published | withdraw |
| Published | Live | start an update (the live version stays until the update is published) |
| Published · update in review / needs changes / rejected | An update to a live guide is being reviewed | as above |
| Hidden | Unpublished by an administrator | edit and submit an update |
| Rejected | Not accepted | view; delete if it was never published |

A revision is stored at every submission and resubmission, every administrator edit, and every publication (identical content reuses the existing revision). Revisions are read-only.

## Moderating guides (administrators)

**Admin → Guides** has tabs for the review queue, needs changes, approved-but-unpublished, published, hidden, rejected, drafts, deleted, and all guides, with search by title, URL, or author. A guide's review page shows its state, a rendered preview of the working copy, a line diff against the published version, revisions (each with a diff against the previous revision or the current working copy), and its moderation history.

| Action | Effect |
| --- | --- |
| Approve and publish | The working copy becomes the public version now. |
| Approve | Marks it approved without publishing. |
| Publish | Publishes an approved working copy. |
| Request changes | Requires a note; the author revises and resubmits. |
| Reject | Declines the submission (a live version, if any, stays live). Requires confirmation. |
| Hide (unpublish) | Removes it from readers; reversible with **Restore to public**. Requires confirmation. |
| Move to deleted | Soft delete; hidden everywhere and recoverable. Requires confirmation. |
| Recover from deleted | Restores the previous state. |
| Delete permanently | Only for soft-deleted guides; requires confirmation and typing the slug. Removes revisions and images. |

Administrators can also edit the working copy (recorded as an *admin edit* revision) and set a custom slug before first publication. Publishing is always a separate, explicit action.

## Settings

**Admin → Settings**:

- Allow new account registrations (default on)
- Automatically approve new accounts (default **off**)
- Accept guide submissions (default on; drafts are still allowed when off)
- Allow anonymous guide submissions (reserved for a later phase; disabled)
- Maximum image upload size, storage quota per member, and images per guide (bounded by hard limits in the private configuration)

The page also shows the state of Turnstile, email, and storage without revealing secrets. Every setting change is audited.

## Audit log

**Admin → Audit log** lists approvals, rejections, suspensions, reactivations, role changes, note updates, guide moderation actions, setting changes, and command-line recovery actions, with the actor, target, time, and non-secret details. Passwords, tokens, secrets, and session identifiers are never recorded.

## Members and profiles

`/members/` (linked from the footer) lists active members; `/members/<username>/` shows the avatar, join date, optional bio, preferred game, website, Discord name, characters, and published guides. Email addresses are never shown publicly. Characters are descriptive: any listed class may be paired with either game, since DevilutionX options and personal setups vary.
