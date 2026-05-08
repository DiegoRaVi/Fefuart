# 📊 Análisis del Proyecto

## Resumen ejecutivo
El sistema actual es una demo funcional con dos objetivos de negocio mezclados en un mismo flujo operativo:

- Captación y gestión de solicitudes de eventos de Live Art.
- Venta de productos personalizados por encargo.

La solución está partida en dos bloques:

- Cliente estático multipágina en HTML, CSS y JS vanilla en app/Client.
- API Laravel en app/Server con JWT, controladores y acceso Eloquent directo.

Como demo, cumple el objetivo de validar idea. Como producto de producción, hoy presenta riesgos críticos de seguridad, deuda de arquitectura y fragilidad operativa.

## Qué hace el proyecto hoy

- Registro e inicio de sesión de usuarios.
- Solicitud de servicios de Live Art.
- Creación de productos personalizados.
- Carrito/pedido básico con cambio de estado.
- Panel administrativo para revisar eventos y pedidos.

## Objetivo aparente del producto

- Conseguir leads y cierres de eventos en vivo para la artista.
- Vender arte personalizado y gestionar pedidos.

## Tipos de usuario identificados

- Usuario cliente: solicita Live Art y compra productos personalizados.
- Admin: gestiona estados de eventos y pedidos.
- Asistente (objetivo acordado para el rebuild): rol operativo intermedio en backoffice.

## Análisis técnico (estado actual)

### Estructura y organización

- Separación física Client/Server correcta como punto de partida.
- Organización interna del backend centrada en controladores con demasiada lógica.
- Frontend con scripts embebidos por vista y acoplamiento fuerte al backend.

### Uso de Laravel

- Hay rutas, modelos y controladores, pero falta diseño por capas.
- No hay separación sólida entre lógica de aplicación y acceso a datos.
- Falta estandarizar FormRequest, Resources, Policies y servicios de dominio.

### Separación de responsabilidades

- Controllers contienen validación, autorización, reglas de negocio y persistencia.
- No hay capa de casos de uso (Application) ni de dominio explícito.
- Reglas de estados de pedidos/eventos están repartidas y no centralizadas.

### Patrones y convenciones

- Patrón dominante: Active Record + controlador gordo.
- Falta uso consistente de DTOs/Actions/Services.
- Contratos API con inconsistencias de respuesta entre endpoints.

### Calidad del código

- Nomenclatura y estilo no homogéneos.
- Lógica repetida en frontend y backend.
- Acoplamiento entre vistas y endpoints sin tipado de contratos.

### Seguridad

Estado encontrado y corregido en Sprint 1:

- Escalamiento de privilegios en registro.
- Exposición de perfil por id sin control robusto.
- Riesgo de modificación de pedidos ajenos.
- Riesgo IDOR en productos por pedido.

Riesgos aún pendientes en la versión legacy:

- Token en localStorage en frontend legacy.
- Falta política integral de rate limiting y auditoría de seguridad.

### Escalabilidad y rendimiento

- Consultas sin paginación consistente en varios listados.
- Sin estrategia de caché por dominio.
- Sin colas para tareas pesadas (notificaciones, media processing).

### Testing y mantenibilidad

- Cobertura histórica casi inexistente.
- Sprint 1 agregó pruebas de seguridad y autorización, pero todavía falta cobertura de flujos de negocio completos.

---

# ⚠️ Problemas Detectados

## Críticos

1. Registro permitía rol desde payload del cliente.
2. Actualización de pedido con autorización insuficiente.
3. Riesgo IDOR en operaciones de productos vinculados a pedido.
4. Contratos de rutas con métodos inconsistentes.

## Altos

1. Control de acceso y reglas de estado dispersas.
2. Contratos frontend-backend no uniformes.
3. Dependencia fuerte de scripts embebidos por página.

## Medios

1. Falta de modularidad real por dominio.
2. Ausencia de observabilidad de negocio y técnica.
3. Manejo de errores heterogéneo.
4. UX admin frágil ante respuestas no esperadas.

## Deuda técnica estructural

- Backend sin capa de aplicación y dominio.
- Frontend sin arquitectura de estado escalable.
- Sin estrategia de evolución API versionada robusta.

