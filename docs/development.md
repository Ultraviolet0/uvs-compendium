# Development workflow

## Start locally

Install Docker Desktop with Compose, Git, and Node.js 20 or newer. From the repository root:

```sh
docker compose build web
docker compose run --rm composer install      # PHP dependencies into vendor/ (dev tools included)
docker compose up -d --wait                   # MariaDB, the site, and the isolated test service
docker compose exec -u www-data web php bin/console migrate
printf 'a local admin passphrase\n' | docker compose exec -T -u www-data web php bin/console admin:create --username=LocalAdmin --email=admin@example.test --password-stdin
```

Open <http://localhost:8080>. Stop with `docker compose down` (add `--volumes` to discard the local database and uploads).

Services (all ports bind to 127.0.0.1 only):

| Service | Purpose |
| --- | --- |
| `db` | MariaDB 11.4 with a persistent `db-data` volume. It holds `uvs_dev` and the disposable `uvs_test`, each with its own placeholder user. No port is published; use `docker compose exec db mariadb -uroot -plocal-root-only`. |
| `web` | The site on <http://localhost:8080>, `UVS_ENV=development`, database `uvs_dev`, uploads in the `web-storage` volume, Turnstile in offline test mode, email captured to storage. |
| `web-test` | The same code on <http://localhost:8082>, `UVS_ENV=test`, database `uvs_test`, its own storage volume. Automated tests only ever use this service. |
| `composer` | Tools profile: `docker compose run --rm composer <command>`. |

The source is mounted read-only. The credentials in `compose.yaml` and `docker/mariadb/` are public placeholders for disposable local containers; production configuration is a private file described in [configuration.md](configuration.md). Run console commands as `www-data` (`docker compose exec -u www-data …`) so files they create in storage stay writable by Apache.

Useful commands:

```sh
docker compose exec -u www-data web php bin/console              # list commands
docker compose exec -u www-data web php bin/console migrate:status
docker compose exec -u www-data web-test php bin/console db:reset-test   # refuses unless UVS_ENV=test and the DB ends in _test
docker compose exec -u www-data web sh -c 'cat /var/uvs/storage/mail/*.eml'   # captured local email
```

The Compose image defaults to PHP 8.3, matching the version selected for the Hostinger site during the September 2026 pre-deployment audit. Never put production credentials in `.env.example` or commit `.env`. Review the image tag and CI PHP version together if production changes.

## Validate changes

Run `npm ci` and `npx playwright install chromium` once, then `npm test` while the local services are running. The suite checks PHP and JavaScript syntax, runs the PHPUnit unit and database-integration tests inside `web-test`, and then runs the Node test files one at a time against `web-test`: representative routes and assets, development-file denial, page structure, known calculator inputs and outputs in a browser, and the community flows (signup, approval, sessions, CSRF, authorization, profiles, characters, guide drafting/moderation/publication, revisions, media restrictions, password reset, two-factor authentication, themes, and mobile layout). The community tests reset the `uvs_test` database and create a throwaway `TestAdmin`; they never touch `uvs_dev`. They need no internet access, email account, or Cloudflare account. Set `PHPUNIT=0` to skip PHPUnit, or run it alone with `docker compose exec -u www-data web-test vendor/bin/phpunit`. `BROWSER_EXECUTABLE` can point the browser tests at a specific Chromium binary. PHP lint runs inside the Compose container, with a regression that proves an invalid PHP file fails lint. To validate against a separate local PHP/Apache installation instead, set `PHP_LINT_CONTAINER=0` and, if using installed Edge, `BROWSER_CHANNEL=msedge` before `npm test`. CI runs the same suite. If a change affects game mechanics, add a deterministic regression example before changing its implementation; record the governing source or accepted correction. Screenshots are useful evidence, but preserve the input values, selected game/version, expected output, and actual output in the bug report so the issue can become a repeatable test.

The Hellfire Premium Item Checker keeps its item tables in `calculators/premium-item-checker/js/data.mjs`, compatibility and slot rules in `rules.mjs`, vendor availability in `availability.mjs`, item and price calculations in `calculate.mjs` and `price.mjs`, and browser controls in `scripts.mjs`. Its direct regression cases are in `tests/premium-item-checker.test.mjs`; `tests/fixtures/premium-results.json` records representative outputs from the verified pre-refactor version. Keep a mechanics correction separate from a presentation refactor so output changes can be reviewed against that baseline.

