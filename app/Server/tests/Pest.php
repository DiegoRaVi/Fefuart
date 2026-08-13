<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations y helpers
|--------------------------------------------------------------------------
|
| Aqui van las expectativas propias y los helpers globales de los tests. De
| momento no hace falta ninguno: los helpers que existen viven en el fichero
| que los usa, que es donde se leen.
|
| Se retiran los de plantilla (`toBeOne`, `something()`) junto con los dos
| ExampleTest que la auditoria contaba como la totalidad de la cobertura.
|
*/
