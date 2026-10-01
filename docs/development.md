# Development workflow

## Start locally

Install Docker Desktop with Compose, Git, and Node.js 20 or newer. From the repository root, run `docker compose up -d --build`, then open <http://localhost:8080>. Stop with `docker compose down`. The only service is PHP with Apache; there is no database. The source is mounted read-only and the port binds to localhost.

The Compose image defaults to PHP 8.3. **Confirm Hostinger's production PHP major/minor version** and set `PHP_VERSION` in a local `.env` before relying on version parity. Never put production credentials in `.env.example` or commit `.env`. The image tag and CI PHP version should then be aligned with production and reviewed together.

## Validate changes

Run `npm ci` and `npx playwright install chromium` once, then `npm test` while the local site is running. The suite checks PHP and JavaScript syntax, representative routes and assets, development-file denial, page structure, and known calculator inputs and outputs in a browser. PHP lint runs inside the Compose container, with a regression that proves an invalid PHP file fails lint. To validate against a separate local PHP/Apache installation instead, set `PHP_LINT_CONTAINER=0` and, if using installed Edge, `BROWSER_CHANNEL=msedge` before `npm test`. CI runs the same suite. If a change affects game mechanics, add a deterministic regression example before changing its implementation; record the governing source or accepted correction. Screenshots are useful evidence, but preserve the input values, selected game/version, expected output, and actual output in the bug report so the issue can become a repeatable test.

The Hellfire Premium Item Checker keeps its item tables in `calculators/premium-item-checker/js/data.mjs`, compatibility and slot rules in `rules.mjs`, vendor availability in `availability.mjs`, item and price calculations in `calculate.mjs` and `price.mjs`, and browser controls in `scripts.mjs`. Its direct regression cases are in `tests/premium-item-checker.test.mjs`; `tests/fixtures/premium-results.json` records representative outputs from the verified pre-refactor version. Keep a mechanics correction separate from a presentation refactor so output changes can be reviewed against that baseline.

## Branches and pull requests

Create a branch from current `main`, using `feature/`, `fix/`, `refactor/`, or `chore/` followed by a short topic. Keep the diff focused. Run the complete suite and inspect the diff for private data. Open a pull request to `main` describing behavior, affected versions, source evidence, and test results. Review and merge through the pull request process. Hostinger deploys `main` separately; CI never deploys. Do not deploy a task branch as part of ordinary development.

## Security and public files

Treat this public Git repository as public at every commit. Use invented sample values in screenshots and bug reports; crop or redact personal information, browser sessions, file paths, and credentials before attaching them. Never commit secrets, local configuration, logs, caches, or private screenshots. Rotate any credential that was committed, even if later removed.

If Hostinger checks out the repository into Apache's public document root, Git metadata, development docs, tests, CI files, local config, and internal PHP fragments can otherwise be requested directly. The root `.htaccess` blocks those paths and directory listing while leaving published `index.php` and `reference/` URLs intact. **Before deploying it**, verify Hostinger uses Apache with `AllowOverride` and `mod_rewrite`, and request the same deny rules in the virtual host or deploy only public files if it does not. Test denial of `/.git/config`, `/AGENTS.md`, `/docs/development.md`, `/tests/`, `/.env`, `/calculators/breadcrumbs.php`, and a calculator's `calculator.php` on a staging copy. Never rely on `.gitignore` to protect files already deployed to the web server.

The Premium Item Checker loads ES modules. The local Apache image enables `mod_headers`; guarded `.htaccess` rules serve `.mjs` as `text/javascript` and set `Cache-Control: no-cache` so unchanged dependency URLs revalidate after a deployment. Before production use, verify a `200` response for both `scripts.mjs` and an imported file such as `rules.mjs` has a JavaScript `Content-Type` and `Cache-Control: no-cache`, then reload the checker after a module update. If the host lacks `mod_mime` or `mod_headers`, configure equivalent behavior in the virtual host or deployment pipeline. Hostinger behavior has not yet been verified.
