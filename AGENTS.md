# AGENTS.md

## Project Overview

Laravel 12 API for soccer/sports predictions (app name: "SoccerApi"). MySQL database `sasport` via XAMPP. Laravel Sanctum for auth — public read endpoints, protected write endpoints.

## Commands

```bash
# Run full dev stack (artisan serve + queue worker + pail logs + Vite)
composer dev

# Run tests (clears config cache first, then phpunit)
composer test

# Run tests directly (skip config clear)
php artisan test

# Run a single test file
php artisan test tests/Feature/ExampleTest.php

# Code style (Laravel Pint, no custom config — uses defaults)
./vendor/bin/pint

# Migrate / seed
php artisan migrate:fresh --seed
```

Tests use SQLite `:memory:` (configured in `phpunit.xml`) regardless of the `.env` DB settings. No external services needed.

## Architecture

All API routes are under `/api/v1/` prefix (defined in `routes/api.php`). All entities use `Route::apiResource()`.

### Custom Query Filter System

Controllers use a `ApiFilter` pattern (`app/Filters/`). Each entity has a filter class extending `ApiFilter` that whitelists safe params and maps query operators. Query format:

```
GET /api/v1/team?name[eq]=Barcelona&country[eq]=Spain
```

Operators: `eq`, `gt`, `lt`, `gte`, `lte`. Filters return an array of `[column, operator, value]` triples passed to `->where()`.

### Non-Standard Primary Keys

Models use custom PKs, not `id`: `team_id`, `match_id`, `stage_id`, `tournament_id`, etc. Set via `$primaryKey` on each model. Bulk insert routes strip these IDs before inserting (`Arr::except`).

### Entity Pattern (for adding a new resource)

Each entity typically needs:
- `app/Models/{Name}.php` — set `$table`, `$primaryKey`, `$fillable`, `$timestamps = false`
- `app/Http/Controllers/{Name}Controller.php` — extends `Controller`, uses Filter + Collection + Resource
- `app/Filters/{Name}Filter.php` — extends `ApiFilter`, define `$safeParams`, `$columnMap`, `$operatorMap`
- `app/Http/Requests/Store{Name}Request.php`, `Update{Name}Request.php`
- `app/Http/Resources/{Name}Collection.php`, `{Name}Resource.php`
- `app/Policies/{Name}Policy.php` — exists as stubs (all methods return `false`)
- `database/migrations/` — uses InnoDB engine, `$table->increments('custom_pk_name')`
- `database/factories/`, `database/seeders/`

### CONMEBOL Data Services

Two external data sources for football data:

- **`FootballDataService`** — football-data.org v4 API. 12 free competitions (Premier League, La Liga, Bundesliga, etc.). Token via `FOOTBALL_DATA_API_TOKEN`.
- **`ApiFootballService`** — API-Football v3. 100 req/day free. Covers Copa Libertadores (id=13), Copa Sudamericana (id=11), and all major leagues. Token via `API_FOOTBALL_KEY`.
- **`TheSportsDBService`** — TheSportsDB v1 API. Free with key `123`. Available but does NOT cover Copa Libertadores or Sudamericana.

Sync commands:
```bash
# Sync European competitions from football-data.org
php artisan football:sync --type=competitions
php artisan football:sync --type=teams --competition=2021
php artisan football:sync --type=matches --competition=2021

# Sync Copa Libertadores + Sudamericana (API-Football only)
php artisan conmebol:sync
php artisan conmebol:sync --competition=libertadores
php artisan conmebol:sync --competition=sudamericana
php artisan conmebol:sync --season=2024
```

**Note:** API-Football free plan only supports seasons 2022-2024. Use `--season=2024` for current data.

## Gotchas

- **Auth enforced**: Sanctum auth middleware active on all write routes. Public GETs remain unprotected.
- **`timestamps` disabled** on all domain models (not User).
- **No `.pint.json`**: Pint uses all Laravel defaults.
- **Node.js required** for `composer dev` (runs `npx concurrently`).

## Development Progress

See `PLAN.md` for full development roadmap. All 8 phases complete (foundation, auth, invitations, predictions, scoring, external APIs, routes/forms, tests).

### Test Suite

71 tests, 162 assertions, 0 failures. Test files:
- `AuthTest.php` — register, login, logout, validation (9)
- `TournamentTest.php` — CRUD (6)
- `TeamTest.php` — CRUD + filter (7)
- `MatchTest.php` — CRUD (6)
- `GroupTest.php` — CRUD (6)
- `InvitationTest.php` — send, accept, cancel, list (11)
- `PredictionTest.php` — CRUD + business rules (8)
- `ScoringTest.php` — exact match, result, wrong, leaderboard (5)
- `OddsBetsTest.php` — odds, bets, groupbets (11)

PUT routes require all fields for updates. Tests send complete payloads via `putJson`.
