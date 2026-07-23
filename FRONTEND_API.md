# Informe Técnico — Equipo Frontend

> SoccerApi v1.0 | Julio 2026
> Base URL: `https://api.soccerapi.com/api/v1`

---

## 1. Autenticación

### Registrar usuario
```http
POST /auth/register
Content-Type: application/json

{
    "first_name": "Juan",
    "last_name": "Pérez",
    "email": "juan@email.com",
    "password": "secret123"
}
```

### Iniciar sesión
```http
POST /auth/login
Content-Type: application/json

{
    "email": "juan@email.com",
    "password": "secret123"
}
```
**Response:**
```json
{
    "user": { "user_id": 1, "first_name": "Juan", ... },
    "plainTextToken": "1|abc123..."
}
```

### Cerrar sesión
```http
POST /auth/logout
Authorization: Bearer {token}
```

### Headers requeridos para rutas protegidas
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

---

## 2. Estructura de Respuestas

### Paginación (listados)
```json
{
    "data": [...],
    "links": { "first": "...", "last": "...", "next": "...", "prev": null },
    "meta": { "current_page": 1, "last_page": 5, "per_page": 15, "total": 75 }
}
```

### Respuesta individual
```json
{
    "data": { "tournament_id": 1, "name": "Liga Pro", ... }
}
```

### Errores
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": ["The email field is required."]
    }
}
```

---

## 3. Endpoints por Entidad

### 3.1 Torneos

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/tournament` | No | Listar torneos |
| GET | `/tournament/{id}` | No | Ver torneo |
| POST | `/tournament` | Sí | Crear torneo |
| PUT | `/tournament/{id}` | Sí | Actualizar torneo |
| DELETE | `/tournament/{id}` | Sí | Eliminar torneo |

**POST /tournament:**
```json
{
    "name": "Copa Mundial 2026",
    "season": "2026",
    "country": "Global",
    "country_code": "GL",
    "start_date": "2026-06-01",
    "end_date": "2026-07-15",
    "actived": "1",
    "is_promotional": "0",
    "promotional_tier_required": null,
    "promotional_ends_at": null
}
```

### 3.2 Stages (Fases del Torneo)

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/stage` | No | Listar stages |
| GET | `/stage/{id}` | No | Ver stage |
| POST | `/stage` | Sí | Crear stage |
| POST | `/stage/bulk` | Sí | Crear múltiples stages |
| PUT | `/stage/{id}` | Sí | Actualizar stage |
| DELETE | `/stage/{id}` | Sí | Eliminar stage |

**POST /stage/bulk:**
```json
[
    {"tournamentid": 1, "name": "Fase de Grupos", "stage_order": "1", "is_knockout": "0"},
    {"tournamentid": 1, "name": "Octavos", "stage_order": "2", "is_knockout": "1"}
]
```

### 3.3 Equipos

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/team` | No | Listar equipos |
| GET | `/team/{id}` | No | Ver equipo |
| POST | `/team` | Sí | Crear equipo |
| POST | `/team/bulk` | Sí | Crear múltiples equipos |
| PUT | `/team/{id}` | Sí | Actualizar equipo |
| DELETE | `/team/{id}` | Sí | Eliminar equipo |

**GET /team?name[eq]=Barcelona&country[eq]=Spain** — Filtros disponibles.

### 3.4 Partidos

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/match` | No | Listar partidos |
| GET | `/match/{id}?includeHome=1&includeAway=1` | No | Ver partido con equipos |
| POST | `/match` | Sí | Crear partido |
| POST | `/match/bulk` | Sí | Crear múltiples partidos |
| PUT | `/match/{id}` | Sí | Actualizar partido |
| DELETE | `/match/{id}` | Sí | Eliminar partido |

**POST /match:**
```json
{
    "home_team_id": 1,
    "away_team_id": 2,
    "tournament_id": 1,
    "stage_id": 1,
    "match_date": "2026-06-01T20:00:00",
    "status": "scheduled"
}
```

**Status posibles:** `scheduled`, `live`, `finished`

### 3.5 Grupos (Predicciones)

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/group` | No | Listar grupos |
| GET | `/group/{id}` | No | Ver grupo |
| POST | `/group` | Sí | Crear grupo |
| POST | `/group/first` | Sí | Crear grupo (con scoring defaults) |
| PUT | `/group/{id}` | Sí | Actualizar grupo |
| DELETE | `/group/{id}` | Sí | Eliminar grupo |

**POST /group/first (recomendado):**
```json
{
    "name": "Mis Amigos",
    "description": "Grupo de predicciones",
    "is_public": "1",
    "max_members": "20",
    "bet_amount": "10.00",
    "prize_1st_pct": "60",
    "prize_2nd_pct": "30",
    "prize_3rd_pct": "10",
    "pool_active": "1",
    "exact_score_points": "10",
    "match_result_points": "5",
    "correct_goal_points": "3",
    "unique_exact_points": "15",
    "multiplier_dieciseisavos": "1.5",
    "multiplier_octavos": "2.0",
    "multiplier_cuartos": "3.0",
    "multiplier_semifinal": "5.0",
    "multiplier_final": "10.0"
}
```

