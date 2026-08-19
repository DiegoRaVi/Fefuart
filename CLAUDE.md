# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Qué es este proyecto

Fefuart es la web del negocio de Felicitas Varela, artista de LiveArt para bodas y eventos. Cuatro servicios: **Live Art** (espectáculo en directo, presupuesto a medida), **Dibujo por encargo** (retratos a partir de fotos), **Letras infantiles** y **Ramos dibujados**. Los tres últimos son productos de catálogo; todos se dibujan a partir de una foto que sube el cliente.

**El repositorio está en transición.** La v1 (`master`) fue auditada el 2026-08-11 y se está reconstruyendo como v2 sobre `develop`. Antes de tocar nada:

- **`docs/AUDIT.md`** — 14 hallazgos de seguridad (SEC-001…014), 8 errores funcionales (BUG-001…008) y la deuda técnica, con el estado de cada uno.
- **`docs/V2-ROADMAP.md`** — **la fuente de verdad**: 28 decisiones arquitectónicas (D1–D28), 20 reglas de negocio (N1–N20), el esquema de base de datos objetivo, el diseño de la API y las 8 fases con su estado.

- **`docs/PAGOS.md`** — el recorrido completo de un cobro: SPA, Laravel, Stripe, qué datos viajan y qué pasa en cada caso. Léelo antes de tocar nada de pagos, señales o devoluciones.

Léelos antes de proponer arquitectura o de tocar precios, pedidos o eventos. Muchas decisiones que parecen abiertas ya están cerradas ahí.

## Estructura

```
app/Server/       Laravel 12 — API.
app/Client/spa/   SPA de React + TypeScript + Vite. Aquí se trabaja.
app/Client/img/   Obra de Felicitas (207 MB). La Galeria de la SPA todavia no existe.
docs/             AUDIT.md + V2-ROADMAP.md + PAGOS.md. Documentos vivos.
```

## Comandos

```bash
# Backend (siempre desde app/Server)
php artisan serve --host=127.0.0.1 --port=8000
php artisan test                              # Pest
php artisan test --filter=NombreDelTest       # un solo test
php artisan test tests/Feature/AuthTest.php   # un solo fichero
php artisan route:list                        # rutas y middleware reales
php artisan migrate:status
composer audit                                # el lock se dejó limpio el 2026-08-12

# SPA (siempre desde app/Client/spa)
npm run dev          # Vite en :5173 — strictPort, ver la trampa de abajo
npm test             # Vitest (unitarios y de componente)
npm run test:e2e     # Playwright: levanta backend, SPA y Mailpit solo
npm run lint         # ESLint; react/no-danger cierra SEC-005
npm run typecheck    # tsc -b --noEmit
npm run build

# Base de datos (MariaDB 10.4 vía XAMPP, BD `fefuart`, root sin contraseña)
"/c/xampp/mysql/bin/mysql" -u root fefuart -e "SHOW TABLES;"
"/c/xampp/mysql/bin/mysqldump" -u root fefuart > backup.sql

# Correo local — obligatorio para recuperación de contraseña y verificación de email
/c/xampp/mailpit/mailpit.exe --listen 127.0.0.1:8025 --smtp 127.0.0.1:1025
# Bandeja: http://127.0.0.1:8025  ·  API: http://127.0.0.1:8025/api/v1/messages

# Cola — obligatoria desde la Fase 6: sin worker los avisos se quedan en `jobs`
php artisan queue:work --tries=3          # se queda abierto
php artisan queue:work --stop-when-empty  # vacía lo pendiente y sale
```

## Trampas del entorno local

**Apache sirve todo el repositorio.** El `DocumentRoot` de XAMPP es `C:/xampp/htdocs`, la carpeta padre del proyecto. Sin protección, `.env`, `storage/logs/`, los `.php` en texto plano y `.git/` son descargables desde la LAN (hallazgo SEC-002, verificado con `curl`). Lo contienen tres ficheros: `.htaccess` en la raíz, `app/Server/.htaccess` (deniega el backend) y `app/Server/public/.htaccess` (reabre solo `public/`). **No los borres ni los muevas.** La solución definitiva —un VirtualHost apuntando a `app/Server/public`— es de la Fase 8.

**El backend nunca se prueba por Apache.** Va por `php artisan serve` en `127.0.0.1:8000`. Apache solo sirve `app/Client`.

**El frontend legacy ya no existe.** Se retiró en la Fase 7 (D15), como estaba previsto: dejó de funcionar en la Fase 1 al sustituir JWT por Sanctum, y mantener la API de v1 en paralelo habría obligado a conservar sus cinco vulnerabilidades críticas. Si necesitas ver cómo era un flujo, está en el histórico de git. Lo que **sí** se conservó es `app/Client/img/`: son las fotos reales de la artista, y la Galería de la SPA todavía no está construida.

