# SoccerApi — Plan de Desarrollo

> Plataforma de predicciones deportivas con competencias, grupos y scoring.
> Los usuarios crean grupos privados, invitan personas, hacen predicciones de partidos
> y el sistema asigna puntaje basado en reglas.

---

## Fase 1: Estabilizar Foundation — ✅ COMPLETADA

### 1.1 Fix model table names
- [x] `Team.php` — `$table = 'Teams'` → `'teams'`
- [x] `Tournament.php` — `$table = 'Tournaments'` → `'tournaments'`
- [x] `Groups.php` — `$table = 'Groups'` → `'groups'`
- [x] `GroupMembers.php` — `$table = 'GroupMembers'` → `'groupmembers'`
- [x] `GroupBets.php` — `$table = 'GroupBets'` → `'groupbets'`
- [x] `GroupPredictions.php` — `$table = 'GroupPredictions'` → `'grouppredictions'`
- [x] `PredictionResults.php` — `$table = 'PredictionResults'` → `'predictionresults'`
- [x] `Odds.php` — `$table = 'Odds'` → `'odds'`
- [x] `Transactions.php` — `$table = 'Transactions'` → `'transactions'`
- [x] `Bonuses.php` — `$table = 'Bonuses'` → `'bonuses'`
- [x] `Bets.php` — `$table = 'Bets'` → `'bets'`
- [x] `Predictions.php` — `$table = 'Predictions'` → `'predictions'`
- [x] `TournamentStage.php` — `$table = 'TournamentStage'` → `'tournamentstage'`

### 1.2 Fix PredictionResults PK and fillable
- [x] `PredictionResults.php` — `$primaryKey = 'transaction_id'` → `'result_id'`
- [x] `PredictionResults.php` — quitar `is_correct` de `$fillable` (columna no existe en migration)

### 1.3 Fix model relationships (belongsTo ↔ hasMany invertidas)
- [x] `Team.php` — `hometeam()`/`awayteam()` → `hasMany`
- [x] `Tournament.php` — `matches()` → `hasMany`
- [x] `TournamentStage.php` — `matches()` → `hasMany`
- [x] `Matches.php` — `tournament()`, `stage()`, `home()`, `away()` → `belongsTo`
- [x] `Matches.php` — `odds()` → `hasMany` (era `odd()` con belongsTo)
- [x] `Odds.php` — `match()` → `belongsTo`
- [x] `GroupMembers.php` — `user()` → `belongsTo` (renamed de `users()`)
- [x] `GroupBets.php` — `createdby()` FK corregido a `created_by`
- [x] `GroupPredictions.php` — `group()` y `prediction()` → `belongsTo`
- [x] `Predictions.php` — `match()` y `user()` → `belongsTo` (corregido `App\Models\Users` → `User`)
- [x] `PredictionResults.php` — `prediction()` → `belongsTo`
- [x] `Transactions.php` — `user()` → `belongsTo`
- [x] `Bonuses.php` — `user()` → `belongsTo`
- [x] `Bets.php` — `groupbets()` → `hasMany`

### 1.4 Fix routes
- [x] Mover prediction/groupprediction/predictionresults/transactions/bonuses dentro del prefix `v1`
- [x] Eliminar rutas DELETE duplicadas (ya cubiertas por apiResource)
- [x] Eliminar todo el código commented-out de rutas

### 1.5 Fix string-based controller refs
- [x] Reemplazar `'App\Http\Controllers\XController@method'` por `[XController::class, 'method']`

### 1.6 Fix Bonuses model
- [x] Agregar `is_used` a `$fillable`

