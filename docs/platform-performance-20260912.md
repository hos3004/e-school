# Platform performance — 2026-09-12

Work and verification are performed only on SSH SCHOOL-WEB.
Candidate: /opt/eschool-worktrees/platform-performance-20260912.
Production baseline: 154080b1453015f2b019b306c136852c0af62aef.

## Behavior

- Nginx compresses hashed Vite JavaScript, CSS and SVG assets at gzip level 5.
  Successful assets receive Cache-Control: public, max-age=31536000, immutable
  and Vary: Accept-Encoding. HTML, the manifest, uploads, and missing assets
  do not receive this long cache policy. Security headers remain present.
- Inertia resolves translations using the rendered component, including error
  pages and authentication variants. Public pages receive only their public
  section; learning pages omit marketing and management dictionaries. Shared
  profile/notification dependencies remain available. Dictionaries are lazy
  props, so unrelated partial reloads do not resolve or transmit them.
  Missing language entries fall back to the configured fallback, then Arabic.
- Sanctum always includes APP_URL's origin in addition to the explicit
  SANCTUM_STATEFUL_DOMAINS list. The old production list included localhost
  only. Alternate first-party hosts (www.telecourse.org) must still be listed.
  No wildcard hosts are trusted and auth/CSRF middleware remains in place.
- Notification polling uses a single request and a 15-second timeout. It stops
  on 401/403/419, aborts when hidden/offline or unmounted, and resumes only
  when visible/online. Normal polling is every 30 seconds; temporary errors
  back off at 60/120/240/300 seconds and respect Retry-After. Success resets
  the delay. Expired-session polling restarts only on a new mounted session.
- Request timing uses the PHP request start and includes application bootstrap.
  Nginx logs request/upstream duration without query strings, cookies or bodies.
  Laravel logs slow route names, duration, query count and cumulative query time.
  Slow-query logs contain only a SHA-256 query fingerprint, connection, time and
  route; SQL text and bindings are excluded.

## Diagnostics

Environment controls:

| Variable | Default |
| --- | --- |
| PERFORMANCE_ENABLED | true |
| PERFORMANCE_SLOW_REQUEST_MS | 1000 |
| PERFORMANCE_SLOW_QUERY_MS | 500 |
| PERFORMANCE_MAX_QUERY_LOGS | 10 per request |
| PERFORMANCE_SERVER_TIMING | false |
| PERFORMANCE_LOG_DAYS | 14 |

Laravel diagnostic files: storage/logs/performance-YYYY-MM-DD.log.
Optional Server-Timing exposes app and database durations only.
PHP-FPM: include /var/www/html/docker/php/fpm-observability.conf from the
existing production pool configuration. The slow threshold is 3 seconds;
stack traces go to storage/logs/php-fpm-slow.log. Install
docker/php/fpm-slowlog.logrotate as /etc/logrotate.d/eschool-fpm-performance
to retain 14 daily rotations. A 4-second isolated request successfully
produced a real FPM stack trace without adding container capabilities.

PostgreSQL is not restarted by this release. Application query diagnostics
provide safe initial evidence. pg_stat_statements requires
shared_preload_libraries and a PostgreSQL restart; schedule that separately
if aggregate SQL analysis is needed. No indexes or data migrations are
introduced without evidence of a slow query.

## Verification

- PHP lint, frontend lint, TypeScript and production asset build pass.
- 99 page components and recursively imported components checked against
  their Arabic translation scopes: no missing static keys/prefixes.
- Focused PHP suite: 61 passing tests / 786 assertions.
- Chromium polling suite: 6 passing tests (401/403/419, hidden tab, cleanup,
  exponential delay, Retry-After, and stale aborted response).
- Real browser login and session API checks: 8 passing tests across desktop
  and mobile for synthetic student, teacher and administrator accounts.
  Authenticated API returns 200; requests without cookies return 401.
- Full PHP run: 1468 passed; two Filament tests initially ran before the
  asset manifest existed. Both affected suites plus architecture were rerun: 95 passed / 2733 assertions.
- PHPStan: 875 existing errors in both the untouched baseline and candidate;
  comparison of file/message/identifier multisets found zero new errors.
  The repository-wide zero-error gate remains unmet; this is not a green CI claim.
- No application or financial data is migrated or altered in production.

Synthetic browser fixtures are created by scripts/performance-browser-fixture.php,
which refuses all environments except the isolated performance database.
Run the browser suite only with PERFORMANCE_BROWSER_REVIEW=1,
PLAYWRIGHT_CHROMIUM_EXECUTABLE=/usr/bin/chromium and the isolated E2E_BASE_URL.
Never run this fixture on production.

## Release and rollback

Before deployment, verify production still has the baseline head and no tracked
edits; save a restricted backup of code, runtime environment, production compose
and FPM configuration, asset manifest/build, and database. Merge only the reviewed
candidate. Copy hashed build assets without deleting older hashes so already-open
tabs can still load their chunks; replace manifest.json last.

Add the canonical and www host to the existing Sanctum environment list. Include
the FPM diagnostic file from the existing production pool; preserve its worker
counts and timeouts. Rebuild Laravel config/routes/views, validate nginx -t and
php-fpm -t, reload Nginx/FPM and restart long-lived workers gracefully.
Verify public login, gzip/cache headers, guest 401 and worker health.

Rollback consists of reverting the release commit and restoring the saved
manifest, environment and FPM configuration, then rebuilding Laravel caches
and reloading the same services. Retain old asset hashes. No database rollback
is needed for this release.
