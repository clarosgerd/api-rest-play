# SoccerApi — Documentación Técnica

> API REST para predicciones deportivas con CRUD completo, autenticación, scoring y sincronización de datos externos.

---

## 1. Stack Tecnológico

| Componente | Versión | Detalle |
|------------|---------|---------|
| PHP | 8.2+ | Requerido por Laravel 12 |
| Laravel | 12.x | Framework principal |
| MySQL | 8.x | Base de datos `sasport` (XAMPP) |
| Laravel Sanctum | 4.x | Autenticación por tokens |
| Guzzle | 7.9 | HTTP client para APIs externas |
| PHPUnit | 11.5 | Testing framework |
| Laravel Pint | 1.13 | Code style (defaults) |

---

## 2. Arquitectura General

```
laravel-crud-api/
├── app/
│   ├── Console/Commands/     # 10 comandos artisan
│   ├── Filters/              # 15 filtros (sistema ApiFilter)
│   ├── Http/
│   │   ├── Controllers/      # 21 controladores
│   │   ├── Middleware/        # 5 middlewares (auth, subscription)
│   │   ├── Requests/         # 31 form requests (validación)
│   │   └── Resources/        # 20 API resources (transformación)
│   ├── Models/               # 19 modelos Eloquent
│   ├── Notifications/        # 7 notificaciones
│   ├── Policies/             # 11 policies (stubs, todo false)
│   ├── Providers/            # AppServiceProvider (singletons)
│   └── Services/             # 3 servicios de datos externos
├── database/
│   ├── factories/            # 15 factories
│   ├── migrations/           # 25 migraciones
│   └── seeders/              # 15 seeders
├── routes/
│   └── api.php               # 78 rutas API
├── storage/app/examples/     # JSON de ejemplo para importación
├── tests/Feature/            # 12 archivos, 109 tests, 270 assertions
├── AGENTS.md                 # Guía para agentes AI
├── CAMBIOS.md                # Guía de cambios scoring/pool/promos
├── PLAN.md                   # Roadmap de desarrollo
└── TECHNICAL.md              # Este archivo
```

---

## 3. Base de Datos

### 3.1 Entidades Principales (21 tablas)

| Tabla | PK | Modelo | Descripción |
|-------|-----|--------|-------------|
| `users` | `id` | User | Usuarios (Laravel default) |
| `teams` | `team_id` | Team | Equipos de fútbol |
| `tournaments` | `tournament_id` | Tournament | Torneos/ligas |
| `tournamentstage` | `stage_id` | TournamentStage | Fases del torneo |
| `matches` | `match_id` | Matches | Partidos |
| `groups` | `group_id` | Groups | Grupos de predicciones |
| `groupmembers` | `member_id` | GroupMembers | Miembros del grupo |
| `grouppredictions` | `gp_id` | GroupPredictions | Predicciones por grupo |
| `groupbets` | `gb_id` | GroupBets | Apuestas por grupo |
| `predictions` | `prediction_id` | Predictions | Predicciones de usuarios |
| `predictionresults` | `result_id` | PredictionResults | Resultados de scoring |
| `odds` | `odd_id` | Odds | Cuotas de partidos |
| `bets` | `bet_id` | Bets | Apuestas de usuarios |
| `bonuses` | `bonus_id` | Bonuses | Reglas de bonificación |
| `transactions` | `transaction_id` | Transactions | Ledger de transacciones |
| `invitations` | `invitation_id` | Invitation | Invitaciones a grupos |
| `tasks` | `id` | Task | Tareas (Laravel default) |

### 3.2 Relaciones Clave

```
Tournament 1──N TournamentStage
Tournament 1──N Matches
TournamentStage 1──N Matches
Team 1──N Matches (home_team_id)
Team 1──N Matches (away_team_id)
Groups 1──N GroupMembers
Groups 1──N GroupPredictions
Groups 1──N GroupBets
User 1──N Predictions
User 1──N Bets
Matches 1──N Predictions
Matches 1──N Odds
```

### 3.3 Convenciones de Base de Datos

