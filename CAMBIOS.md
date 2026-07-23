# Guía de Cambios — Scoring, Pool y Torneos Promocionales

> Fecha: 2026-07-22
> Versión: 1.0

---

## Resumen

Este documento describe los cambios implementados para:
1. **Sistema de Scoring** — Puntos configurables por grupo en BD, bonos por etapa, predicción única
2. **Pool de Premios** — Apuestas grupales con distribución configurable
3. **Torneos Promocionales** — Acceso gratuito temporal para marketing

---

## 1. Configuración (Per-Group en BD)

Todos los valores de puntos y multiplicadores se almacenan **por grupo** en la tabla `groups`. Cada grupo puede tener sus propias reglas:

```sql
-- Campos en la tabla groups
exact_score_points INT DEFAULT 10,     -- Marcador exacto acertado
match_result_points INT DEFAULT 5,     -- Ganador acertado
correct_goal_points INT DEFAULT 3,     -- Gol acertado (por cada equipo)
unique_exact_points INT DEFAULT 15,    -- Predicción única (solo tú en el grupo)
multiplier_dieciseisavos DECIMAL(3,1) DEFAULT 1.5,
multiplier_octavos DECIMAL(3,1) DEFAULT 2,
multiplier_cuartos DECIMAL(3,1) DEFAULT 3,
multiplier_semifinal DECIMAL(3,1) DEFAULT 5,
multiplier_final DECIMAL(3,1) DEFAULT 10,
bet_amount DECIMAL(10,2) DEFAULT 0,    -- Valor de apuesta
prize_1st_pct INT DEFAULT 60,          -- % para 1er lugar
prize_2nd_pct INT DEFAULT 30,          -- % para 2do lugar
prize_3rd_pct INT DEFAULT 10,          -- % para 3er lugar
pool_active BOOLEAN DEFAULT FALSE      -- Pool habilitado
```

**Para cambiar los puntos de un grupo:** Actualiza directamente los campos del grupo via API.

---

## 2. Base de Datos

### Nuevas tablas
- **`grouppool`** — Registro de contribuciones y premios del pool

### Campos nuevos

**`groups`** (tabla existente):
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `exact_score_points` | int | 10 | Puntos por marcador exacto |
| `match_result_points` | int | 5 | Puntos por ganador acertado |
| `correct_goal_points` | int | 3 | Puntos por gol acertado |
| `unique_exact_points` | int | 15 | Puntos por predicción única |
| `multiplier_dieciseisavos` | decimal(3,1) | 1.5 | Multiplicador dieciseisavos |
| `multiplier_octavos` | decimal(3,1) | 2.0 | Multiplicador octavos |
| `multiplier_cuartos` | decimal(3,1) | 3.0 | Multiplicador cuartos |
| `multiplier_semifinal` | decimal(3,1) | 5.0 | Multiplicador semifinal |
| `multiplier_final` | decimal(3,1) | 10.0 | Multiplicador final |
| `bet_amount` | decimal(10,2) | 0 | Cuánto apuesta cada miembro |
| `prize_1st_pct` | decimal(5,2) | 60 | % para 1er lugar |
| `prize_2nd_pct` | decimal(5,2) | 30 | % para 2do lugar |
| `prize_3rd_pct` | decimal(5,2) | 10 | % para 3er lugar |
| `pool_active` | boolean | false | Pool habilitado |

**`predictionresults`** (tabla existente):
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `points_breakdown` | JSON | Desglose detallado de puntos |

**`tournaments`** (tabla existente):
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `is_promotional` | boolean | false | Torneo promocional activo |
| `promotional_tier_required` | int | 2 | Tier que requiere normalmente (0=free,1=pro,2=premium) |
| `promotional_ends_at` | timestamp | null | Cuándo expira la promo |

---

## 3. Sistema de Scoring (reescribir)

### Fórmula de puntos

```
Subtotal = exact_score + match_result + correct_goal_home + correct_goal_away + unique_exact
Total = round(Subtotal × stage_multiplier)
```

### Detalle de reglas

| Regla | Campo en BD | Condición |
|-------|-------------|-----------|
| Marcador exacto | `exact_score_points` | `predicted_home == actual_home AND predicted_away == actual_away` |
| Ganador acertado | `match_result_points` | `predicted_result == actual_result` |
| Gol home acertado | `correct_goal_points` | `predicted_home == actual_home` |
| Gol away acertado | `correct_goal_points` | `predicted_away == actual_away` |
| Predicción única | `unique_exact_points` | Solo UN usuario en el grupo acertó el marcador exacto |

### Multiplicadores de etapa

Solo aplica a etapas knockout (`is_knockout = true` en `tournamentstage`). Cada grupo configura sus propios multiplicadores (campos `multiplier_*` en la tabla `groups`).

### points_breakdown (JSON)

```json
{
    "exact_score": 10,
    "match_result": 5,
    "correct_goal_home": 3,
    "correct_goal_away": 3,
    "unique_exact": 15,
    "stage_multiplier": 2.0,
    "subtotal": 36,
    "total": 72
}
```

---

## 4. Pool de Premios

### Flujo