### 3.6 Miembros del Grupo

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/member` | No | Listar miembros |
| POST | `/member` | Sí | Agregar miembro |
| PUT | `/member/{id}` | Sí | Actualizar miembro |
| DELETE | `/member/{id}` | Sí | Quitar miembro |

### 3.7 Predicciones

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/prediction?group_id=1` | No | Listar predicciones |
| GET | `/prediction/{id}` | No | Ver predicción |
| POST | `/prediction` | Sí | Crear predicción |
| PUT | `/prediction/{id}` | Sí | Actualizar predicción |
| DELETE | `/prediction/{id}` | Sí | Eliminar predicción |

**POST /prediction:**
```json
{
    "match_id": 1,
    "group_id": 1,
    "predicted_home_score": 2,
    "predicted_away_score": 1,
    "predicted_result": "home_win",
    "bet_type": "exact_score",
    "confidence": 8
}
```

**Reglas:**
- Solo se puede predecir si el match está `scheduled`
- Ventana: 30 minutos antes del inicio del partido
- No duplicados (mismo user + match + group)
- `predicted_result`: `home_win`, `away_win`, `draw`
- `bet_type`: `exact_score`, `match_result`, `both_teams_score`
- `confidence`: 1-10

### 3.8 Invitaciones

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/invitation` | Sí | Ver mis invitaciones |
| POST | `/invitation` | Sí | Enviar invitación |
| POST | `/invitation/accept-code` | Sí | Unirse por código |
| POST | `/invitation/{id}/accept` | Sí | Aceptar invitación |
| DELETE | `/invitation/{id}` | Sí | Cancelar invitación |

**POST /invitation:**
```json
{
    "group_id": 1,
    "email": "amigo@email.com"
}
```

**POST /invitation/accept-code:**
```json
{
    "code": "ABC123"
}
```

### 3.9 Leaderboard

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/group/{groupId}/leaderboard` | No | Ranking del grupo |

**Response:**
```json
{
    "group_id": 1,
    "leaderboard": [
        {
            "user_id": 1,
            "first_name": "Juan",
            "total_points": 85,
            "total_predictions": 12,
            "correct_predictions": 8,
            "exact_scores": 3,
            "unique_predictions": 1,
            "pool_winnings": 25.50
        }
    ]
}
```

### 3.10 Scoring Dashboard (NUEVO)

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/group/{groupId}/scoring` | No | Dashboard completo del grupo |
| GET | `/group/{groupId}/scoring/user/{userId}` | No | Scoring detallado de usuario |

**GET /group/1/scoring:**
```json
{
    "data": {
        "group": { "group_id": 1, "name": "Mi Grupo" },
        "summary": {
            "total_predictions": 150,
            "scored_predictions": 120,
            "members": 10
        },
        "scoring_rules": {
            "exact_score_points": 10,
            "match_result_points": 5,
            "correct_goal_points": 3,
            "unique_exact_points": 15,
            "multipliers": {
                "dieciseisavos": 1.5,
                "octavos": 2.0,
                "cuartos": 3.0,
                "semifinal": 5.0,
                "final": 10.0
            }
        },
        "members": [
            {
                "user_id": 1,
                "first_name": "Juan",
                "total_points": 85,
                "total_predictions": 12,
                "correct_predictions": 8,
                "exact_scores": 3,
                "unique_predictions": 1,
                "pool_winnings": 25.50
            }
        ]
    }
}
```

**GET /group/1/scoring/user/1:**
```json
{
    "data": {
        "group": { "group_id": 1, "name": "Mi Grupo" },
        "summary": {
            "total_points": 85,
            "total_predictions": 12,
            "exact_scores": 3,
            "match_results": 5,
            "correct_goals": 7,
            "unique_exact": 1,
            "pool_winnings": 25.50
        },
        "matches": [
            {
                "match_id": 1,
                "match": "Brasil vs Argentina",
                "home_score": 2,
                "away_score": 1,
                "predicted_home_score": 2,
                "predicted_away_score": 1,
                "stage": "Final",
                "points_earned": 28,
                "breakdown": {
                    "exact_score": 10,
                    "match_result": 5,
                    "correct_goal_home": 3,
                    "correct_goal_away": 3,
                    "unique_exact": 15,
                    "stage_multiplier": 1.0,
                    "subtotal": 36,
                    "total": 28
                }
            }
        ]
    }
}
```

### 3.11 Pool de Premios (NUEVO)

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/pool?group_id=1` | Sí | Ver pool del grupo |
| GET | `/pool/{groupId}` | Sí | Detalle del pool + ganadores |
| POST | `/pool` | Sí | Contribuir (apostar) |
| POST | `/pool/{groupId}/distribute` | Sí | Distribuir premios (solo creador) |