- **Primary keys custom**: Nunca se usa `id`, cada modelo tiene su propio PK (`team_id`, `match_id`, etc.)
- **`$timestamps = false`**: Todos los modelos excepto User
- **`external_id`**: Columna en `teams`, `matches`, `tournaments` para datos de APIs externas
- **Motor InnoDB**: Todas las migraciones lo especifican

---

## 4. Autenticación (Sanctum)

### 4.1 Flujo

```
1. POST /api/v1/auth/register  → Crea usuario, retorna token
2. POST /api/v1/auth/login     → Valida credenciales, retorna token
3. POST /api/v1/auth/logout    → Revoca token (requiere Bearer)
```

### 4.2 Uso en Requests

```
Authorization: Bearer {token}
```

### 4.3 Reglas de Acceso

| Acción | Requiere Auth |
|--------|---------------|
| GET (listar/detalle) | No |
| POST (crear) | Sí |
| PUT (actualizar) | Sí |
| DELETE (eliminar) | Sí |
| Register/Login | No |
| Logout | Sí |

---

## 5. API Routes (78 rutas)

### 5.1 Todas las Rutas bajo `/api/v1/`

#### Públicas (33 rutas)

| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `auth/register` | Registro |
| POST | `auth/login` | Login |
| GET | `tournament` | Listar torneos |
| GET | `tournament/{id}` | Detalle torneo |
| GET | `team` | Listar equipos |
| GET | `team/{id}` | Detalle equipo |
| GET | `match` | Listar partidos |
| GET | `match/{id}` | Detalle partido |
| GET | `stage` | Listar fases |
| GET | `stage/{id}` | Detalle fase |
| GET | `group` | Listar grupos |
| GET | `group/{id}` | Detalle grupo |
| GET | `member` | Listar miembros |
| GET | `member/{id}` | Detalle miembro |
| GET | `prediction` | Listar predicciones |
| GET | `prediction/{id}` | Detalle predicción |
| GET | `groupprediction` | Listar predicciones de grupo |
| GET | `groupprediction/{id}` | Detalle predicción de grupo |
| GET | `predictionresults` | Listar resultados |
| GET | `predictionresults/{id}` | Detalle resultado |
| GET | `transactions` | Listar transacciones |
| GET | `transactions/{id}` | Detalle transacción |
| GET | `bonuses` | Listar bonuses |
| GET | `bonuses/{id}` | Detalle bonus |
| GET | `odds` | Listar cuotas |
| GET | `odds/{id}` | Detalle cuota |
| GET | `bets` | Listar apuestas |
| GET | `bets/{id}` | Detalle apuesta |
| GET | `groupbets` | Listar apuestas de grupo |
| GET | `groupbets/{id}` | Detalle apuesta de grupo |
| GET | `group/{id}/leaderboard` | Leaderboard del grupo |

#### Protegidas (45 rutas)

| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `auth/logout` | Logout |
| GET | `user` | Perfil |
| PUT | `user` | Actualizar perfil |
| PUT | `user/password` | Cambiar contraseña |
| POST | `tournament` | Crear torneo |
| PUT | `tournament/{id}` | Actualizar torneo |
| DELETE | `tournament/{id}` | Eliminar torneo |
| POST | `stage` | Crear fase |
| POST | `stage/bulk` | Crear fases (bulk) |
| PUT | `stage/{id}` | Actualizar fase |
| DELETE | `stage/{id}` | Eliminar fase |
| POST | `team` | Crear equipo |
| POST | `team/bulk` | Crear equipos (bulk) |
| PUT | `team/{id}` | Actualizar equipo |
| DELETE | `team/{id}` | Eliminar equipo |
| POST | `group` | Crear grupo |
| POST | `group/first` | Crear primer grupo |
| PUT | `group/{id}` | Actualizar grupo |
| DELETE | `group/{id}` | Eliminar grupo |
| POST | `member` | Agregar miembro |
| PUT | `member/{id}` | Actualizar miembro |
| DELETE | `member/{id}` | Eliminar miembro |
| POST | `match` | Crear partido |
| POST | `match/bulk` | Crear partidos (bulk) |
| PUT | `match/{id}` | Actualizar partido |
| DELETE | `match/{id}` | Eliminar partido |
| POST | `prediction` | Crear predicción |
| PUT | `prediction/{id}` | Actualizar predicción |
| DELETE | `prediction/{id}` | Eliminar predicción |
| POST | `groupprediction` | Crear predicción de grupo |
| PUT | `groupprediction/{id}` | Actualizar predicción de grupo |
| DELETE | `groupprediction/{id}` | Eliminar predicción de grupo |
| POST | `predictionresults` | Crear resultado |
| PUT | `predictionresults/{id}` | Actualizar resultado |
| DELETE | `predictionresults/{id}` | Eliminar resultado |
| POST | `transactions` | Crear transacción |
| PUT | `transactions/{id}` | Actualizar transacción |
| DELETE | `transactions/{id}` | Eliminar transacción |
| POST | `bonuses` | Crear bonus |
| PUT | `bonuses/{id}` | Actualizar bonus |
| DELETE | `bonuses/{id}` | Eliminar bonus |
| POST | `odds` | Crear cuota |
| PUT | `odds/{id}` | Actualizar cuota |
| DELETE | `odds/{id}` | Eliminar cuota |
| POST | `bets` | Crear apuesta |
| PUT | `bets/{id}` | Actualizar apuesta |
| DELETE | `bets/{id}` | Eliminar apuesta |
| POST | `groupbets` | Crear apuesta de grupo |
| PUT | `groupbets/{id}` | Actualizar apuesta de grupo |
| DELETE | `groupbets/{id}` | Eliminar apuesta de grupo |
| GET | `invitation` | Listar invitaciones |
| POST | `invitation` | Enviar invitación |
| POST | `invitation/accept-code` | Aceptar por código |
| POST | `invitation/{id}/accept` | Aceptar invitación |
| DELETE | `invitation/{id}` | Cancelar invitación |

---

## 6. Sistema de Filtros (ApiFilter)

### 6.1 Uso

```
GET /api/v1/team?name[eq]=Barcelona&country[eq]=Spain
GET /api/v1/match?status[eq]=finished&home_score[gte]=2
GET /api/v1/tournament?season[eq]=2024&active[eq]=1
```

### 6.2 Operadores Disponibles

| Operador | SQL | Ejemplo |
|----------|-----|---------|
| `eq` | `= ?` | `name[eq]=Bolívar` |
| `gt` | `> ?` | `home_score[gt]=2` |
| `lt` | `< ?` | `home_score[lt]=3` |
| `gte` | `>= ?` | `home_score[gte]=2` |
| `lte` | `<= ?` | `home_score[lte]=1` |

### 6.3 Implementación

Cada modelo tiene un filtro asociado en `app/Filters/`:

- `ApiFilter.php` — Clase base
- `TournamentFilter.php` — Filtros: `name`, `season`, `country`, `active`
- `TeamFilter.php` — Filtros: `name`, `country`, `league`, `active`
- `MatchesFilter.php` — Filtros: `status`, `tournament_id`, `stage_id`, `match_date`
- `TournamentStagesFilter.php` — Filtros: `tournament_id`, `name`, `is_knockout`
- (15 filtros en total, uno por entidad)

Los filtros devuelven triples `[column, operator, value]` que se pasan a `->where()`.

---

## 7. Bulk Operations

### 7.1 Rutas Bulk (API)

| Ruta | Entidad |
|------|---------|
| `POST /api/v1/stage/bulk` | Crear múltiples fases |
| `POST /api/v1/team/bulk` | Crear múltiples equipos |
| `POST /api/v1/match/bulk` | Crear múltiples partidos |

### 7.2 Form Requests para Bulk

- `BulkStoreTournamentStageRequest.php` — Array de `name`, `stage_order`, `is_knockout`
- `BulkStoreTeamRequest.php` — Array de `name`, `country`, `logo_url`, etc.
- `BulkStoreMatchesRequest.php` — Array de `home_team_id`, `away_team_id`, etc.

