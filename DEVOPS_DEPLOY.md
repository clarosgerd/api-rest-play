# Informe Técnico — Equipo DevOps

> SoccerApi v1.0 | Julio 2026
> Despliegue SIT (System Integration Testing)

---

## 1. Stack Tecnológico

| Componente | Versión | Descripción |
|-----------|---------|-------------|
| PHP | 8.2+ | Lenguaje backend |
| Laravel | 12.x | Framework |
| MySQL | 8.0+ | Base de datos |
| Redis | 7.0+ | Cache + Queue (recomendado) |
| Node.js | 18+ | Solo para build de assets (Vite) |
| Composer | 2.x | Dependencias PHP |
| NPM | 9+ | Dependencias JS |
| Sanctum | - | API tokens (SPA/mobile auth) |

---

## 2. Requisitos del Servidor

### Mínimos (SIT)
- 2 CPU cores
- 4GB RAM
- 20GB disco
- PHP 8.2 con extensiones: mbstring, xml, ctype, json, bcmath, pdo, mysql, zip, gd

### Recomendados (Producción)
- 4 CPU cores
- 8GB RAM
- 50GB disco SSD

---

## 3. Variables de Entorno

### .env requeridas

```env
APP_NAME=SoccerApi
APP_ENV=sit
APP_KEY=base64:GENERAR_CON_php artisan key:generate
APP_DEBUG=true
APP_URL=https://sit.soccerapi.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sasport
DB_USERNAME=soccer_user
DB_PASSWORD=GENERAR_PASSWORD_SEGURO

SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu-email@gmail.com
MAIL_PASSWORD=app-password-gmail
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@soccerapi.com
MAIL_FROM_NAME="SoccerApi"

# APIs Externas
FOOTBALL_DATA_API_TOKEN=tu-token-football-data
API_FOOTBALL_KEY=tu-api-key-football

# Stripe (SIT usa test mode)
STRIPE_KEY=pk_test_xxx
STRIPE_SECRET=sk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx
STRIPE_PRO_MONTHLY_PRICE_ID=price_xxx
STRIPE_PRO_ANNUAL_PRICE_ID=price_xxx
STRIPE_PREMIUM_MONTHLY_PRICE_ID=price_xxx
STRIPE_PREMIUM_ANNUAL_PRICE_ID=price_xxx
```

---

## 4. Scripts de Despliegue

### 4.1 Deploy inicial (SIT)

```bash
#!/bin/bash
# deploy-sit.sh

set -e

echo "=== SoccerApi SIT Deploy ==="

# 1. Clonar repositorio
cd /var/www
git clone https://github.com/tu-org/soccer-api.git soccer-api-sit
cd soccer-api-sit

# 2. Instalar dependencias
composer install --no-dev --optimize-autoloader
npm install
npm run build

# 3. Configurar entorno
cp .env.example .env
php artisan key:generate

# 4. Configurar base de datos
# IMPORTANTE: Crear BD sasport en MySQL ANTES de ejecutar migraciones
mysql -u root -e "CREATE DATABASE IF NOT EXISTS sasport CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -e "CREATE USER IF NOT EXISTS 'soccer_user'@'localhost' IDENTIFIED BY 'TU_PASSWORD';"
mysql -u root -e "GRANT ALL PRIVILEGES ON sasport.* TO 'soccer_user'@'localhost';"
mysql -u root -e "FLUSH PRIVILEGES;"

# 5. Ejecutar migraciones y seeders
php artisan migrate:fresh --seed

# 6. Configurar permisos
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 7. Optimizar
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 8. Configurar scheduler (cron)
# * * * * * cd /var/www/soccer-api-sit && php artisan schedule:run >> /dev/null 2>&1

# 9. Configurar queue worker (supervisor o systemd)
# [program:soccer-api-worker]
# command=php /var/www/soccer-api-sit artisan queue:work --sleep=3 --tries=3
# autostart=true
# autorestart=true

echo "=== Deploy SIT completado ==="
echo "URL: https://sit.soccerapi.com"
```

### 4.2 Deploy actualizaciones

```bash
#!/bin/bash
# update-sit.sh

set -e

cd /var/www/soccer-api-sit

echo "=== Actualizando SoccerApi SIT ==="

# 1. Pull cambios
git pull origin develop

# 2. Actualizar dependencias
composer install --no-dev --optimize-autoloader
npm install && npm run build

# 3. Ejecutar migraciones (sin --fresh en actualizaciones)
php artisan migrate --force

# 4. Limpiar caché
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Reiniciar queue worker
php artisan queue:restart

echo "=== Actualización completada ==="
```

---

## 5. Configuración de Servidor Web

### Nginx

```nginx
server {
    listen 80;
    server_name sit.soccerapi.com;
    root /var/www/soccer-api-sit/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realroot$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Health check
    location /up {
        access_log off;
        return 200 "OK";
        add_header Content-Type text/plain;
    }

    # Cache de assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff2|woff|ttf|svg)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
```

### Apache (.htaccess)

