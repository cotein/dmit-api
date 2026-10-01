<?php

declare(strict_types=1);

namespace App\Src\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Único dueño de la pregunta "¿sobre qué empresa está operando este request?".
 *
 * Resolución:
 *   1. header X-Company-Id, validado contra las empresas del usuario (403 si no es suya);
 *   2. session('company_id') del guard web, si existe y es suya (compatibilidad legacy);
 *   3. primera empresa del usuario ordenada por id (comportamiento histórico).
 *
 * Devuelve null cuando no hay usuario autenticado (consola, seeders, jobs): en ese
 * caso CompanyScope falla cerrado y quien escribe debe pasar company_id explícito.
 */
final class CurrentCompany
{
    public const HEADER = 'X-Company-Id';

    private ?int $resolved = null;

    private bool $done = false;

    public function __construct(private readonly Request $request)
    {
    }

    public function id(): ?int
    {
        // Sólo se memoiza un resultado positivo: si algo resuelve la empresa antes
        // de que corra el middleware de auth, no queremos que ese null quede pegado.
        if ($this->done && $this->resolved !== null) {
            return $this->resolved;
        }

        $this->done = true;

        $user = Auth::user();

        if (! $user) {
            return $this->resolved = null;
        }

        $header = (int) $this->request->header(self::HEADER, 0);

        if ($header > 0) {
            abort_unless(
                $user->companies()->whereKey($header)->exists(),
                403,
                'No tenés acceso a esa empresa.'
            );

            return $this->resolved = $header;
        }

        $sessionCompanyId = 0;

        if ($this->request->hasSession()) {
            $sessionCompanyId = (int) $this->request->session()->get('company_id', 0);
        }

        if ($sessionCompanyId > 0 && $user->companies()->whereKey($sessionCompanyId)->exists()) {
            return $this->resolved = $sessionCompanyId;
        }

        $first = (int) $user->companies()->orderBy('companies.id')->value('companies.id');

        return $this->resolved = $first > 0 ? $first : null;
    }

    public function requireId(): int
    {
        return $this->id() ?? abort(403, 'El usuario no tiene una empresa activa.');
    }
}