### 7.3 Importación JSON (CLI)

```bash
php artisan tournament:import storage/app/examples/bolivia_liga.json
```

**Estructura del JSON:**

```json
{
    "tournament": {
        "name": "Liga Boliviana",
        "season": "2024",
        "country": "Bolivia",
        "country_code": "BO",
        "logo_url": null,
        "start_date": "2024-02-01",
        "end_date": "2024-12-31"
    },
    "stages": [
        { "name": "Apertura", "order": 1, "is_knockout": false },
        { "name": "Clausura", "order": 2, "is_knockout": false },
        { "name": "Semifinal", "order": 3, "is_knockout": true },
        { "name": "Final", "order": 4, "is_knockout": true }
    ],
    "teams": [
        { "name": "Bolívar", "country": "Bolivia", "logo_url": "https://..." },
        { "name": "The Strongest", "country": "Bolivia", "logo_url": null }
    ],
    "matches": [
        {
            "home": "Bolívar",
            "away": "The Strongest",
            "stage": "Apertura",
            "date": "2024-02-10 20:00:00",
            "status": "finished",
            "home_score": 2,
            "away_score": 1,
            "external_id": 12345
        }
    ]
}
```

**Características:**
- Idempotente: re-ejecutar actualiza, no duplica
- Auto-detecta teams por nombre
- Referencia stages por string
- Todos los campos opcionales excepto `name` (tournament) y `home`/`away` (matches)

---

## 8. Servicios de Datos Externos

### 8.1 FootballDataService (`football-data.org` v4)

| Campo | Valor |
|-------|-------|
| URL | `https://api.football-data.org/v4` |
| Config | `services.football_data.token` |
| Env | `FOOTBALL_DATA_API_TOKEN` |
| Gratis | 12 competiciones |
| Usado para | Ligas europeas (Premier League, La Liga, Bundesliga, etc.) |

**Competiciones disponibles:** Premier League (2021), La Liga (2014), Bundesliga (78), Serie A (135), Ligue 1 (61), Eredivisie (88), Primeira Liga (94), Championship (15), Championship (16), Liga MX (36), etc.

### 8.2 ApiFootballService (`api-sports.io` v3)

| Campo | Valor |
|-------|-------|
| URL | `https://v3.football.api-sports.io` |
| Config | `services.api_football.key` |
| Env | `API_FOOTBALL_KEY` |
| Gratis | 100 req/día |
| Usado para | Copa Libertadores, Copa Sudamericana, ligas sudamericanas |
| **Limitación** | Free plan: solo temporadas 2022-2024 |

**Headers:** `x-apisports-key: {key}`

**Endpoints:**
- `/leagues` — Listar ligas
- `/teams?league={id}&season={year}` — Equipos de una liga
- `/fixtures?league={id}&season={year}` — Partidos de una liga
- `/fixtures?id={id}` — Detalle de partido
- `/standings?league={id}&season={year}` — Tabla de posiciones

### 8.3 TheSportsDBService (`thesportsdb.com` v1)

| Campo | Valor |
|-------|-------|
| URL | `https://www.thesportsdb.com/api/v1/json` |
| Config | `services.thesportsdb.key` |
| Env | `THESPORTSDB_API_KEY` |
| Gratis | Key `123` (free tier) |
| **Nota** | NO cubre Copa Libertadores ni Sudamericana |

**Disponible pero no usado para CONMEBOL.**

### 8.4 Comandos de Sincronización

```bash
# Ligas europeas (football-data.org)
php artisan football:sync --type=competitions
php artisan football:sync --type=teams --competition=2021
php artisan football:sync --type=matches --competition=2021

# CONMEBOL (API-Football)
php artisan conmebol:sync --season=2024
php artisan conmebol:sync --competition=libertadores
php artisan conmebol:sync --competition=sudamericana

# Cualquier liga (API-Football)
php artisan league:sync --league-id=344 --season=2024  # Bolivia
php artisan league:sync  # Lista ligas conocidas
```

**Ligas conocidas predefinidas:**

