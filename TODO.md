# TODO — SoccerApi

> Última actualización: 2026-07-23

## Pendientes

### Testing
- [ ] Tests para Pool API endpoints (store, show, distribute)
- [ ] Tests para Scoring Dashboard (groupDashboard, userDashboard)
- [ ] Tests para Auto-scoring (MatchObserver, ScoreMatchPredictions Job)
- [ ] Tests para PoolDistributedNotification

### Features futuras
- [ ] WebSocket para leaderboard en tiempo real
- [ ] Paginación en endpoints de scoring
- [ ] Exportar leaderboard a PDF/CSV
- [ ] Rate limiting en endpoints públicos
- [ ] Cache de leaderboard (redis)
- [ ] Background job para sync automático de partidos desde APIs externas
- [ ] Endpoint para historial de predicciones por usuario (paginado)
- [ ] Filtros avanzados en leaderboard (por fecha, stage, etc.)
- [ ] Dashboard de analytics para admin (uso de la API, predicciones por día, etc.)

### DevOps
- [ ] Configurar CI/CD pipeline
- [ ] Variables de entorno para SIT/UAT/PROD
- [ ] Health check endpoint mejorado
- [ ] Logging centralizado (Sentry/Datadog)
- [ ] CDN para assets estáticos
- [ ] Backup automático de BD

### Frontend
- [ ] Integración con Stripe Checkout (redirección)
- [ ] Manejo de errores de validación (422)
- [ ] Loading states para scoring dashboard
- [ ] Polling o WebSocket para actualizaciones de scoring
