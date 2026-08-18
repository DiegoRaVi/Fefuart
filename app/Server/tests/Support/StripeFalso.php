<?php

namespace Tests\Support;

use RuntimeException;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

/**
 * Stripe, sin red.
 *
 * Se sustituye el cliente HTTP del SDK, no el `StripeClient`. La diferencia
 * importa: asi los tests atraviesan la serializacion real de parametros y la
 * hidratacion real de objetos, y se puede afirmar sobre **lo que se envio**.
 * Es lo que permite comprobar cosas como que nunca mandamos
 * `payment_method_types` o que el importe de la linea es el de N4 y no el
 * unitario por la cantidad.
 *
 * Tambien es la red de seguridad: `Tests\TestCase` instala uno de estos en
 * cada test, de modo que ninguna prueba puede salir a internet por descuido.
 */
class StripeFalso implements ClientInterface
{
    /** @var list<array{metodo: string, url: string, params: array<string, mixed>, headers: list<string>}> */
    public array $peticiones = [];

    /** @var list<array{cuerpo: array<string, mixed>, codigo: int}> */
    private array $respuestas = [];

    /**
     * Instala el doble y lo devuelve. El SDK guarda el cliente en estatico,
     * asi que hay que reponerlo en cada test.
     */
    public static function instalar(): self
    {
        $falso = new self;
        ApiRequestor::setHttpClient($falso);

        return $falso;
    }

    /** @param  array<string, mixed>  $cuerpo */
    public function responde(array $cuerpo, int $codigo = 200): self
    {
        $this->respuestas[] = ['cuerpo' => $cuerpo, 'codigo' => $codigo];

        return $this;
    }

    /**
     * Una sesion de Checkout como la devuelve Stripe, con lo que de verdad
     * leemos de ella.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function sesion(array $extra = []): array
    {
        return [
            'id' => 'cs_test_'.bin2hex(random_bytes(8)),
            'object' => 'checkout.session',
            'status' => 'open',
            'payment_status' => 'unpaid',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_falsa',
            'amount_total' => 4500,
            'currency' => 'eur',
            'payment_intent' => null,
            'mode' => 'payment',
            ...$extra,
        ];
    }

    /** @return array{metodo: string, url: string, params: array<string, mixed>, headers: list<string>} */
    public function ultima(): array
    {
        if ($this->peticiones === []) {
            throw new RuntimeException('No se ha hecho ninguna peticion a Stripe.');
        }

        return $this->peticiones[array_key_last($this->peticiones)];
    }

    /** Las cabeceras de una peticion, ya partidas en clave y valor. */
    public function cabecerasDe(int $indice = -1): array
    {
        $peticion = $indice < 0 ? $this->ultima() : $this->peticiones[$indice];
        $cabeceras = [];

        foreach ($peticion['headers'] as $linea) {
            [$clave, $valor] = array_pad(explode(':', (string) $linea, 2), 2, '');
            $cabeceras[strtolower(trim($clave))] = trim($valor);
        }

        return $cabeceras;
    }

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->peticiones[] = [
            'metodo' => (string) $method,
            'url' => (string) $absUrl,
            'params' => $params,
            'headers' => array_values($headers),
        ];

        if ($this->respuestas === []) {
            throw new RuntimeException(
                "El test llamo a {$method} {$absUrl} sin haber preparado una respuesta."
            );
        }

        $respuesta = array_shift($this->respuestas);

        return [json_encode($respuesta['cuerpo']), $respuesta['codigo'], []];
    }
}