| Key | Liga | ID API-Football |
|-----|------|-----------------|
| `bolivia` | Bolivian Primera División | 344 |
| `argentina` | Argentine Primera División | 128 |
| `brazil` | Brasileirão | 71 |
| `chile` | Chilean Primera División | 266 |
| `colombia` | Colombian Liga | 239 |
| `ecuador` | Ecuadorian Serie A | 242 |
| `peru` | Peruvian Primera División | 282 |
| `uruguay` | Uruguayan Primera División | 269 |
| `paraguay` | Paraguayan Primera División | 254 |
| `venezuela` | Venezuelan Primera División | 267 |
| `mexico` | Liga MX | 36 |
| `usa` | MLS | 253 |
| `spain` | La Liga | 140 |
| `england` | Premier League | 39 |
| `germany` | Bundesliga | 78 |
| `italy` | Serie A | 135 |
| `france` | Ligue 1 | 61 |
| `portugal` | Primeira Liga | 94 |
| `netherlands` | Eredivisie | 88 |

---

## 9. Scoring System

### 9.1 Reglas de Puntaje

| Predicción | Puntos |
|------------|--------|
| Score exacto | 10 |
| Resultado correcto (ganador/empate) | 5 |
| Ambos equipos anotan | 3 |
| Predicción incorrecta | 0 |

### 9.2 Comando de Scoring

```bash
php artisan predictions:score
```

---

## 10. Sistema de Invitaciones

### 10.1 Flujo

```
1. POST /api/v1/invitation          → Crea invitación (retorna código)
2. GET /api/v1/invitation           → Lista invitaciones pendientes
3. POST /api/v1/invitation/accept-code  → Aceptar por código (cualquier usuario)
4. POST /api/v1/invitation/{id}/accept  → Aceptar por ID (solo destinatario)
5. DELETE /api/v1/invitation/{id}   → Cancelar invitación (solo creador)
```

---

## 11. Sistema de Notificaciones (Email)

### 11.1 Notificaciones Implementadas

| Notificación | Trigger | Contenido |
|--------------|---------|-----------|
| `InvitationNotification` | `POST /invitation` | Email con código de invitación al grupo |
| `PredictionReminderNotification` | `predictions:remind` | Recordatorio de hacer predicción antes del partido |
| `MatchPointsNotification` | `predictions:score` | Puntos ganados después de cada partido |
| `TournamentSummaryNotification` | `tournament:summary` | Resumen total al finalizar torneo/fase |

### 11.2 Ubicación

```
app/Notifications/
├── InvitationNotification.php
├── PredictionReminderNotification.php
├── MatchPointsNotification.php
└── TournamentSummaryNotification.php
```

### 11.3 Configuración SMTP (Gmail)

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="your-email@gmail.com"
MAIL_FROM_NAME="${APP_NAME}"
```

**Para generar App Password:** Google Account > Security > 2-Step Verification > App Passwords

### 11.4 Comandos de Notificaciones

```bash
# Enviar recordatorios de predicciones (partidos en próximas 24h)
php artisan predictions:remind
php artisan predictions:remind --hours=48

# Enviar resumen de torneo a todos los miembros
php artisan tournament:summary {tournament_id}
php artisan tournament:summary {tournament_id} --group_id=1
```

### 11.5 Flujo de Notificaciones

```
INVITACIÓN:
  POST /invitation → InvitationNotification → Email al destinatario

RECORDATORIO:
  predictions:remind → PredictionReminderNotification → Email a miembros sin predicción

PUNTOS POR PARTIDO:
  predictions:score → MatchPointsNotification → Email con puntos ganados

RESUMEN DE TORNEO:
  tournament:summary → TournamentSummaryNotification → Email con posición y estadísticas