```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/up$
    RewriteRule ^(.*)$ index.php?/$1 [L,QSA]

    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
</IfModule>
```

---

## 6. Configuración de Cola (Queue)

### Supervisor (Ubuntu)

```ini
; /etc/supervisor/conf.d/soccer-api-worker.conf
[program:soccer-api-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/soccer-api-sit artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/soccer-api-worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start "soccer-api-worker:*"
```

---

## 7. Scheduler (Cron)

```bash
# Editar crontab
crontab -e

# Agregar:
* * * * * cd /var/www/soccer-api-sit && php artisan schedule:run >> /dev/null 2>&1
```

**Tareas programadas:**
- `predictions:score-unscored` — Cada minuto (auto-scoring de matches finalizados)

---

## 8. Base de Datos

### Crear BD para SIT

```sql
CREATE DATABASE IF NOT EXISTS sasport
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'soccer_user'@'localhost'
  IDENTIFIED BY 'PASSWORD_SEGURO';

GRANT ALL PRIVILEGES ON sasport.* TO 'soccer_user'@'localhost';
FLUSH PRIVILEGES;
```

### Migraciones

```bash
# Fresh (desarrollo/SIT)
php artisan migrate:fresh --seed

# Actualización (producción)
php artisan migrate --force
```

### Tablas principales

| Tabla | Descripción |
|-------|-------------|
| `users` | Usuarios (con subscription_tier, balance) |
| `tournaments` | Torneos (con campos promocionales) |
| `tournamentstage` | Fases del torneo (grupos, octavos, etc.) |
| `teams` | Equipos |
| `matches` | Partidos (status: scheduled/live/finished) |
| `groups` | Grupos de predicciones (con scoring config y pool) |
| `groupmembers` | Miembros de grupos |
| `predictions` | Predicciones de usuarios |
| `grouppredictions` | Vinculación predicción-grupo |
| `predictionresults` | Resultados scoring (con points_breakdown JSON) |
| `grouppool` | Contribuciones y premios del pool |
| `subscriptions` | Historial de suscripciones |
| `payments` | Historial de pagos |
| `transactions` | Transacciones (bet, win, etc.) |
| `tournamentgroups` | Grupos de torneo (Grupo A, B, C...) |
| `tournamentgroupteams` | Equipos en grupos de torneo |
| `tournamentadvancementrules` | Reglas de avance entre fases |
| `tournamentadvancement` | Registro de equipos que avanzaron |

---

## 9. Endpoints de Health Check

```
GET /up                    → 200 OK (Laravel health check)
GET /api/v1/tournament     → 200 (API funcionando)
```

---

## 10. Monitoreo

### Logs

```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Queue worker logs
tail -f /var/log/soccer-api-worker.log

# Nginx/Apache logs
tail -f /var/log/nginx/soccer-api-error.log
```

### Comandos útiles

```bash
# Verificar estado de la cola
php artisan queue:monitor redis

# Ver jobs pendientes
php artisan queue:work --once

# Reintentar jobs fallidos
php artisan queue:retry all

# Limpiar jobs antiguos
php artisan queue:prune --hours=48
```

---

## 11. Seguridad

### Checklist SIT

- [ ] `APP_DEBUG=false` en producción
- [ ] `APP_KEY` generado con `php artisan key:generate`
- [ ] Permisos de BD solo para usuario específico
- [ ] HTTPS habilitado
- [ ] Headers de seguridad (X-Frame-Options, CSP)
- [ ] Rate limiting habilitado
- [ ] Webhook de Stripe con secret configurado
- [ ] Tokens de API externos en `.env` (no en código)

### Rate Limiting (Laravel 12 default)

- API no autenticada: 60 req/min
- API autenticada: 120 req/min

---

## 12. Variables de Entorno por Ambiente

| Variable | SIT | UAT | PROD |
|----------|-----|-----|------|
| `APP_ENV` | sit | uat | production |
| `APP_DEBUG` | true | false | false |
| `DB_HOST` | localhost | db-uat | db-prod |
| `STRIPE_KEY` | pk_test_xxx | pk_test_xxx | pk_live_xxx |
| `STRIPE_SECRET` | sk_test_xxx | sk_test_xxx | sk_live_xxx |
| `MAIL_MAILER` | log | smtp | smtp |
| `QUEUE_CONNECTION` | redis | redis | redis |
| `SESSION_DRIVER` | redis | redis | redis |

---

## 13. Troubleshooting

| Problema | Solución |
|----------|----------|
| `500 Internal Server Error` | Verificar `storage/logs/laravel.log` |
| `SQLSTATE[HY000] Connection refused` | Verificar MySQL corriendo y credenciales |
| `CSRF token mismatch` | Agregar header `Accept: application/json` |
| `401 Unauthenticated` | Verificar token Bearer en header |
| `Queue not processing` | Verificar `php artisan queue:work` y Redis |
| `Scheduler not running` | Verificar cron: `php artisan schedule:run` |
| `Stripe webhook failing` | Verificar `STRIPE_WEBHOOK_SECRET` |
| `Migrations failing` | Verificar permisos de BD y que tablas no existan |