### 1.7 Fix controller messages
- [x] PredictionsController — "Odds created" → "Prediction created/updated/deleted"
- [x] PredictionResultsController — "Odds created" → "Prediction result created/updated/deleted"
- [x] PredictionResultsController — quitar `is_correct` de store/update
- [x] TransactionsController — "Odds created" → "Transaction created/updated/deleted"
- [x] MatchesController — "Task deleted" → "Match deleted"
- [x] OddsController — quitar `away_team_id` (no existe en tabla), "Task deleted" → "Odds deleted"
- [x] BonusesController — "groups created" → "Bonus created/deleted"
- [x] BetsController — "groups created" → "Bet created/updated"
- [x] GroupPredictionsController — "groups created" → "Group prediction created/updated"
- [x] GroupBetsController — "groups created" → "Group bet created/updated"

### 1.8 Fix filename typo
- [x] `PredictionsFIlter.php` → `PredictionsFilter.php`

---

## Fase 2: Autenticación y Usuarios — ✅ COMPLETADA

- [x] 2.1 Implementar AuthController (register, login, logout) con Sanctum
- [x] 2.2 Crear UserController (perfil, actualizar perfil, cambiar contraseña)
- [x] 2.3 Registrar rutas auth y proteger endpoints con `auth:sanctum`

---

## Fase 3: Sistema de Invitaciones a Grupos — ✅ COMPLETADA

- [x] 3.1 Crear migration + model `Invitation` (tabla `invitations`)
- [x] 3.2 Endpoint: `POST /api/v1/invitation` — enviar invitación por email (solo admin del grupo)
- [x] 3.3 Endpoint: `POST /api/v1/invitation/accept-code` — unirse a grupo vía código
- [x] 3.4 Endpoint: `POST /api/v1/invitation/{id}/accept` — aceptar invitación por ID
- [x] 3.5 Endpoint: `GET /api/v1/invitation` — listar invitaciones pendientes del usuario
- [x] 3.6 Endpoint: `DELETE /api/v1/invitation/{id}` — cancelar invitación (solo quien la creó)
- [x] 3.7 Notificación por email (Mail::raw, fail-safe si no hay mail configurado)

---

## Fase 4: Lógica de Predicciones — ✅ COMPLETADA

- [x] 4.1 Validar ventana de 30 min antes del inicio del partido (rechaza si <30min)
- [x] 4.2 Prevenir predicciones duplicadas (user + match + group) con query compuesta
- [x] 4.3 Auto-vincular predicciones a grupos vía GroupPredictions en misma transacción
- [x] 4.4 Validar que el match esté en status `scheduled` para crear/actualizar/borrar
- [x] 4.5 Validar que el usuario sea miembro del grupo antes de predecir
- [x] 4.6 Solo el dueño puede actualizar/borrar sus predicciones
- [x] 4.7 Agregar relación `invitations()` al modelo Groups

---

## Fase 5: Motor de Scoring — ✅ COMPLETADA

- [x] 5.1 Artisan command `predictions:score` para procesar partidos finalizados
- [x] 5.2 Reglas de scoring:
  - Score exacto: **10 puntos**
  - Resultado correcto (home_win/away_win/draw): **5 puntos**
  - Ambos equipos anotan (correcto): **3 puntos**
- [x] 5.3 Endpoint: `GET /api/v1/group/{id}/leaderboard` — ranking de miembros con puntos totales, predicciones totales y aciertos
- [x] 5.4 Guarda resultados en `PredictionResults` + actualiza `GroupPredictions.points_awarded` en transacción
- [x] 5.5 Soporte para `--match_id=XX` para procesar un partido específico
- [x] 5.6 Previene re-scoring (verifica si ya existe en PredictionResults)

---

## Fase 6: APIs Externas de Datos — ✅ COMPLETADA

- [x] 6.1 Elegir football-data.org (gratis, 12 ligas mayores, 10 req/min)
- [x] 6.2 Crear `FootballDataService` — cliente Guzzle con rate limit handling
- [x] 6.3 Artisan command `football:sync` con 3 modos:
  - `--type=competitions` — sincroniza competencias → tabla `tournaments`
  - `--type=teams` — sincroniza equipos → tabla `teams`
  - `--type=matches` — sincroniza partidos → tabla `matches`
