# Sistema Multi-Fase para Torneos

> Fecha: 2026-07-23
> Versión: 1.0
> Tests: 135 pasados, 391 assertions

---

## Resumen

Soporte para múltiples formatos de torneo:

1. **Caso 1 — Mundial 2026**: 48 equipos → 12 grupos de 4 → top 2 + mejores 3ros → Dieciseisavos → Octavos → Cuartos → Semifinal → Final
2. **Caso 2 — Eliminatorias CONMEBOL**: 10 equipos, liguilla todos contra todos (ida y vuelta)
3. **Caso 3 — Eliminación directa**: 16 → 8 → 4 → 2 (partido único)

---

## Base de Datos

### Nuevas tablas

| Tabla | Descripción |
|-------|-------------|
| `tournamentgroups` | Grupos de torneo (Grupo A, B, C...) |
| `tournamentgroupteams` | Equipos en cada grupo + estadísticas |
| `tournamentadvancementrules` | Reglas de avance entre fases |
| `tournamentadvancement` | Registro de equipos que avanzaron |

### Esquema

```sql
-- tournamentgroups
group_id INT UNSIGNED PK AUTO_INCREMENT
stage_id INT UNSIGNED FK → tournamentstage
name VARCHAR(50)
group_order INT DEFAULT 1
status VARCHAR(20) DEFAULT 'pending' -- pending, approved, rejected

-- tournamentgroupteams
id INT UNSIGNED PK AUTO_INCREMENT
group_id INT UNSIGNED FK → tournamentgroups
team_id INT UNSIGNED FK → teams
position_final INT UNSIGNED NULL
points INT DEFAULT 0
goal_diff INT DEFAULT 0
goals_for INT DEFAULT 0
goals_against INT DEFAULT 0
matches_played INT DEFAULT 0
wins INT DEFAULT 0
draws INT DEFAULT 0
losses INT DEFAULT 0

-- tournamentadvancementrules
rule_id INT UNSIGNED PK AUTO_INCREMENT
stage_id INT UNSIGNED FK → tournamentstage
next_stage_id INT UNSIGNED FK → tournamentstage
rule_type VARCHAR(50) -- top_n, best_third, manual
rule_value INT UNSIGNED NULL
description VARCHAR(255) NULL

-- tournamentadvancement
advancement_id INT UNSIGNED PK AUTO_INCREMENT
rule_id INT UNSIGNED FK → tournamentadvancementrules
team_id INT UNSIGNED FK → teams
source_group_id INT UNSIGNED NULL FK → tournamentgroups
source_position INT UNSIGNED NULL
confirmed BOOLEAN DEFAULT FALSE
confirmed_at TIMESTAMP NULL
```

---

## Endpoints

### Tournament Groups (Públicos)

```
GET    /api/v1/tournament/{id}/groups          # Listar grupos del torneo
GET    /api/v1/tournament-group/{id}           # Ver grupo con equipos
```

### Tournament Groups (Protegidos)

```
POST   /api/v1/tournament/{id}/group           # Crear grupo
PUT    /api/v1/tournament-group/{id}           # Actualizar grupo
DELETE /api/v1/tournament-group/{id}           # Eliminar grupo
POST   /api/v1/tournament-group/{id}/teams     # Asignar equipos
DELETE /api/v1/tournament-group/{id}/teams/{team_id}  # Quitar equipo
POST   /api/v1/tournament-group/{id}/approve   # Aprobar grupo
POST   /api/v1/tournament-group/{id}/reject    # Rechazar grupo
```

### Advancement Rules (Públicos)

```
GET    /api/v1/stage/{id}/advancement-rules    # Listar reglas de avance
```

### Advancement Rules (Protegidos)

```
POST   /api/v1/stage/{id}/advancement-rule     # Crear regla
PUT    /api/v1/advancement-rule/{id}           # Actualizar regla
DELETE /api/v1/advancement-rule/{id}           # Eliminar regla
```

### Advancement (Públicos)