**POST /pool:**
```json
{
    "group_id": 1
}
```
> Se descuenta `bet_amount` del saldo del usuario automáticamente.

**POST /pool/1/distribute:**
```json
{
    "distribution": [
        { "place": "1st", "user_id": 1, "points": 85, "percentage": 60, "prize": 60.00 },
        { "place": "2nd", "user_id": 2, "points": 70, "percentage": 30, "prize": 30.00 },
        { "place": "3rd", "user_id": 3, "points": 55, "percentage": 10, "prize": 10.00 }
    ]
}
```

### 3.12 Bracket / Multi-Fase (NUEVO)

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/tournament/{id}/bracket` | No | Bracket completo del torneo |
| GET | `/tournament/{id}/bracket/{stageId}` | No | Bracket de una fase |
| GET | `/tournament/{id}/groups` | No | Grupos del torneo |
| GET | `/tournament-group/{id}` | No | Grupo con equipos |
| GET | `/stage/{id}/standings` | No | Tabla de posiciones |
| GET | `/stage/{id}/advancement-rules` | No | Reglas de avance |
| GET | `/stage/{id}/advancements` | No | Equipos que avanzaron |

**GET /tournament/1/bracket:**
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
                    "teams": [...],
                    "standings": [...],
                    "matches": [...]
                }
            ]
        },
        {
            "stage_id": 2,
            "name": "Octavos",
            "is_knockout": true,
            "stage_order": 2,
            "matches": [...],
            "advancements": [...]
        }
    ]
}
```

### 3.13 Suscripciones

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/user/subscription` | Sí | Ver mi suscripción |
| GET | `/user/subscription/plans` | Sí | Ver planes disponibles |
| POST | `/user/subscription/checkout` | Sí | Crear sesión Stripe |
| POST | `/user/subscription/cancel` | Sí | Cancelar suscripción |

**Planes:**
| Plan | Precio Mensual | Precio Anual | Límite Predicciones/día | Límite Torneos |
|------|---------------|--------------|------------------------|----------------|
| Free | $0 | $0 | 5 | 2 |
| Pro | $4.99 | $39.99 | 20 | 5 |
| Premium | $9.99 | $79.99 | ∞ | ∞ |

### 3.14 Perfil de Usuario

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/user` | Sí | Ver perfil |
| PUT | `/user` | Sí | Actualizar perfil |
| PUT | `/user/password` | Sí | Cambiar contraseña |

---

## 4. Filtros Disponibles

Todos los endpoints de listado soportan filtros:

```
GET /team?name[eq]=Barcelona
GET /team?country[eq]=Spain
GET /match?status[eq]=finished
GET /match?match_date[gte]=2026-06-01
GET /tournament?season[eq]=2026
```

**Operadores:** `eq`, `gt`, `lt`, `gte`, `lte`

---

## 5. Scoring System

### Puntos por predicción (configurable por grupo)
| Concepto | Puntos Default |
|----------|---------------|
| Marcador exacto | 10 |
| Ganador acertado | 5 |
| Gol local acertado | 3 |
| Gol visitante acertado | 3 |
| Predicción única | 15 |

### Multiplicadores por fase (configurable por grupo)
| Fase | Multiplicador |
|------|---------------|
| Dieciseisavos | 1.5x |
| Octavos | 2.0x |
| Cuartos | 3.0x |
| Semifinal | 5.0x |
| Final | 10.0x |

### Fórmula
```
Total = (puntos_base × multiplicador_stage)
```

---

## 6. Pool de Premios

1. Admin del grupo crea grupo con `pool_active=1` y `bet_amount=10`
2. Miembros contribuyen vía `POST /pool` (se descuenta de su saldo)
3. Predicciones se procesan automáticamente cuando el match termina
4. Admin distribuye premios vía `POST /pool/{id}/distribute`
5. Ganadores reciben notificación por email

---

## 7. Notificaciones

| Notificación | Trigger | Canal |
|-------------|---------|-------|
| MatchPointsNotification | Match termina + scoring procesado | Email |
| PoolDistributedNotification | Pool distribuido | Email |

---

## 8. Errores Comunes

| Código | Significado |
|--------|------------|
| 401 | No autenticado |
| 403 | No autorizado (no eres admin del grupo) |
| 404 | Recurso no encontrado |
| 409 | Duplicado (ya existe una predicción para este match) |
| 422 | Validación fallida |
| 429 | Rate limit excedido |

---

## 9. Tips para Frontend

1. **Siempre enviar `Accept: application/json`** para recibir respuestas JSON
2. **Los PUT requieren TODOS los campos** (no hay PATCH)
3. **Las fechas vienen en formato ISO 8601** (ej: `2026-06-01T20:00:00.000000Z`)
4. **El scoring se procesa automático** cuando un match cambia a `finished`
5. **El leaderboard se actualiza automáticamente** después de scoring
6. **Los campos `is_promotional` y `pool`** se incluyen en las respuestas de tournaments y groups
