<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Src\Services\MercadoLibre\MercadoLibreItemSyncService;
use App\Src\Services\MercadoLibre\MercadoLibreOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Vuelta del OAuth de MercadoLibre.
 *
 * Es una carga COMPLETA de página: el navegador sale a autorizar a MercadoLibre
 * y vuelve acá. Por eso no puede llevar auth:api; la empresa y el usuario viajan
 * firmados en el `state`.
 *
 * Al terminar redirige al frontend con ?ml=connected o ?ml=error, que es lo que
 * el guard del router usa para avisar. Misma convención que Mercado Pago: la
 * vuelta NO puede quedar como una respuesta JSON de la API.
 */
class MercadoLibreCallbackController extends Controller
{
    public function __construct(
        private MercadoLibreOAuthService $oauth,
        private MercadoLibreItemSyncService $itemSync,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return $this->volverConError((string) $request->string('error_description', 'Autorización rechazada.'));
        }

        $code = (string) $request->string('code');
        $state = (string) $request->string('state');

        if ($code === '' || $state === '') {
            return $this->volverConError('MercadoLibre no devolvió el código de autorización.');
        }

        try {
            $companyId = $this->oauth->verifyState($state)['company_id'];
            $cuenta = $this->oauth->connect($companyId, $state, $code);
        } catch (\Throwable $e) {
            Log::warning('MercadoLibre: falló la conexión de la cuenta', ['error' => $e->getMessage()]);

            return $this->volverConError($e->getMessage());
        }

        // La primera importación se dispara sola: conectar la cuenta y no ver
        // nada sería una pantalla vacía sin explicación. Si falla, la cuenta
        // queda conectada igual y el error queda en el historial de la corrida.
        try {
            $this->itemSync->import($cuenta);
        } catch (\Throwable $e) {
            Log::warning('MercadoLibre: la importación inicial falló, la cuenta quedó conectada', [
                'company_id' => $cuenta->company_id,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->volverCon('?ml=connected');
    }

    private function volverCon(string $consulta): RedirectResponse
    {
        $destino = (string) config('mercadolibre.back_url');

        return redirect()->away($destino . (str_contains($destino, '?') ? '&' : '?') . ltrim($consulta, '?'));
    }

    private function volverConError(string $mensaje): RedirectResponse
    {
        return $this->volverCon('?ml=error&message=' . urlencode($mensaje));
    }
}
