<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(\Illuminate\Foundation\Inspiring::quote());
})->purpose('Display an inspiring quote');

// Full sync: football-data.org (current seasons) + ESPN every 4 hours
Schedule::command('sync:all')->cron('0 */4 * * *');

// Live scores: ESPN fixtures only, hourly (skip logic limits to every 2h effectively)
Schedule::command('espn:sync --type=fixtures')->hourly();

// IA Franco (16/10/2026) — genera predicciones para partidos programados sin
// una todavía, evalúa los que ya terminaron (was_correct) y actualiza el Elo
// de los equipos involucrados. 15 min después de cada sync por hora, para
// que ya haya datos frescos (resultados/estados) de football-data.org/ESPN
// que evaluar. Sin --evaluate: igual corre ambas fases cada vez (ver
// AiPredictCommand::handle()).
Schedule::command('ai:predict')->hourlyAt(15)->withoutOverlapping();

// Elo histórico Kaggle (16/10/2026) — kaggle:compute-elo --incremental por
// sí solo es un no-op permanente si nadie vuelve a traer datos nuevos del
// dataset (kaggle:import sin --force no reimporta nada si ya hay filas).
// Se agrega kaggle:import --force semanal ANTES del compute-elo
// incremental para que de verdad haya partidos nuevos que procesar cuando
// el dataset de origen (GitHub) se actualice. Semanal, no diario: es un
// dataset histórico que cambia lento (solo tras partidos de selecciones
// reales), y el import re-descarga ~97K filas por HTTP sin reintentos —
// domingo de madrugada para no competir con los syncs normales.
Schedule::command('kaggle:import --force')->weeklyOn(0, '03:00')->withoutOverlapping();
Schedule::command('kaggle:compute-elo --incremental')->weeklyOn(0, '03:15')->withoutOverlapping();

// Suscripciones (19/10/2026) — ambos comandos ya existían y funcionan bien,
// pero nunca estuvieron agendados acá: dependían de ejecución manual.
// check-expired primero (baja a 'free' lo que ya venció) y remind-expiring
// después, para no recordatorear algo que este mismo run ya expiró.
Schedule::command('subscription:check-expired')->dailyAt('07:00');
Schedule::command('subscription:remind-expiring')->dailyAt('07:05');
