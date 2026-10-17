# Franco (Chelo) — Competidor IA de Predicciones

Resumen de la sesión de trabajo para mejorar la precisión de **Franco Marcelo (Chelo)**, el competidor IA de la plataforma Friend Soccer.

---

## Objetivo

Mejorar la precisión de Franco como competidor IA integrando:
1. Dataset histórico Kaggle (~50K partidos de selecciones nacionales)
2. Conocimiento específico CONMEBOL (altitud, goleadores, torneos)

---

## 1. Dataset Kaggle (selecciones nacionales)

### Migración
`2026_07_29_240000_create_kaggle_tables.php` — 5 tablas:

| Tabla | Contenido |
|-------|-----------|
| `kaggle_results` | partidos históricos (fecha, equipos, goles, torneo, país, neutral) |
| `kaggle_goalscorers` | goleadores, autogoles, penales |
| `kaggle_shootouts` | tandas de penales |
| `kaggle_former_names` | nombres anteriores de selecciones |
| `kaggle_elo_ratings` | Elo calculado (unique por equipo) |

### Import
`php artisan kaggle:import` — streaming con `fopen` + `fgetcsv` (no HTTP, batches de 100). Fuente: `github.com/martj42/international_results`.

**Datos:** 49,520 results · 47,914 goalscorers · 683 shootouts · 36 former_names

---

## 2. Elo Kaggle

### `KaggleEloService`
- Replay cronológico de los 49K partidos
- K-factor según tipo de torneo: friendly 10, cup 25, league 15, other 20
- Multiplicador por diferencia de goles
- `computeAll` / `computeIncremental` / `getMatchFeatures` / `hasBothTeams`
- Cache en memoria + persistencia

### Comando
`php artisan kaggle:compute-elo` (flags: `--incremental`, `--up-to=YYYY-MM-DD`)

**Top-5 Elo:** Spain 2155 · Argentina 2120 · England 2050 · France 2048 · Brazil 2013

---

## 3. Conocimiento CONMEBOL

### `ConmebolService`
- `COUNTRY_ALTITUDE`: Bolivia 3640, Ecuador 2850, Colombia 2640, Perú 150, etc.
- `altitudeFactor()` — ajuste xG por altitud (umbral 1000m, +3% por 500m, cap 1.25)
- `isConmebolTournament()` / `isConmebolCountry()` / `isConmebolClash()`

### `SyncConmebolCommand` — `conmebol:sync`
- `--competition=libertadores|sudamericana`
- `--season=YYYY`
- **`--topscorers`** (nuevo): sincroniza goleadores desde API-Football a la tabla `players`

---

## 4. Goleadores CONMEBOL

### Migración
`2026_07_29_250000_add_scoring_stats_to_players_table.php` — agregó a `players`:
`goals`, `assists`, `games_played`, `games_minutes`, `tournament_id`, `season`
+ unique `(external_id, tournament_id, season)`.

### Sincronización
```bash
php artisan conmebol:sync --topscorers --season=2022
php artisan conmebol:sync --topscorers --season=2023
php artisan conmebol:sync --topscorers --season=2024
```

**Goleadores por torneo:**

| Temporada | Copa Libertadores | Copa Sudamericana |
|-----------|-------------------|-------------------|
| 2022 | Pedro (Flamengo) | B. Cuesta (Melgar) |
| 2023 | G. Cano (Fluminense) | G. Mastriani (América-MG) |
| 2024 | **Júnior Santos (Botafogo, 10 g)** | A. Martínez (Racing) |

> Nota: API-Football free plan solo permite temporadas 2022-2024. Para 2025 hay que subir plan o usar otra fuente.

---

## 5. PredictionEngineService v2.2

Ensemble de 6 modelos. Pesos:

**Con Kaggle:** Poisson 0.30 · Kaggle-Elo 0.20 · Elo 0.10 · Form 0.18 · H2H 0.10 · Momentum 0.12

**Sin Kaggle:** Poisson 0.35 · Elo 0.25 · Form 0.20 · H2H 0.10 · Momentum 0.10

