<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Src\Services\MercadoPago\MercadoPagoOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Vuelta del navegador después de autorizar en Mercado Pago. RUTA PÚBLICA.
 *
 * No puede pedir auth:api porque es el navegador del usuario volviendo de MP, y
 * la app autentica con Bearer (no con sesión). Por eso la empresa y el usuario
 * viajan FIRMADOS en el `state`, que es lo que impide conectar la cuenta de uno
 * a la empresa de otro.
 */
class MercadoPagoCallbackController extends Controller
{
    public function __construct(private MercadoPagoOAuthService $oauth)
    {
    }

    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        $code = $request->query('code');
        $state = $request->query('state');
        $error = $request->query('error');

        if ($error) {
            return $this->fail('Mercado Pago rechazó la autorización: ' . $error);
        }

        if (empty($code) || empty($state)) {
            return $this->fail('La respuesta de Mercado Pago vino incompleta.');
        }

        try {
            $datos = $this->oauth->verifyState((string) $state);
            $this->oauth->connect($datos['company_id'], (string) $code);
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Mercado Pago: falló la conexión de la cuenta', ['error' => $e->getMessage()]);

            return $this->fail('No se pudo completar la conexión con Mercado Pago.');
        }

        $destino = config('mercadopago.back_url');

        if (empty($destino)) {
            return response()->json(['message' => 'Cuenta de Mercado Pago conectada.']);
        }

        return redirect()->away($destino . (str_contains($destino, '?') ? '&' : '?') . 'mp=connected');
    }

    private function fail(string $mensaje): JsonResponse|RedirectResponse
    {
        $destino = config('mercadopago.back_url');

        if (empty($destino)) {
            return response()->json(['message' => $mensaje], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return redirect()->away($destino . (str_contains($destino, '?') ? '&' : '?') . 'mp=error&message=' . urlencode($mensaje));
    }
}