---

# 🏗️ Propuesta de Arquitectura

## Enfoque recomendado

Rebuild desde cero con:

- Laravel como API en modular monolith.
- Clean Architecture ligera por módulo de negocio.
- SPA React separada consumiendo API versionada.

## Módulos de dominio propuestos

- IdentityAccess
- Catalog
- Cart
- OrderManagement
- LiveArtBooking
- BackofficeOps
- Notifications
- MediaAssets

## Capas por módulo

- Interfaces (HTTP): Controllers, FormRequests, API Resources.
- Application: UseCases/Actions, DTOs, orchestración transaccional.
- Domain: Entidades, Value Objects, reglas e invariantes.
- Infrastructure: Eloquent repositories, storage, mail, integraciones externas.

## Estructura objetivo de carpetas (backend)

app/
- Modules/
- Shared/
- Support/

app/Modules/OrderManagement/
- Domain/
- Application/
- Infrastructure/
- Interfaces/Http/

## Reglas clave

- Controllers delgados: solo entrada/salida HTTP.
- Casos de uso como unidad principal de negocio.
- Reglas de estado centralizadas por agregado.
- Repositorios por interfaz en Application/Domain y concretos en Infrastructure.
- Errores de dominio y de infraestructura claramente separados.

## Hoja de ruta técnica (8-12 semanas)

- Hito A: base técnica, auth/autorización, convenciones API.
- Hito B: catálogo, carrito y checkout de productos con pago online.
- Hito C: Live Art por solicitud de presupuesto y backoffice operativo.
- Hito D: hardening, observabilidad, QA final y salida a producción.

---

# 🔧 Backend (Laravel)

## Diseño recomendado

- REST versionado: /api/v1.
- Contrato de respuesta uniforme:
  - success: data + meta opcional.
  - error: code + message + details + trace_id.

## Gestión de lógica de negocio

- Mover reglas a UseCases por escenario:
  - RegisterUser
  - CreateOrGetCart
  - AddCustomProductToCart
  - CheckoutOrder
  - RequestLiveArtEvent
  - ReviewLiveArtEvent

## Autenticación y autorización

- Recomendación principal: Sanctum con cookie HttpOnly para SPA en mismo dominio.
- Alternativa: JWT solo si la topología técnica obliga a token bearer cross-domain.
- Policies/Gates por recurso: Order, Product, Event, UserProfile.
- RBAC inicial: admin + assistant + user.

## Manejo de errores

- Exception handler centralizado con catálogo de errores.
- Validación con FormRequest.
- Errores de dominio explícitos (BusinessRuleViolation, UnauthorizedAction, InvalidTransition).

## Datos y consistencia

- Transacciones en operaciones multi-entidad.
- Estados definidos por máquina de estados:
  - Order: cart -> pending -> paid -> shipped/cancelled.
  - Event: pending -> confirmed/rejected -> done.

## Testing

- Unit: reglas de dominio y transiciones.
- Feature: auth, autorización, flujos críticos API.
- Integración: servicios externos (pago/notificaciones).
- E2E API contract tests para endpoints críticos.

## Observabilidad y operación

- Logs estructurados con correlation id.
- Métricas de negocio y técnicas.
- Auditoría de acciones administrativas.

---

# 🎨 Frontend (Decisión y arquitectura)

## Decisión

Se recomienda React para este proyecto.

## Justificación frente a Vue/Angular

- React ofrece mejor equilibrio para escalabilidad y modularidad en un equipo pequeño/medio.
- Ecosistema muy maduro para estado servidor, formularios y arquitectura por features.
- Integración natural con Vite y API Laravel desacoplada.
- Angular sería sobrecoste inicial para el alcance actual.
- Vue también encaja, pero React ofrece mayor disponibilidad de patrones y talento para crecimiento.

## Arquitectura frontend recomendada

- React + TypeScript + Vite.
- React Router para navegación y guardas por rol.
- TanStack Query para estado servidor.
- Zustand o Context modular para estado cliente mínimo.
- React Hook Form + Zod para formularios robustos.
- Design system propio con componentes base reutilizables.

## Organización por features

