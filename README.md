# Friend Soccer API

Backend en Laravel de **Friend Soccer**, una plataforma de pronósticos deportivos: torneos y copas
reales (sincronizados desde football-data.org, ESPN y API-Football) y privados, grupos de
pronosticadores con tabla de posiciones y pool de premios, un competidor de IA ("Franco") y
suscripciones de pago.

Frontend (React): [front-end-soccer](https://github.com/clarosgerd/front-end-soccer).

## Qué incluye

- **Torneos y grupos**: torneos públicos (sincronizados) o privados, con fases/bracket, grupos de
  pronosticadores con scoring configurable y pool de premios 1°/2°/3°.
- **Predicciones y Franco (IA)**: motor de predicción (Poisson + Elo + Elo histórico de un dataset de
  Kaggle + forma + head-to-head + momentum, con ajustes específicos para CONMEBOL) que compite como un
  participante más dentro de los grupos.
- **Suscripciones**: planes Predictor / Pro / Premium, cobrados en Bolivianos vía **SIP** (QR bancario,
  ver `app/Services/SipService.php`). Stripe queda integrado pero sin uso activo (se dejó por si se
  retoma para USD/tarjeta internacional).
- **Panel de soporte (super admin)**: gestión de usuarios, confirmación manual de pagos SIP pendientes, y
  [Laravel Telescope](https://laravel.com/docs/telescope) para ver requests/excepciones/jobs fallidos sin
  entrar por SSH — todo detrás del rol `role = 'admin'`.
- **Sync de datos deportivos**: comandos Artisan que traen equipos/partidos/jugadores/posiciones desde
  football-data.org, ESPN, API-Football y CONMEBOL (`app/Console/Commands/Sync*.php`).

## Requisitos

- PHP 8.2+
- Composer
- MySQL (o SQLite para tests — ver `phpunit.xml`)
- Un worker de colas (`QUEUE_CONNECTION=database`) para que salgan los correos (bienvenida, invitación a
  grupo, etc.)

## Instalación

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed   # crea admin@demo.com / password (role=admin)
php artisan serve
```

En otra terminal, para que los correos encolados se envíen de verdad:

```bash
php artisan queue:work
```

## Variables de entorno

Además de las estándar de Laravel (`DB_*`, `MAIL_*`, `QUEUE_CONNECTION=database`), esta app usa:

| Variable | Para qué |
|---|---|
| `FOOTBALL_DATA_API_TOKEN` | Sync de ligas/torneos vía football-data.org |
| `THESPORTSDB_API_KEY` | Datos complementarios de equipos |
| `API_FOOTBALL_KEY` | Sync de CONMEBOL/Copa del Mundo vía API-Football |
| `STRIPE_KEY` / `STRIPE_SECRET` / `STRIPE_WEBHOOK_SECRET` / `STRIPE_*_PRICE_ID` | Checkout de suscripciones en USD (código presente, sin uso activo hoy) |
| `SIP_BASE_AUTH_URL` / `SIP_BASE_API_URL` / `SIP_USERNAME` / `SIP_PASSWORD` / `SIP_APIKEY_SERVICIO` | Credenciales del merchant SIP para generar QR de cobro en Bolivianos |
| `SIP_CALLBACK_URL` / `SIP_CALLBACK_BASIC_USER` / `SIP_CALLBACK_BASIC_PASSWORD` | Webhook que SIP llama para confirmar un pago (`SubscriptionController::sipCallback`) |

Ninguna de estas vive en `.env.example` (son credenciales reales, no placeholders de documentación) —
pedilas al equipo o generá las tuyas de prueba.

## Tareas programadas

`routes/console.php` define, además del sync deportivo, el ciclo de Franco:

- `ai:predict` — genera predicciones de partidos próximos y evalúa los que ya terminaron (cada hora).
- `kaggle:import --force` + `kaggle:compute-elo --incremental` — actualiza el Elo histórico (semanal).

Para que corran en producción, agregá a cron:

```
* * * * * php /path-to-app/artisan schedule:run >> /dev/null 2>&1
```

## Tests

```bash
php artisan test
```

Usa SQLite en memoria (`phpunit.xml`) — no toca la base de datos real. Los servicios externos (SIP,
football-data, Stripe) se mockean con `Http::fake()`/mocks, nunca se llama a las APIs reales desde los
tests.

## Soporte / Admin

Con una cuenta `role = 'admin'` (la seed crea `admin@demo.com` / `password`):

- `GET/PATCH /api/v1/admin/users` — listar, cambiar plan, resetear contraseña, suspender cuentas.
- `GET /api/v1/admin/subscriptions` + `POST .../confirm` — confirmar manualmente un pago SIP que quedó
  `pending_payment` (el usuario pagó pero el callback de SIP nunca llegó).
- `/telescope` — logs, excepciones y jobs fallidos.