```

---

## 12. Tests

### 12.1 Suite Actual

| Archivo | Tests | Cobertura |
|---------|-------|-----------|
| `AuthTest.php` | 9 | Register, login, logout, validación |
| `TournamentTest.php` | 6 | CRUD completo |
| `TeamTest.php` | 7 | CRUD + filtro por nombre |
| `MatchTest.php` | 6 | CRUD completo |
| `GroupTest.php` | 6 | CRUD completo |
| `InvitationTest.php` | 11 | Send, accept, cancel, list |
| `PredictionTest.php` | 8 | CRUD + reglas de negocio |
| `ScoringTest.php` | 5 | Exacto, resultado, incorrecto, leaderboard |
| `OddsBetsTest.php` | 11 | Odds, bets, groupbets |
| `ExampleTest.php` | 1 | Unit test base |
| **Total** | **71** | **162 assertions, 0 failures** |

### 12.2 Ejecución

```bash
php artisan test                    # Todos los tests
php artisan test tests/Feature/AuthTest.php  # Archivo específico
composer test                       # Clears config cache + test
```

### 11.3 Configuración

- Tests usan SQLite `:memory:` (configurado en `phpunit.xml`)
- No dependen de servicios externos
- No dependen de la base de datos MySQL

---

## 13. Comandos Artisan

| Comando | Descripción |
|---------|-------------|
| `league:sync` | Sincificar cualquier liga desde API-Football |
| `conmebol:sync` | Sincificar Copa Libertadores + Sudamericana |
| `football:sync` | Sincificar ligas europeas desde football-data.org |
| `tournament:import` | Importar torneo completo desde JSON |
| `tournament:summary` | Enviar resumen de torneo por email a miembros |
| `predictions:score` | Calcular puntos de predicciones |
| `predictions:remind` | Enviar recordatorios de predicciones por email |
| `scrape:onefootball` | Scraping de OneFootball (roto, no usar) |

---

## 14. Variables de Entorno

```env
# Base de datos
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sasport
DB_USERNAME=root
DB_PASSWORD=

# Mail (Gmail SMTP)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="your-email@gmail.com"
MAIL_FROM_NAME="${APP_NAME}"

# APIs externas
FOOTBALL_DATA_API_TOKEN={token}
THESPORTSDB_API_KEY=123
API_FOOTBALL_KEY={key}