src/
- app/
- features/auth/
- features/live-art-booking/
- features/catalog-orders/
- features/backoffice/
- shared/ui/
- shared/api/
- shared/lib/

## Objetivos UX técnicos

- Formularios con feedback claro y manejo de error real.
- Flujos robustos offline/intermitencia mínima.
- Accesibilidad AA desde el inicio.
- Rendimiento optimizado en media y galería.

---

# 🚀 Ideas de Mejora

## Producto

- Funnel dual explícito: Live Art (lead) y productos (compra).
- Paquetes orientativos de Live Art para facilitar cotización.
- Estados operativos de lead con SLA visible.

## UX/UI

- Unificar narrativa de marca y CTAs por intención.
- Simplificar formularios por pasos para mejorar conversión.
- Dashboard de cliente con estado de pedido/solicitud.

## Experiencia en directo (Live Art)

- Agenda tentativa por disponibilidad.
- Seguimiento de solicitud con timeline.
- Confirmaciones automáticas y recordatorios.

## Monetización

- Pago online para productos personalizados (alcance acordado).
- Add-ons en checkout (urgencia, marco premium, copia extra).
- Cross-sell entre evento y producto con bundles.

## Operación interna

- Backoffice por colas de trabajo.
- Plantillas de respuesta y comunicación con cliente.
- Indicadores de conversión por canal.

---

# ❓ Preguntas Clave

1. ¿Cuál es el KPI principal del trimestre: cierres de eventos, facturación productos o ratio mixto ponderado?
2. ¿Qué SLA comercial se compromete para responder solicitudes de Live Art?
3. ¿Qué información mínima necesita la artista para cotizar sin ida y vuelta excesiva?
4. ¿Cómo se priorizan leads de Live Art en alta demanda?
5. ¿Qué pasarela de pago se usará para productos y en qué mercados?
6. ¿Qué política de devoluciones/cancelaciones se publicará para productos y eventos?
7. ¿Qué acciones exactas debe poder ejecutar el rol assistant en backoffice?
8. ¿Qué métricas se considerarán éxito en los primeros 90 días post-lanzamiento?
9. ¿Se requiere versión bilingüe ES/EN en MVP o fase posterior?
10. ¿Qué restricciones legales/fiscales hay para facturación y tratamiento de datos?

---

## Estado de implementación actual (abril 2026)

- Sprint 1 de hardening legacy completado para reducir riesgo inmediato de seguridad/autorización.
- Backend modular v1 en ejecución con contratos uniformes y rutas activas para:
  - IdentityAccess (register, login, me, logout).
  - LiveArtBooking (alta de solicitudes de live art).
  - Catalog (listado público y mantenimiento backoffice).
  - OrderManagement (cart, add item, add from catalog, checkout, my orders).
  - BackofficeOps (resumen operativo, listado y actualización de estados de pedidos y eventos).
  - Notifications (feed de notificaciones del usuario y marcado como leído).
  - MediaAssets (subida de archivos, metadata y borrado seguro por ownership/rol).
- Integración operativa: actualizaciones backoffice de estados ahora generan notificación persistida al cliente afectado.
- Documentación de contrato v1 actualizada en app/Server/docs/api-v1-contract.md.
- Cobertura de tests feature ampliada con prueba E2E inter-módulo (catálogo → carrito → checkout → backoffice → notificaciones) y suite en verde con 45 tests y 267 assertions.
- Mapa actual de API v1 verificado con 26 rutas registradas.
- Sprint de SPA frontend v1 completado en modo convivencia con legacy:
  - Nueva app en app/Client/spa con React + TypeScript + Vite + React Router + TanStack Query.
  - Routing funcional por dominios: auth, catalog, cart, live-art, notifications, media-assets y backoffice.
  - Cliente HTTP compartido para API v1 con sesión bearer en localStorage y parser uniforme de envelope success/error.
  - Sistema visual responsive implementado y build validado en verde con npm run build.
  - Checklist de smoke QA manual documentado en app/Client/spa/QA_SMOKE_CHECKLIST.md.
  - Automatización E2E inicial con Playwright implementada en app/Client/spa/e2e (smoke público, auth/cart/live-art, media page y flujo backoffice-notificaciones condicional).
  - Validación E2E por defecto en verde: 3 pruebas pasando y 2 pruebas condicionales en skip (assistant credentials y media upload opt-in).
