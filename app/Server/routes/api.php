<?php

/*
|--------------------------------------------------------------------------
| API
|--------------------------------------------------------------------------
|
| Las 40 rutas de v1 se retiran aqui. Dependian del guard `api` de JWT, que
| ya no existe, y arrastraban cinco fallos criticos de autorizacion
| (SEC-001, SEC-003, SEC-004, SEC-008, SEC-009) mas tres rutas que apuntaban
| a metodos inexistentes (BUG-001, BUG-002, BUG-003).
|
| No se mantienen en paralelo de forma deliberada (D15): conservarlas
| funcionando obligaria a conservar tambien sus vulnerabilidades. Los
| controllers de v1 siguen en el arbol como referencia hasta que cada fase
| reconstruya su area.
|
| Los endpoints de v2 se anaden por fases y sin prefijo de version (D13).
|
*/