- [x] 6.4 Migración `external_id` en tournaments, teams, matches (sync con API externa)
- [x] 6.5 Soporte `--competition=ID` para sincronizar una competencia específica
- [x] 6.6 Auto-crea `TournamentStage` basado en el campo `stage` de la API
- [x] 6.7 Token configurable via `FOOTBALL_DATA_API_TOKEN` en `.env`

---

## Fase 7: Activar Rutas Faltantes — ✅ COMPLETADA

- [x] Activar rutas de Odds, Bets, GroupBets (9 rutas CRUD completas)
- [x] Completar 16 Form Requests con validación real (authorize=true + rules)
- [x] OddsController — store/update ahora usan StoreOddsRequest/UpdateOddsRequest
- [x] BetsController — store/update ahora usan StoreBetsRequest/UpdateBetsRequest
- [x] GroupBetsController — store/update ahora usan StoreGroupBetsRequest/UpdateGroupBetsRequest
- [x] BonusesController — store usa StoreBonusesRequest, update implementada con UpdateBonusesRequest
- [x] GroupPredictionsController — store/update ahora usan sus Form Requests
- [x] PredictionResultsController — store/update ahora usan sus Form Requests
- [x] TransactionsController — store/update ahora usan sus Form Requests
- [x] Pint ejecutado: 135 issues de estilo corregidos en 191 archivos

---

## Fase 8: Tests — ✅ COMPLETADA

- [x] 8.1 Tests de autenticación — `AuthTest.php` (9 tests): register, login, logout, validación
- [x] 8.2 Tests de entidades CRUD — `TournamentTest.php` (6), `TeamTest.php` (7), `MatchTest.php` (6), `GroupTest.php` (6)
- [x] 8.3 Tests de predicciones — `PredictionTest.php` (8): crear, ventana 30min, duplicados, estado, membresía
- [x] 8.4 Tests de scoring — `ScoringTest.php` (5): exacto, resultado, equivocado, leaderboard
- [x] 8.5 Tests de invitaciones — `InvitationTest.php` (11): enviar, aceptar, cancelar, listar
- [x] 8.6 Tests de odds/bets — `OddsBetsTest.php` (11): odds, bets, groupbets CRUD
- [x] 71 tests, 162 assertions, 0 fallos

---

## Fase 9: Fuentes CONMEBOL — ✅ COMPLETADA

- [x] `TheSportsDBService` — cliente para TheSportsDB v1 API (gratis, key `123`)
- [x] `ApiFootballService` — cliente para API-Football v3 (100 req/día gratis)
- [x] Config: `services.football_data`, `services.thesportsdb`, `services.api_football`
- [x] `SyncConmebolCommand` — `php artisan conmebol:sync` (API-Football only)
- [x] Eliminado `OneFootballScraper` (roto e incompleto)
- [x] Copa Libertadores: API-Football id=13 (TheSportsDB **NO** cubre Libertadores)
- [x] Copa Sudamericana: API-Football id=11 (TheSportsDB **NO** cubre Sudamericana)
- [x] Sync verificado: 47+56 equipos, 155+157 partidos (temporada 2024)

---

## Notas Técnicas

- **Base de datos**: MySQL `sasport` via XAMPP. Tests usan SQLite `:memory:`
- **Primary keys custom**: Cada modelo usa su propio PK (`team_id`, `match_id`, etc.), no `id`
- **Filtros**: Sistema `ApiFilter` con operadores `eq`, `gt`, `lt`, `gte`, `lte`
- **Rutas**: Todo bajo prefijo `/api/v1/`
- **Auth**: Sanctum — register, login, logout implementados
- **Fuentes de datos externas**:
  - `FootballDataService` — football-data.org (europeas, 12 ligas gratis)
  - `ApiFootballService` — API-Football (CONMEBOL + ligas, 100 req/día gratis, free plan: temporadas 2022-2024)
  - `TheSportsDBService` — TheSportsDB (disponible pero NO cubre CONMEBOL)
