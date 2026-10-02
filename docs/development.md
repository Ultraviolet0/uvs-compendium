# Development workflow

## Start locally

Install Docker Desktop with Compose, Git, and Node.js 20 or newer. From the repository root, run `docker compose up -d --build`, then open <http://localhost:8080>. Stop with `docker compose down`. The only service is PHP with Apache; there is no database. The source is mounted read-only and the port binds to localhost.

The Compose image defaults to PHP 8.3, matching the version selected for the Hostinger site during the September 2026 pre-deployment audit. Never put production credentials in `.env.example` or commit `.env`. Review the image tag and CI PHP version together if production changes.

## Validate changes

Run `npm ci` and `npx playwright install chromium` once, then `npm test` while the local site is running. The suite checks PHP and JavaScript syntax, representative routes and assets, development-file denial, page structure, and known calculator inputs and outputs in a browser. PHP lint runs inside the Compose container, with a regression that proves an invalid PHP file fails lint. To validate against a separate local PHP/Apache installation instead, set `PHP_LINT_CONTAINER=0` and, if using installed Edge, `BROWSER_CHANNEL=msedge` before `npm test`. CI runs the same suite. If a change affects game mechanics, add a deterministic regression example before changing its implementation; record the governing source or accepted correction. Screenshots are useful evidence, but preserve the input values, selected game/version, expected output, and actual output in the bug report so the issue can become a repeatable test.

The Hellfire Premium Item Checker keeps its item tables in `calculators/premium-item-checker/js/data.mjs`, compatibility and slot rules in `rules.mjs`, vendor availability in `availability.mjs`, item and price calculations in `calculate.mjs` and `price.mjs`, and browser controls in `scripts.mjs`. Its direct regression cases are in `tests/premium-item-checker.test.mjs`; `tests/fixtures/premium-results.json` records representative outputs from the verified pre-refactor version. Keep a mechanics correction separate from a presentation refactor so output changes can be reviewed against that baseline.

The Premium Checker, Item Price, and Shop Qlvl now default to Hellfire multiplayer and accept explicit Diablo/Hellfire and single-player/multiplayer context. Shop Qlvl and basic-town source ranges use deepest dungeon level visited in single player; Hive/Crypt visits in Hellfire also reach the maximum town level. The Damage Calculator has a Diablo/Hellfire switch for its validated class, spell, and Hellfire-only item controls; its modeled damage has no player-count input. Warrior Repair has no mode switch because it models a shared durability operation. Run the direct mode cases in `tests/mode-rules.test.mjs` and the standalone/combined browser cases in `tests/site.test.mjs` when changing these rules.

The Item Price and Damage Calculator page URLs are `/calculators/item-price/` and `/calculators/damage/`. Their previous `/calculators/hellfire-item-price/` and `/calculators/hellfire-damage/` page URLs redirect permanently with query strings preserved; the old directories still hold shared calculator assets for cached pages.

## Branches and pull requests

Create a branch from current `main`, using `feature/`, `fix/`, `refactor/`, or `chore/` followed by a short topic. Keep the diff focused. Run the complete suite and inspect the diff for private data. Open a pull request to `main` describing behavior, affected versions, source evidence, and test results. Review and merge through the pull request process. CI never deploys; keep Hostinger deployment separate from ordinary development. Do not deploy a task branch as part of ordinary development.

## Runtime deployment package

Run `npm run build:runtime` to rebuild the ignored `build/runtime/` directory. The script copies an exact file allowlist: the root PHP entry and `.htaccess`, calculator and guide pages with their PHP fragments, CSS and JavaScript (including all premium-checker modules), shared includes, images/icons/video, the legacy `/shopqlvl/` redirect, and the three intentionally public reference PDFs. It deletes the previous output before rebuilding so old files cannot survive. New files in these directories require an explicit allowlist update.

Run `npm run test:runtime` to rebuild the package and test it as a separate local Apache document root on port 8081. This checks PHP and JavaScript syntax and runs the browser site tests against the packaged files. The command stops its test container afterward. The regular `npm test` also checks the package's contents and repeatability.

Only this runtime package should be considered for future Hostinger staging or production deployment. The full repository contains development and internal files and must not be copied into the public document root. This package does not configure Hostinger or deploy anything; the target mapping and production server behavior still need staging verification.

## Security and public files

Treat this public Git repository as public at every commit. Use invented sample values in screenshots and bug reports; crop or redact personal information, browser sessions, file paths, and credentials before attaching them. Never commit secrets, local configuration, logs, caches, or private screenshots. Rotate any credential that was committed, even if later removed.

The runtime package excludes Git metadata, development docs, tests, CI files, and local configuration. Its root `.htaccess` also blocks direct access to internal PHP fragments and directory listing while leaving published `index.php` and `reference/` URLs intact. **Before deploying it**, verify the host honors these directives or configure equivalent server rules. Test denial of `/includes/public_header.php`, `/calculators/breadcrumbs.php`, and a calculator's `calculator.php` on a staging copy. Never rely on `.gitignore` to protect files already deployed to the web server.

The Premium Item Checker loads ES modules. The local Apache image enables `mod_headers`; guarded `.htaccess` rules serve `.mjs` as `text/javascript` and set `Cache-Control: no-cache` so unchanged dependency URLs revalidate after a deployment. Before production use, verify a `200` response for both `scripts.mjs` and an imported file such as `rules.mjs` has a JavaScript `Content-Type` and `Cache-Control: no-cache`, then reload the checker after a module update. If the host lacks `mod_mime` or `mod_headers`, configure equivalent behavior in the virtual host or deployment pipeline. Hostinger behavior has not yet been verified for these modules or its CDN.
