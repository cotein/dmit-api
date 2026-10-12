<?php

declare(strict_types=1);

namespace App\Src\Services\Plans;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Quién puede cambiar el plan de una empresa.
 *
 * REGLA DE HOY: administra el usuario más antiguo de la empresa, que es quien la
 * creó. El aviso del tope lo ve cualquiera que factura, pero el cambio efectivo lo
 * hace esta persona; si otro pide el cambio, el pedido queda REGISTRADO para el
 * dueño y no cambia nada.
 *
 * POR QUÉ NO SE USAN LOS ROLES: los roles del proyecto son globales del usuario y no
 * pueden expresar "administra ESTA empresa" (la tabla model_has_roles no tiene
 * company_id), y además hoy ninguna cuenta tiene roles asignados. Inventar un rol
 * nuevo para esto obligaría a cargarlo a mano en cada empresa existente y dejaría a
 * todos sin poder cambiar de plan hasta que alguien lo haga.
 *
 * Es la única función de esta decisión y está aislada a propósito: el día que el
 * producto defina permisos por empresa, se cambia acá y nada más.
 */
final class PlanAudience
{
    public function canManage(?User $user, int $companyId): bool
    {
        if ($user === null) {
            return false;
        }

        $creador = DB::table('company_user')
            ->where('company_id', $companyId)
            ->orderBy('id')
            ->value('user_id');

        return $creador !== null && (int) $creador === (int) $user->id;
    }
}
