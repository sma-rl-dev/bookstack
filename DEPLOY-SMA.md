# BookStack — Deploy & Seed Notes (tester-env baseline)

## Source

- **Upstream:** https://github.com/BookStackApp/BookStack
- **Fork:** https://github.com/Smartesting/bookstack
- **Baseline branch:** `tester-env-baseline`
- **Baseline commit:** `d421a191935e9689709f5a949c3d23dfd87d6ecc` (BookStackApp/BookStack dev branch, v26.05 release)
- **Docker image tag:** `tester-env-bookstack:<hash>` (mutates per patch-set; manual invocations default to `:dev`)

## Deploy

```bash
./tester-env deploy [--run-id <id>] [--port <port>]
```

- Ensures `.env` exists with deterministic `APP_KEY=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaab` and `APP_ENV=local`
- Builds `app` + `db` + `mailhog` services via `docker compose` using `docker-compose.yml` (upstream) + `docker-compose.tester-env.yml` overlay
- Overlay sets `APP_URL=http://172.17.0.1:${DEV_PORT:-8080}` (Docker bridge gateway) so cross-container browser access works
- Overlay pins `app.image: ${IMAGE_TAG:-tester-env-bookstack:dev}` (content-addressed per baseline+patch-set via `scripts/rl-env`; manual runs default to `:dev`)
- Overlay pins the compose network to `${BOOKSTACK_SUBNET:-10.180.180.0/24}` (explicit `/24`; Docker auto-IPAM spills into protected `192.168.0.0/16`, so auto-allocation is not used)
- Builds frontend assets (`npm install && npm run build`) as root in a one-shot node container
- Waits for HTTP 200/302 response on http://localhost:$PORT
- Host URL: `http://localhost:$PORT`
- Browser MCP URL: `http://172.17.0.1:$PORT`
- Default credentials: `admin@admin.com` / `password`

## Seed

```bash
./tester-env seed
```

- Runs `php artisan migrate:fresh --force` then `php artisan db:seed --class=TesterEnvSeeder --force`
- Deterministic seed: uses fixed timestamp `2026-02-15 09:30:00`, fixed bcrypt hash, `Str::slug()` for entity slugs
- Populates: 2 shelves, 4 books, 6 chapters, 12 pages, 3 users (admin, editor Ari Patel, viewer Mina Chen)
- Searchable phrase: "bluebird escalation" in Priority Matrix page

## Verify

```bash
./tester-env verify
```

- Checks entity counts, user authentication, search phrase presence, slug integrity, route lookup
- Zero-diff on repeated runs after reset+deploy+seed

## Chapter-export control seed (scoped, additive)

```bash
./tester-env seed-chapter-export
./tester-env verify-chapter-export
```

- `seed-chapter-export` runs the ordinary `seed` first (base `TesterEnvSeeder` untouched: 2 shelves, 4 books, 6 chapters, 12 pages, 3 users), then the additive `TesterEnvChapterExportSeeder`
- Adds ONLY: `Escalation Contacts` second chapter of `Support Playbooks` (priority 2) with one ordinary page `Escalation Roster` (Ari Patel primary on-call, Mina Chen secondary; no `bluebird` text, no fixture provenance)
- Rebuilds joint permissions and the search index, same as the base seeder; create-if-missing so repeats are stable
- `verify-chapter-export` reuses `tester-env-verify.php` via include, then asserts the strict control: 2 chapters in Support Playbooks, roster chapter/book scope, slugs/URLs/route lookups, Triage still 2 pages, Glossary still standalone, `bluebird escalation` search target still exactly 1 page
- These explicit commands support manual runs. For centralized chapter setup, select the adapter described below.

## Chapter-export CLI adapter (scoped)

- The central runner calls the selected `app_cli` with standard `reset`, `deploy`, and `seed` commands; it does not execute legacy `deploy.seed_command` metadata.
- For `bookstack-chapter-markdown-export-scope-001`, set `deploy.app_cli` to `tester-env-chapter-export`. Its standard `seed` and `verify` commands invoke the scoped chapter commands. All other commands and their arguments, environment and exit status pass through to `tester-env`.
- Set chapter `deploy.copy_paths` to `["tester-env", "database/seeders/TesterEnvChapterExportSeeder.php", "tester-env-verify-chapter-export.php"]`; the runner archives the selected CLI and declared companions from the immutable tooling ref.

## Reset

```bash
./tester-env reset
```

- `docker compose down -v` — removes containers AND database volume
- Restores host worktree ownership (`alpine chown -R <host-uid:gid>`, exact `SCRIPT_DIR` bind, `--network none`) so root-owned container build trees never block central `git worktree remove`
- Full idempotency: `reset && deploy && seed && verify` produces identical state every time

## Stop

```bash
./tester-env stop
```

- Stops containers without removing volumes (preserves database)

## Browser Smoke Evidence (2026-06-02)

- **Browser MCP URL:** `http://172.17.0.1:8080`
- **Login:** `admin@admin.com` / `password` succeeds, lands on `/home`
- **Shelves:** Operations Hub and Product Knowledge Base visible on `/shelves`
- **Books:** Support Playbooks, Product Launch Q2 2026, Engineering Runbooks, Customer Research Archive visible
- **Chapter/Page:** Triage Workflows chapter accessible; Priority Matrix page loads at `/books/support-playbooks/page/priority-matrix`
- **Search:** "bluebird escalation" returns Priority Matrix page with seeded phrase visible
- **Safe mutation:** New page creation via browser works without affecting seed integrity

## Mutation Smoke Evidence

- Mutation: `lang/en/auth.php` line 15 `'Log in'` → `'MUTATION-SMOKE-TEST'`
- Before: heading `<h1>Log In</h1>`, nav link "Log in", button "Log In"
- After: heading `<h1>Mutation-Smoke-Test</h1>`, nav link "MUTATION-SMOKE-TEST", button "Mutation-Smoke-Test"
- Change visible immediately (source mount + Laravel local env), no rebuild needed

## Port

- Default `8080`, configurable via `--port` flag or `DEV_PORT` env var
