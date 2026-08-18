<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StripeWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Throwable;
use UnexpectedValueException;

/**
 * La unica ruta de la API sin sesion.
 *
 * No la protege `auth:sanctum` —Stripe no tiene cuenta aqui— sino la firma
 * del cuerpo. Sin verificarla, este endpoint seria «cualquiera puede
 * declarar pagado cualquier pedido» con un `curl`.
 *
 * Se verifica sobre el **cuerpo crudo**, byte a byte. Basta con que Laravel
 * decodifique y vuelva a serializar el JSON para que la firma deje de
 * cuadrar, y por eso aqui se lee `getContent()` y no `$request->input()`.
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeWebhookService $webhooks): Response|JsonResponse
    {
        $secreto = (string) config('services.stripe.webhook.secret');

        if ($secreto === '') {
            // Sin secreto no hay verificacion posible, y procesar sin
            // verificar seria peor que no procesar. En local lo da
            // `stripe listen`; en produccion, el panel.
            report(new RuntimeException('STRIPE_WEBHOOK_SECRET esta vacio: el webhook no puede verificar la firma.'));

            return response()->json(['message' => 'El webhook no esta configurado.'], 500);
        }

        try {
            $evento = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                $secreto
            );
        } catch (SignatureVerificationException|UnexpectedValueException) {
            // 400 y no 500: la firma no va a mejorar reintentando, y un 4xx
            // le dice a Stripe que no insista. Tampoco se distingue entre
            // «firma mala» y «cuerpo ilegible», para no ir dando pistas.
            return response()->json(['message' => 'Firma no valida.'], 400);
        }

        try {
            $webhooks->procesar($evento);
        } catch (Throwable $e) {
            report($e);

            // 500 a proposito: Stripe reintenta con espera creciente. El
            // motivo ya quedo guardado en `webhook_events.error`; al cliente
            // —que aqui es Stripe— no se le manda la traza (SEC-012).
            return response()->json(['message' => 'No se ha podido procesar el evento.'], 500);
        }

        return response()->noContent();
    }
}