```
GET    /api/v1/stage/{id}/advancements         # Ver equipos que avanzaron
```

### Advancement (Protegidos)

```
POST   /api/v1/stage/{id}/advancements/suggest  # Sistema sugiere quiénes avanzan
PUT    /api/v1/advancement/{id}/confirm         # Admin confirma avance
POST   /api/v1/stage/{id}/advancements/confirm-all  # Confirmar todos
```

### Standings (Públicos)

```
GET    /api/v1/stage/{id}/standings            # Tabla de posiciones (liguilla)
GET    /api/v1/stage/{id}/group/{groupId}/standings  # Posiciones de un grupo
```

### Brackets (Públicos)

```
GET    /api/v1/tournament/{id}/bracket          # Estructura completa del bracket
GET    /api/v1/tournament/{id}/bracket/{stageId}  # Bracket de una fase
```

---

## Flujo de Uso

### Caso 1: Mundial 2026

```bash
# 1. Crear torneo
POST /api/v1/tournament
{
    "name": "Copa Mundial 2026",
    "season": "2026",
    "country": "Global",
    "country_code": "GL",
    "start_date": "2026-06-01",
    "end_date": "2026-07-15",
    "actived": "1"
}

# 2. Crear stages
POST /api/v1/stage/bulk
[
    {"tournamentid": 1, "name": "Fase de Grupos", "stage_order": "1", "is_knockout": "0"},
    {"tournamentid": 1, "name": "Dieciseisavos", "stage_order": "2", "is_knockout": "1"},
    {"tournamentid": 1, "name": "Octavos", "stage_order": "3", "is_knockout": "1"},
    {"tournamentid": 1, "name": "Cuartos", "stage_order": "4", "is_knockout": "1"},
    {"tournamentid": 1, "name": "Semifinal", "stage_order": "5", "is_knockout": "1"},
    {"tournamentid": 1, "name": "Final", "stage_order": "6", "is_knockout": "1"}
]

# 3. Crear grupos para Fase de Grupos
POST /api/v1/tournament/1/group
{"stage_id": 1, "name": "Grupo A", "group_order": 1}

# 4. Asignar equipos a cada grupo
POST /api/v1/tournament-group/1/teams
{"team_ids": [1, 2, 3, 4]}

# 5. Aprobar grupo
POST /api/v1/tournament-group/1/approve

# 6. Crear regla de avance
POST /api/v1/stage/1/advancement-rule
{
    "next_stage_id": 2,
    "rule_type": "top_n",
    "rule_value": 2,
    "description": "Top 2 de cada grupo"
}

# 7. Cuando los partidos terminan, sugerir avances
POST /api/v1/stage/1/advancements/suggest

# 8. Admin confirma avances
PUT /api/v1/advancement/1/confirm
POST /api/v1/stage/1/advancements/confirm-all

# 9. Ver bracket
GET /api/v1/tournament/1/bracket
```

### Caso 2: Eliminatorias CONMEBOL

```bash
# 1. Crear torneo
POST /api/v1/tournament
{
    "name": "Eliminatorias Sudamericanas",
    "season": "2026",
    "country": "Sudamerica",
    "country_code": "SA",
    "start_date": "2023-09-07",
    "end_date": "2025-09-09",
    "actived": "1"
}

# 2. Crear stage liguilla
POST /api/v1/stage
{"tournamentid": 1, "name": "Liguilla", "stage_order": "1", "is_knockout": "0"}

# 3. Crear partidos todos contra todos (90 partidos)
POST /api/v1/match/bulk
[
    {"home_team_id": 1, "away_team_id": 2, "tournamentid": 1, "stageid": 1, "match_date": "2023-09-07", "status": "scheduled"},
    ...
]

# 4. Ver posiciones
GET /api/v1/stage/1/standings
```

### Caso 3: Eliminación Directa