The Premium Checker, Item Price, and Shop Qlvl now default to Hellfire multiplayer and accept explicit Diablo/Hellfire and single-player/multiplayer context. Shop Qlvl and basic-town source ranges use deepest dungeon level visited in single player; Hive/Crypt visits in Hellfire also reach the maximum town level. The Damage Calculator has a Diablo/Hellfire switch for its validated class, spell, and Hellfire-only item controls; its modeled damage has no player-count input. Warrior Repair has no mode switch because it models a shared durability operation. Run the direct mode cases in `tests/mode-rules.test.mjs` and the standalone/combined browser cases in `tests/site.test.mjs` when changing these rules.

The Item Price and Damage Calculator page URLs are `/calculators/item-price/` and `/calculators/damage/`. Their previous `/calculators/hellfire-item-price/` and `/calculators/hellfire-damage/` page URLs redirect permanently with query strings preserved; the old directories still hold shared calculator assets for cached pages.

## Branches and pull requests

Create a branch from current `main`, using `feature/`, `fix/`, `refactor/`, or `chore/` followed by a short topic. Keep the diff focused. Run the complete suite and inspect the diff for private data. Open a pull request to `main` describing behavior, affected versions, source evidence, and test results. Review and merge through the pull request process. CI never deploys; keep Hostinger deployment separate from ordinary development. Do not deploy a task branch as part of ordinary development.

## Runtime deployment package

Run `npm run build:runtime` to rebuild the ignored `build/runtime/` directory. The script copies an exact file allowlist: the root PHP entry and `.htaccess`, calculator and guide pages with their PHP fragments, CSS and JavaScript (including all premium-checker modules), shared includes, images/icons/video, the legacy `/shopqlvl/` redirect, the privacy page, the community front controller and `bin/console`, and the three intentionally public reference PDFs. It also copies every PHP file in the reviewed application directories `src/`, `templates/`, and `migrations/`, installs production Composer dependencies from `composer.lock` (no dev packages; VCS data, package tests, and docs are pruned; timestamps are fixed for reproducibility), and writes a deny-all `.htaccess` into each server-only directory. It deletes the previous output before rebuilding so old files cannot survive. New files outside those application directories require an explicit allowlist update. `RUNTIME_COMPOSER=docker` forces dependency installation through the project's Docker image. Uploaded media, configuration, tests, docs, Docker files, and `composer.json` are never packaged.

Run `npm run test:runtime` to rebuild the package and test it as a separate Compose project (its own database and storage) with the package as the Apache document root on ports 8081 and 8083. This checks PHP and JavaScript syntax and runs the site, community, and browser tests against the packaged files. The command removes its containers and volumes afterward. `COMPOSE_NO_BUILD=1` reuses an already built image. The regular `npm test` also checks the package's contents and repeatability.

Guide "Updated" dates in the runtime package come from each guide page's latest Git commit, so a checkout or redeploy does not change them. Build from a full Git checkout; CI fetches the full history. The source-only local site falls back to each guide file's filesystem date. "Published" dates remain editorial metadata.

Only this runtime package should be considered for future Hostinger staging or production deployment. The full repository contains development and internal files and must not be copied into the public document root. This package does not configure Hostinger or deploy anything; the target mapping and production server behavior still need staging verification.

Before a production deployment that changes the schema, run `php bin/console migrate` from the deployed directory; see [configuration.md](configuration.md).

## Security and public files

Treat this public Git repository as public at every commit. Use invented sample values in screenshots and bug reports; crop or redact personal information, browser sessions, file paths, and credentials before attaching them. Never commit secrets, local configuration, logs, caches, or private screenshots. Rotate any credential that was committed, even if later removed.

The runtime package excludes Git metadata, development docs, tests, CI files, and local configuration. Its root `.htaccess` also blocks direct access to internal PHP fragments and directory listing while leaving published `index.php` and `reference/` URLs intact. **Before deploying it**, verify the host honors these directives or configure equivalent server rules. Test denial of `/includes/public_header.php`, `/calculators/breadcrumbs.php`, and a calculator's `calculator.php` on a staging copy. Never rely on `.gitignore` to protect files already deployed to the web server.

The Premium Item Checker loads ES modules. The local Apache image enables `mod_headers`; guarded `.htaccess` rules serve `.mjs` as `text/javascript` and set `Cache-Control: no-cache` so unchanged dependency URLs revalidate after a deployment. Before production use, verify a `200` response for both `scripts.mjs` and an imported file such as `rules.mjs` has a JavaScript `Content-Type` and `Cache-Control: no-cache`, then reload the checker after a module update. If the host lacks `mod_mime` or `mod_headers`, configure equivalent behavior in the virtual host or deployment pipeline. Hostinger behavior has not yet been verified for these modules or its CDN.

The community application has its own documents: [architecture](architecture.md), [configuration and deployment](configuration.md), [security model](security.md), and [publishing and administration](publishing.md).