- Pipeline CI inicial integrado en GitHub Actions:
  - Workflow creado en .github/workflows/ci.yml.
  - Job backend-tests: instala dependencias de Laravel y ejecuta php artisan test.
  - Job spa-build: instala dependencias de la SPA y ejecuta npm run build.
  - Job e2e-smoke: prepara backend SQLite, aplica migraciones, levanta servidor Laravel y ejecuta npm run test:e2e (perfil smoke por defecto).
  - Activación condicional de escenarios E2E avanzados ya cableada por entorno: E2E_ASSISTANT_EMAIL, E2E_ASSISTANT_PASSWORD y E2E_ENABLE_MEDIA_UPLOAD (secrets/vars de GitHub).
  - Job e2e-extended opcional (workflow_dispatch con run_extended_e2e=true): ejecuta suite assistant/media con prerequisites estrictos de credenciales y upload activo.
  - Job prerelease-rollout-gates (workflow_dispatch): exige URL objetivo de rollout (input o var ROLLOUT_SPA_BASE_URL), permite seleccionar rollout_phase (phase1|phase2|phase3|all, default all), valida disponibilidad HTTP + firma HTML de entrada SPA y ejecuta canary E2E.
  - Script operativo de disparo rápido: scripts/run-prerelease-gate.ps1 para lanzar workflow_dispatch por CLI con fase (default all), carril extendido opcional, seguimiento del run (-Watch) y descarga de artefactos (-DownloadArtifacts).
  - Runner local equivalente al gate: scripts/run-prerelease-gate-local.ps1 para entornos sin despliegue (prepara sqlite testing, levanta backend local, ejecuta canary por fase y guarda artefactos en artifacts/local-prerelease).
  - El runner local genera artifacts/local-prerelease/run-summary.md con estado final, duracion, suites ejecutadas y presencia de artefactos.
  - El runner local soporta SeedDemoCatalog para cargar productos demo del catalogo en entorno local de pruebas.
  - Seeder de catalogo demo añadido: app/Server/database/seeders/CatalogDemoSeeder.php.
  - SeedDemoCatalog ahora intenta sembrar tambien el entorno local por defecto (best-effort) para facilitar navegacion manual fuera del perfil testing.
  - Publicación de artefactos de Playwright y log backend para depuración de fallos.
  - Validación local del bloque CI en verde: php artisan test (45/45), npm run build y npm run test:e2e (3 passed, 2 skipped esperados).
  - Validación runner local en verde: powershell -ExecutionPolicy Bypass -File scripts/run-prerelease-gate-local.ps1 -RolloutPhase all => canary 6/6 passed.
  - UX SPA refinada: BrowserRouter ya activa future flags de React Router v7 para eliminar warnings y AuthPage separa estados de carga de login/registro.
  - Verificacion manual local: catalogo SPA visible con productos demo tras ejecutar el runner local con SeedDemoCatalog.
  - Carrito SPA refinado: selector real de catalogo (sin depender de ID manual), validaciones cliente y estados de carga por accion.
  - Live Art SPA refinado: validaciones de fecha/telefono y persistencia de borrador local para evitar perdida de datos.
  - Cliente API SPA endurecido: mejor manejo de errores de red y detalle de validaciones backend en mensajes de UI.
  - Carrito SPA evolucionado: edicion de cantidad por linea, eliminacion de lineas y sugerencia reutilizable de ultima direccion de checkout.
  - Historial de pedidos SPA nuevo: ruta /orders con filtros por estado, paginacion y desglose de lineas por pedido.
  - Formularios clave con feedback por campo: auth, cart y live-art consumen detalles de validacion backend y muestran errores junto al input correspondiente.
  - OrderManagement API v1 ampliado: PATCH/DELETE de lineas de carrito y GET /orders/my con filtros status/per_page.
  - Cobertura backend ampliada: nuevas pruebas feature para update/remove de lineas y filtro de historial (suite global 48 tests, 299 assertions en verde).
  - Iteracion UX+QA: OrdersPage ahora incluye busqueda local por id/estado/direccion/item, chips visuales por estado y detalle de lineas expandible por pedido.
  - Estabilidad E2E: nuevo spec smoke.cart-orders.spec.ts cubre edicion/eliminacion de lineas + checkout + filtro/historial; canary actualizado para incluirlo.
  - End-to-end validado en local: canary actual 5 passed, 2 skipped (los 2 skips corresponden a pruebas de rollout target/phase dependientes de variables dedicadas).
  - QA de validaciones por campo: nuevo spec smoke.validation-feedback.spec.ts verifica errores junto al input en auth (confirmacion password) y checkout (direccion minima).
  - Robustez backend: nuevo test garantiza orden determinista en /orders/my por order_date desc + id desc cuando hay multiples pedidos del mismo dia.
  - Runner local prerelease reforzado: ahora valida presencia de specs canary/validation, ejecuta validation smoke por defecto y exige report index en artefactos.
  - Cart/orders smoke ampliado: paginacion real validada (navegacion 1/2 -> 2/2) y escenario adicional pending->paid->shipped condicionado a credenciales assistant.
  - Historial SPA reforzado: panel de desglose de estados visibles en pagina para lectura operativa rapida.
  - Alineacion CI/local cerrada: workflow prerelease-rollout-gates ahora ejecuta validation smoke y publica outcomes por suite (canary + validation) en GITHUB_STEP_SUMMARY.
  - Runner local ahora exporta resumen estructurado run-summary.json y usa alias npm test:e2e:validation para ejecucion consistente.
  - Se agrega esquema versionado scripts/local-prerelease-summary.schema.json y run-summary.json incorpora schema_version=1.0.0 para integracion automatizada estable.
  - Se agrega scripts/dispatch-prerelease-workflow.ps1 para disparar workflow_dispatch via REST cuando no hay gh CLI disponible en el entorno local.
  - scripts/run-prerelease-gate.ps1 ahora soporta modo auto/gh/rest: fallback REST con token, watch por polling API, descarga de artefactos sin dependencia obligatoria de gh CLI y salida -PassThru con run_id/run_url para automatizacion.
  - Se agrega scripts/inspect-prerelease-run.ps1 para validar por API los outcomes del job prerelease (canary, validation smoke y publicacion de summary) y fallar en CI/manual si algo no queda en success.
  - scripts/run-prerelease-gate.ps1 se corrige para fail-fast en errores REST (sin falso "Workflow dispatched successfully" ante 4xx/5xx) y se documenta diagnostico 404 cuando el workflow ci.yml aun no existe en remoto.