# App
APP_NAME=SoccerApi
APP_ENV=local
APP_DEBUG=true
```

---

## 15. Endpoints de Ejemplo (cURL)

### 15.1 Registrar Usuario

```bash
curl -X POST http://localhost/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Juan","email":"juan@test.com","password":"12345678"}'
```

### 15.2 Listar Equipos con Filtro

```bash
curl "http://localhost/api/v1/team?name[eq]=Bol%C3%ADvar&country[eq]=Bolivia"
```

### 15.3 Crear Torneo (con auth)

```bash
curl -X POST http://localhost/api/v1/tournament \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"name":"Liga Test","season":"2024","country":"Bolivia","country_code":"BO","active":true}'
```

### 15.4 Bulk Crear Equipos

```bash
curl -X POST http://localhost/api/v1/team/bulk \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"teams":[{"name":"Team A","country":"Bolivia"},{"name":"Team B","country":"Bolivia"}]}'
```

### 15.5 Bulk Crear Partidos

```bash
curl -X POST http://localhost/api/v1/match/bulk \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"matches":[{"home_team_id":1,"away_team_id":2,"tournament_id":1,"stage_id":1,"status":"scheduled"}]}'
```

---

## 16. Flujo Completo desde Frontend

### Crear un Torneo con todo configurado:

```
1.  POST /api/v1/auth/register          → Obtener token
2.  POST /api/v1/tournament             → Crear torneo
3.  POST /api/v1/stage/bulk             → Crear fases (Apertura, Clausura, Final...)
4.  POST /api/v1/team/bulk              → Crear equipos con logos
5.  POST /api/v1/match/bulk             → Crear partidos
6.  GET  /api/v1/tournament             → Listar torneos
7.  GET  /api/v1/stage?tournament_id=1  → Ver fases
8.  GET  /api/v1/team?league=Liga+Test  → Ver equipos
9.  GET  /api/v1/match?tournament_id=1  → Ver partidos
```

### Flujo de Predicciones:

```
1.  POST /api/v1/group                  → Crear grupo
2.  POST /api/v1/invitation             → Invitar miembros
3.  POST /api/v1/invitation/accept-code → Miembro acepta
4.  POST /api/v1/prediction             → Crear predicción
5.  POST /api/v1/groupprediction        → Asociar predicción a grupo
6.  POST /api/v1/predictionresults      → Calcular scoring
7.  GET  /api/v1/group/1/leaderboard    → Ver leaderboard
```

---

## 17. Sistema Freemium y Suscripciones

### Modelo de Planes

| Plan | Precio Mensual | Precio Anual | Límites |
|------|----------------|--------------|---------|
| **Free** | $0 | $0 | 2 torneos, 10 miembros/grupo, 5 predicciones/día |
| **Pro** | $4.99 | $39.99 | 5 torneos, 50 miembros/grupo, 20 predicciones/día, ligas CONMEBOL |
| **Premium** | $9.99 | $79.99 | Ilimitado todo, todas las ligas (World Cup, Champions, etc.) |

- **Trial gratuito**: 7 días para Pro/Premium (requiere tarjeta)
- **Upgrade**: inmediato con cargo prorrateado
- **Downgrade**: al final del período, sin reembolso
- **Moneda**: USD

### Base de Datos

**Tabla `users`** — Nuevos campos:
- `subscription_tier` (enum: free/pro/premium, default: free)
- `subscription_expires_at` (timestamp nullable)
- `stripe_customer_id` (string nullable)
- `stripe_trial_ends_at` (timestamp nullable)

**Tabla `subscriptions`** — Historial de suscripciones:
- `subscription_id` (PK), `user_id` (FK), `stripe_subscription_id`, `stripe_customer_id`
- `plan`, `status` (trialing/active/past_due/cancelled/expired)
- `monthly_price`, `annual_price`, `is_annual`
- `current_period_start`, `current_period_end`, `trial_ends_at`
- `cancelled_at`, `created_at`

**Tabla `payments`** — Historial de pagos:
- `payment_id` (PK), `user_id` (FK), `subscription_id` (FK)
- `stripe_payment_id`, `stripe_invoice_id`
- `amount`, `currency`, `status`, `description`, `paid_at`

### Middlewares

```php
// Verificar tier mínimo (en rutas)
'subscription.tier:pro'       // Requiere Pro o superior
'subscription.tier:premium'   // Requiere Premium