1. **Crear grupo** con `pool_active=true` y `bet_amount=X`
2. **Contribuir** al pool: `pool:contribute --group_id=X --user_id=Y`
   - Descuenta del balance del usuario
   - Crea registro en `grouppool`
   - Crea registro en `transactions` (type: bet)
3. **Distribuir** premios: `pool:distribute --group_id=X`
   - Calcula puntos de cada miembro
   - Top 3 ganan según porcentajes del grupo
   - Agrega balance a ganadores
   - Crea registros en `transactions` (type: win)

### Comandos

```bash
# Contribuir al pool
php artisan pool:contribute --group_id=1 --user_id=5

# Distribuir premios
php artisan pool:distribute --group_id=1
```

### Ejemplo de distribución

Pool total: $20 (2 miembros × $10)
- 1er lugar (60%): $12
- 2do lugar (30%): $6
- 3er lugar (10%): $2 (no aplica si solo hay 2 miembros)

---

## 5. Torneos Promocionales

### Concepto

Un torneo puede marcarse como "promocional" para dar acceso gratuito a usuarios free durante un periodo limitado. Herramienta de marketing para convertir usuarios free en suscriptores.

### Cómo funciona

1. Crear o actualizar torneo con `is_promotional=true`
2. Establecer `promotional_tier_required` (tier normal: 2=premium)
3. Establecer `promotional_ends_at` (fecha de expiración)
4. Los usuarios free obtienen acceso COMPLETO durante el periodo
5. Después de expirar, vuelve a requerir tier normal

### Comando de expiración

```bash
# Expirar torneos promocionales vencidos
php artisan promotions:expire
```

Programar diariamente en `routes/console.php`:
```php
$schedule->command('promotions:expire')->daily();
```

### Middlewares actualizados

- **CheckGroupLimits**: Bypass para torneos promocionales
- **CheckSubscriptionTier**: Bypass para torneos promocionales

---

## 6. Archivos Modificados/Creados

### Nuevos
| Archivo | Descripción |
|---------|-------------|
| `config/scoring.php` | Valores configurables de scoring |
| `app/Models/GroupPool.php` | Modelo del pool |
| `app/Console/Commands/PoolContributionCommand.php` | Contribuir al pool |
| `app/Console/Commands/DistributePoolCommand.php` | Distribuir premios |
| `app/Console/Commands/ExpirePromotionsCommand.php` | Expirar promos |
| `tests/Feature/PoolTest.php` | Tests del pool (8 tests) |
| `tests/Feature/PromotionalTournamentTest.php` | Tests de promos (9 tests) |
| 4 migraciones | Base de datos |

### Modificados
| Archivo | Cambios |
|---------|---------|
| `app/Models/Groups.php` | Campos pool + relación + helper |
| `app/Models/Tournament.php` | Campos promo + helper + cast |
| `app/Models/PredictionResults.php` | points_breakdown + cast |
| `app/Console/Commands/ScorePredictionsCommand.php` | Reescritura completa |
| `app/Http/Middleware/CheckGroupLimits.php` | Bypass promocional |
| `app/Http/Middleware/CheckSubscriptionTier.php` | Bypass promocional |
| `app/Http/Controllers/TournamentController.php` | Campos promo |
| `app/Http/Controllers/GroupsController.php` | Campos pool |
| `app/Http/Controllers/LeaderboardController.php` | Nuevas columnas |
| `app/Http/Requests/StoreTournamentRequest.php` | Validación promo |
| `app/Http/Requests/UpdateTournamentRequest.php` | Validación promo |
| `app/Http/Requests/StoreGroupsRequest.php` | Validación pool |
| `app/Http/Resources/TournamentResource.php` | Campos promo |
| `tests/Feature/ScoringTest.php` | Tests actualizados (12 tests) |

---

## 7. Tests

```
109 tests, 270 assertions, 0 failures
```

| Suite | Tests | Descripción |
|-------|-------|-------------|
| ScoringTest | 12 | Scoring completo: exacto, ganador, goles, único, multiplicadores |
| PoolTest | 8 | Contribución, distribución, validaciones |
| PromotionalTournamentTest | 9 | Crear, acceder, expirar, API |
| SubscriptionTest | 14 | Sistema freemium |
| AuthTest | 9 | Autenticación |
| GroupTest | 6 | CRUD grupos |
| InvitationTest | 11 | Invitaciones |
| MatchTest | 6 | CRUD partidos |
| OddsBetsTest | 11 | Odds y bets |
| PredictionTest | 8 | CRUD predicciones |
| TeamTest | 7 | CRUD equipos |
| TournamentTest | 6 | CRUD torneos |

---

## 8. Comandos Artisan

```bash
# Scoring
php artisan predictions:score                    # Scoring de predicciones
php artisan predictions:score --match_id=5       # Scoring de un match específico

# Pool
php artisan pool:contribute --group_id=1 --user_id=5
php artisan pool:distribute --group_id=1

# Promociones
php artisan promotions:expire                    # Expirar torneos promocionales
```

---

## 9. Para cambiar valores de scoring

1. Abrir `config/scoring.php`
2. Modificar los valores necesarios
3. Ejecutar `php artisan config:clear` si está en producción
4. Los tests usan los valores del config (tests/Feature/ScoringTest.php)

**No es necesario tocar código para ajustar puntos o multiplicadores.**