```bash
# 1. Crear torneo
POST /api/v1/tournament
{
    "name": "Copa Direct Elimination",
    "season": "2026",
    "country": "Global",
    "country_code": "GL",
    "start_date": "2026-06-01",
    "end_date": "2026-06-15",
    "actived": "1"
}

# 2. Crear stages knockout
POST /api/v1/stage/bulk
[
    {"tournamentid": 1, "name": "Octavos", "stage_order": "1", "is_knockout": "1"},
    {"tournamentid": 1, "name": "Cuartos", "stage_order": "2", "is_knockout": "1"},
    {"tournamentid": 1, "name": "Semifinal", "stage_order": "3", "is_knockout": "1"},
    {"tournamentid": 1, "name": "Final", "stage_order": "4", "is_knockout": "1"}
]

# 3. Crear regla manual
POST /api/v1/stage/1/advancement-rule
{
    "next_stage_id": 2,
    "rule_type": "manual",
    "description": "Emparejamientos por sorteo"
}

# 4. Crear partidos de Octavos
POST /api/v1/match/bulk
[
    {"home_team_id": 1, "away_team_id": 16, "tournamentid": 1, "stageid": 1, "match_date": "2026-06-01", "status": "scheduled"},
    ...
]

# 5. Ver bracket
GET /api/v1/tournament/1/bracket
```

---

## Respuesta de Bracket

```json
{
    "tournament_id": 1,
    "tournament_name": "Copa Mundial 2026",
    "bracket": [
        {
            "stage_id": 1,
            "name": "Fase de Grupos",
            "is_knockout": false,
            "stage_order": 1,
            "groups": [
                {
                    "group_id": 1,
                    "name": "Grupo A",
                    "status": "approved",
                    "teams": [
                        {"team_id": 1, "team_name": "Brasil", "points": 9, "goal_diff": 5}
                    ],
                    "standings": [
                        {"position": 1, "team_id": 1, "team_name": "Brasil", "points": 9, "goal_diff": 5, "goals_for": 7, "goals_against": 2}
                    ],
                    "matches": [
                        {
                            "match_id": 1,
                            "home": {"team_id": 1, "name": "Brasil"},
                            "away": {"team_id": 2, "name": "Argentina"},
                            "home_score": 2,
                            "away_score": 1,
                            "status": "finished"
                        }
                    ]
                }
            ]
        },
        {
            "stage_id": 2,
            "name": "Octavos",
            "is_knockout": true,
            "stage_order": 2,
            "matches": [...],
            "advancements": [
                {"advancement_id": 1, "team_id": 1, "team_name": "Brasil", "confirmed": true}
            ]
        }
    ]
}
```

---

## Archivos Creados

### Migraciones
- `database/migrations/2026_07_23_000001_create_tournament_groups_table.php`
- `database/migrations/2026_07_23_000002_create_tournament_group_teams_table.php`
- `database/migrations/2026_07_23_000003_create_tournament_advancement_rules_table.php`
- `database/migrations/2026_07_23_000004_create_tournament_advancement_table.php`

### Models
- `app/Models/TournamentGroup.php`
- `app/Models/TournamentGroupTeam.php`
- `app/Models/TournamentAdvancementRule.php`
- `app/Models/TournamentAdvancement.php`

### Controllers
- `app/Http/Controllers/TournamentGroupController.php`
- `app/Http/Controllers/AdvancementController.php`
- `app/Http/Controllers/BracketController.php`

### Services
- `app/Services/AdvancementService.php`

### Form Requests
- `app/Http/Requests/StoreTournamentGroupRequest.php`
- `app/Http/Requests/AddTeamsTournamentGroupRequest.php`
- `app/Http/Requests/StoreAdvancementRuleRequest.php`

### Resources
- `app/Http/Resources/TournamentGroupResource.php`
- `app/Http/Resources/TournamentGroupTeamResource.php`

### Tests
- `tests/Feature/TournamentGroupTest.php` (10 tests)
- `tests/Feature/AdvancementTest.php` (10 tests)
- `tests/Feature/BracketTest.php` (6 tests)