// Verificar límites diarios
'subscription.limits.predictions'  // 5/20/-1 por día según tier
'subscription.limits.groups:tournaments'  // 2/5/-1 según tier
'subscription.limits.groups:members'      // 10/50/-1 según tier
```

### Rutas API (Stripe)

```
GET    /api/v1/user/subscription           → Estado actual
GET    /api/v1/user/subscription/plans     → Lista de planes
POST   /api/v1/user/subscription/checkout  → Crear sesión Stripe Checkout
POST   /api/v1/user/subscription/cancel    → Cancelar al final del período
POST   /api/v1/webhook/stripe              → Webhook Stripe (sin auth)
```

### Webhook Events

- `checkout.session.completed` → Crea suscripción, actualiza user
- `invoice.payment_succeeded` → Confirma pago, actualiza período
- `invoice.payment_failed` → Marca past_due, notifica
- `customer.subscription.deleted` → Downgrade a free
- `customer.subscription.updated` → Sincroniza estado

### Comandos Artisan

```bash
php artisan subscription:check-expired       # Downgrade expiradas
php artisan subscription:remind-expiring     # Recordatorios 3 días antes
```

### Notificaciones

- `SubscriptionExpiringNotification` — 3 días antes de expirar
- `SubscriptionExpiredNotification` — Al expirar/cancelar
- `PaymentFailedNotification` — Fallo de pago

---

## 18. Sistema de Scoring y Pool de Premios

### Scoring Per-Group (Configurable en BD)

Todos los valores se almacenan **por grupo** en la tabla `groups`:

| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `exact_score_points` | int | 10 | Home y away coinciden exactamente |
| `match_result_points` | int | 5 | Resultado (home_win/away_win/draw) coincide |
| `correct_goal_points` | int | 3 | Gol acertado (home o away) |
| `unique_exact_points` | int | 15 | Solo UN usuario en el grupo acertó exacto |
| `multiplier_dieciseisavos` | decimal(3,1) | 1.5 | Multiplicador dieciseisavos |
| `multiplier_octavos` | decimal(3,1) | 2.0 | Multiplicador octavos |
| `multiplier_cuartos` | decimal(3,1) | 3.0 | Multiplicador cuartos |
| `multiplier_semifinal` | decimal(3,1) | 5.0 | Multiplicador semifinal |
| `multiplier_final` | decimal(3,1) | 10.0 | Multiplicador final |
| `bet_amount` | decimal | 0 | Valor de apuesta |
| `prize_1st_pct` | int | 60 | % premio 1er lugar |
| `prize_2nd_pct` | int | 30 | % premio 2do lugar |
| `prize_3rd_pct` | int | 10 | % premio 3er lugar |
| `pool_active` | bool | false | Pool activo |

**Fórmula:**
```
Subtotal = exact_score + match_result + correct_goal_home + correct_goal_away + unique_exact
Total = round(Subtotal × stage_multiplier)
```

### Multiplicadores por Etapa (Knockout)

Cada grupo configura sus propios multiplicadores. Se aplican solo en etapas knockout (`is_knockout=true`):

| Etapa | Default | Ejemplo (21pts base) |
|-------|---------|----------------------|
| Regular Season | x1.0 | 21 |
| Dieciseisavos | x1.5 | 32 |
| Octavos | x2.0 | 42 |
| Cuartos | x3.0 | 63 |
| Semifinal | x5.0 | 105 |
| Final | x10.0 | 210 |

### Pool de Premios

**Flujo:**
1. Crear grupo con `pool_active=true`, `bet_amount=X`, `prize_*_pct=Y`
2. Miembros contribuyen: `pool:contribute --group_id=X --user_id=Y`
3. Distribuir premios: `pool:distribute --group_id=X`
4. Top 3 ganan según porcentajes configurables por grupo

**Tabla `grouppool`:** group_id, user_id, match_id, amount_applied, points_earned, prize_won, status

### Leaderboard extendido

Nuevas columnas:
- `exact_scores` — Count de predicciones con marcador exacto
- `unique_predictions` — Count de bonos de predicción única
- `pool_winnings` — Total ganado en pools

---

## 19. Torneos Promocionales (Marketing)

### Concepto

Torneo normalmente de Pro/Premium que se ofrece GRATIS por tiempo limitado a usuarios free. Crea FOMO y convierte usuarios en suscriptores.

### Campos en `tournaments`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `is_promotional` | boolean | Torneo promocional activo |
| `promotional_tier_required` | int | Tier normal (0=free,1=pro,2=premium) |
| `promotional_ends_at` | timestamp | Cuándo expira la promo |

### Comportamiento

- Usuarios free obtienen acceso COMPLETO (crear grupos, predicciones, pools)
- Los middlewares `CheckGroupLimits` y `CheckSubscriptionTier` hacen bypass automático
- Después de expirar, vuelve a requerir tier normal

### Comando

```bash
php artisan promotions:expire  # Expira torneos donde promotional_ends_at < now()
```

Programar diariamente:
```php
// routes/console.php
$schedule->command('promotions:expire')->daily();
```

---

## 20. Gotchas

- **PUT requiere todos los campos**: No hay PATCH. Para actualizar, enviar todos los campos.
- **`password_hash`**: Campo de contraseña en BD, cast `hashed`. Input field: `password`.
- **`$timestamps = false`**: Todos los modelos excepto User.
- **Primary keys custom**: Nunca usar `id` en queries, usar `team_id`, `match_id`, etc.
- **API-Football free plan**: Solo temporadas 2022-2024.
- **TheSportsDB**: NO tiene Copa Libertadores ni Sudamericana.
- **Node.js requerido**: Para `composer dev` (npx concurrently).
- **SQLite para tests**: Tests no dependen de MySQL ni APIs externas.

---

*Documento generado el 2026-07-22*