- README principal ahora expone badge de estado del workflow CI para visibilidad operativa continua.
- Sustitución gradual legacy → SPA iniciada con gate de rollout:
  - Configuración central en app/Client/config/config.js para mapear vistas legacy a rutas SPA.
  - Activación controlada por localStorage (fefuart_spa_rollout=1) o query param ?spa=1 / ?spa=0.
  - Destino SPA configurable por localStorage (fefuart_spa_base_url) o query param ?spaBase=... para despliegue progresivo por entorno.
  - Scope por vista habilitado para despliegue progresivo fino: localStorage fefuart_spa_rollout_scope o query param ?spaViews=... .
  - Presets de fases nombradas habilitados: fefuart_spa_rollout_phase o query param ?spaPhase=phase1|phase2|phase3.
  - Vistas services y galeria integradas al gate al cargar config.js.
  - Validación funcional en navegador: index legacy redirige a SPA home cuando rollout está activo.
  - Cobertura E2E incorporada con Playwright: smoke.legacy-rollout.spec.ts valida activación del gate, presets por fase, scope por vista, persistencia de redirección y rollback controlado.
- Hardening incremental en legacy backend:
  - ProductController corrige respuestas 404 mal formadas en update/delete.
  - updateProductById ahora persiste todos los campos ya validados (description/category/subcategory/delivery_time/image_url/stock además de name/price/delivery_type).

Este documento sigue siendo la referencia principal de la recreación desde cero. El siguiente bloque recomendado es ejecutar sustitución gradual de vistas legacy en producción y añadir gates de despliegue progresivo sobre esta base de CI.