### Mejoras CONMEBOL (v2.1 → v2.2)
1. **Altitud:** xG del local ajustado si el país tiene altitud (ej. Bolivia +25%)
2. **Fallback Kaggle por país:** en partidos CONMEBOL usa el Elo del país del club
3. **Bonus confianza +10%** en torneos CONMEBOL (Franco "conoce la región")
4. **`strikerFactor()`** (v2.2): en CONMEBOL, el xG de cada equipo se ajusta según su mejor goleador (max goles en una temporada) vs. la media CONMEBOL. Rango **[0.9, 1.15]** + bonus de confianza si un goleador domina.
5. Features expuestas: `is_conmebol`, `altitude_factor`, `home_striker_factor`, `away_striker_factor`

### Verificación real
```
Botafogo (Júnior Santos, 10 goles) → factor 1.15
River Plate (Borja, 6 goles)       → factor 1.037
Predicción: Botafogo 2-1 River, confianza 0.621
```

---

## 6. Visibilidad de predicciones de Franco

Las predicciones de Franco se generan internamente al programar el partido, pero **solo se revelan cuando el partido termina**:

- Backend: `AiCompetitorController@prediction` y `AiPredictionController@show` devuelven `{ data: null }` si `status !== 'finished'`
- Frontend: `MatchPredictionCard.tsx` renderiza el badge de IA solo si `match.status === 'finished'`
- Badge `IA` en el leaderboard para `ai_competitor_enabled`

---

## 7. Bug crítico corregido: config cache

**Síntoma:** `php artisan test` ejecutaba `migrate:fresh` contra **MySQL real** en vez de sqlite `:memory:`, borrando la BD en cada corrida.

**Causa:** caché de configuración vencida (`bootstrap/cache/config.php`). `env('DB_CONNECTION')` devolvía `sqlite` pero `config('database.default')` devolvía `mysql`.

**Solución:** `php artisan config:clear`

---

## 8. Tests

| Proyecto | Resultado |
|----------|-----------|
| Backend (Laravel) | **135/135 pass** (391 assertions) |
| Frontend (React) | 46/46 pass + tsc + lint limpios |

Fixes incluidos:
- `SubscriptionTest`: se arregló solo con `config:clear` (era el bug de la caché)
- `TournamentGroupTest`: faltaba `created_by` en el torneo de prueba

---

## 9. Estado final de la BD

```
Kaggle:         49,520 partidos + Elo (~350 equipos)
CONMEBOL:       143 equipos · 936 partidos · 118 goleadores (6 torneos 2022-2024)
```

---

## Comandos útiles

```bash
# Kaggle
php artisan kaggle:import                 # importa dataset
php artisan kaggle:compute-elo            # recalcula Elo completo
php artisan kaggle:compute-elo --incremental

# CONMEBOL
php artisan conmebol:sync --competition=libertadores --season=2024
php artisan conmebol:sync --topscorers --season=2024   # incluye goleadores

# Tests
php artisan test                           # backend (usa sqlite, seguro)
npm test                                   # frontend
```

---

## Archivos clave

| Archivo | Rol |
|---------|-----|
| `app/Services/PredictionEngineService.php` | Motor de predicción v2.2 (ensemble + altitud + goleadores) |
| `app/Services/KaggleEloService.php` | Elo histórico de selecciones |
| `app/Services/ConmebolService.php` | Altitudes y detección CONMEBOL |
| `app/Console/Commands/SyncConmebolCommand.php` | Sync CONMEBOL + goleadores |
| `app/Console/Commands/KaggleImportCommand.php` | Import dataset Kaggle |
| `app/Console/Commands/KaggleComputeEloCommand.php` | Cálculo Elo |
| `app/Models/Player.php` | Goleadores (goals, season, tournament_id) |
| `app/Http/Controllers/AiCompetitorController.php` | Ocultar predicción hasta `finished` |
| `src/components/domain/MatchPredictionCard.tsx` | Badge IA solo si partido finalizado |