**Los E2E corren contra su propia base.** `npm run test:e2e` arranca Laravel con `APP_ENV=e2e`, que carga `.env.e2e` y apunta a `fefuart_e2e`, y la resiembra con `migrate:fresh`. Esa variable es lo único que separa las dos bases: si la pierdes, vacías `fefuart`. Ningún recorrido llama a la API de Stripe — el webhook se firma en el propio test con el secreto de `.env.e2e`.

**La SPA tiene que arrancar en el puerto 5173.** `SANCTUM_STATEFUL_DOMAINS` declara `localhost:5173`, y solo desde un origen declarado arranca la sesión. Por eso `vite.config.ts` lleva `strictPort: true`: si Vite cayera a 5174, el login dejaría de funcionar con un error que no dice por qué. Por lo mismo, para probar la API a mano hay que ir por `http://localhost:8000` con `Referer: http://localhost:5173/` — con `127.0.0.1` la cookie no viaja, porque `SESSION_DOMAIN` es `localhost`.

## Arquitectura de v2

Laravel estándar. **Sin repositorios ni DDD por módulos**: para un CRUD de este tamaño Eloquent ya es la capa de persistencia, y envolverlo en interfaces es la sobrearquitectura que se descartó explícitamente (la rama `autotest` lo intentó; ver más abajo). Services solo donde hay lógica real: `PricingService`, `CartService`, `CheckoutService`, `QuoteService`, `StripePaymentService`.

Invariantes que no se negocian, cada una atada a un hallazgo de la auditoría:

- **El precio nunca llega del cliente.** `POST /api/cart/items` recibe `product_id` + `variant_id` + `shipping_method_id`; el servidor calcula (SEC-006). En v1 el navegador enviaba el precio.
- **Toda ruta sobre un recurso propio pasa por Policy**, nunca por comparación inline (SEC-003/004/008/009).
- **`role` jamás es asignable en masa** (SEC-001).
- **Rutas sin prefijo de versión**: `/api/…`, no `/api/v1/…` (D13).
- **Un aviso se encola dentro del mismo `if` que hace el cambio de estado** (D31), nunca al principio del manejador. Es lo único que impide que un reenvío de Stripe mande un segundo correo: la garantía es «al menos una vez», y por D30 una entrega puede fallar después de guardar el cobro.
- Autenticación por **Sanctum con cookies HttpOnly**, no JWT (D2). React y Laravel deben ser same-site → proxy de Vite hacia `/api`.
- **Prohibido `dangerouslySetInnerHTML`** en React (SEC-005).

Modelo de datos: el catálogo (`products` + `product_variants`, con el precio) está separado del encargo concreto (`order_items`, con la descripción y la foto de referencia). Cada dibujo es único; lo que deja de serlo es el precio. `quantity` son copias de la misma lámina, no encargos distintos, y no multiplica el precio completo (N3, N4). El envío se cobra una vez por pedido, no por línea (N5).

## Git

Se trabaja en **`develop`**. `master` es la v1 congelada como referencia. Ramas `feature/…`, `fix/…`, `refactor/…`, `docs/…`. Commits pequeños, de un solo tema; no mezclar refactor con funcionalidad ni con cambios de esquema. Nunca `reset --hard`, ni borrado de ramas, ni force push.

### Formato de los commits

```
[Tipo]: resumen del commit
```

`[Fix]`, `[Feat]`, `[Docs]`, `[Chore]`, `[Refactor]`. El resumen en una línea; el cuerpo, si hace falta, explica el porqué y cita el hallazgo que cierra (`SEC-001`, `BUG-004`…).

**Prohibido firmar los commits.** Nada de `Co-Authored-By`, `Generated with`, ni ninguna otra atribución al pie. El historial es del autor del repositorio.

**La rama `autotest`** contiene un intento previo de v2 (~16.300 líneas: backend modular DDD, SPA React/TS, CI) que se descartó (D1). Está etiquetada como `archive/v2-autonomous-attempt`. **Déjala intacta** — no se mergea, no se borra, y no se usa como referencia de arquitectura.

## Testing

**Cada hallazgo de la auditoría se cierra con un test que lo reproduce.** Es lo que evita repetir la auditoría dentro de un año: al arreglar SEC-001, escribe el test que envía `"role":"admin"` en el registro y comprueba que se ignora. Al añadir una Policy, escribe el IDOR que bloquea.

Cobertura alta en Services, Policies y endpoints; no se persigue un porcentaje global.

**Los E2E prueban lo que los unitarios no pueden.** No son una segunda capa de lo mismo: cubren el camino entero, que es donde vive lo que nadie ve montando una mitad por separado. En la Fase 7 destaparon cinco fallos reales, dos de ellos vivos desde la Fase 1 —el registro no enviaba el correo de verificación y su enlace daba 500 al abrirlo desde el buzón—. Cuando arregles algo que encuentre un E2E, la regresión va **también** en Pest o Vitest: el recorrido dice que está roto, el test unitario dice por qué.
