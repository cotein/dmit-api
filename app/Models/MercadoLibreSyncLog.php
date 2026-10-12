<?php

namespace App\Models;

use App\Src\Traits\BelongsToCompanyTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Una corrida de sincronización, con su resultado.
 *
 * Existe para que el usuario pueda ver en pantalla qué pasó ("importé 240
 * publicaciones, 12 quedaron sin vincular, 3 fallaron") en lugar de tener que
 * mirar la salida de un comando de consola.
 */
class MercadoLibreSyncLog extends Model
{
    use HasFactory, BelongsToCompanyTrait;

    public const TYPE_IMPORT = 'import';
    public const TYPE_PUSH = 'push';
    public const TYPE_ORDERS = 'orders';
    public const TYPE_WEBHOOK = 'webhook';
    public const TYPE_MATCH = 'match';
    public const TYPE_RECONCILE = 'reconcile';
    public const TYPE_PUBLISH = 'publish';

    public const STATUS_RUNNING = 'running';
    public const STATUS_OK = 'ok';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_FAILED = 'failed';

    protected $table = 'mercadolibre_sync_logs';

    protected $fillable = [
        'company_id',
        'mercadolibre_account_id',
        'type',
        'status',
        'started_at',
        'finished_at',
        'duration_ms',
        'processed',
        'created',
        'updated',
        'skipped',
        'failed',
        'message',
        'errors',
        'context',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'errors' => 'array',
        'context' => 'array',
    ];

    /**
     * Cierra la corrida. El estado se deduce: si falló todo es `failed`, si
     * falló algo es `partial` y si no, `ok`. Así la pantalla no tiene que
     * interpretar contadores.
     */
    public function close(?string $message = null): self
    {
        $this->finished_at = now();
        $this->duration_ms = $this->started_at
            ? (int) $this->started_at->diffInMilliseconds($this->finished_at)
            : null;

        $this->status = match (true) {
            $this->failed > 0 && $this->processed === 0 => self::STATUS_FAILED,
            $this->failed > 0 => self::STATUS_PARTIAL,
            default => self::STATUS_OK,
        };

        if ($message !== null) {
            $this->message = mb_substr($message, 0, 255);
        }

        $this->save();

        return $this;
    }

    /**
     * Guarda un error acotado. No se guarda el log entero: sólo los primeros
     * para poder mostrarlos en pantalla sin llenar la base.
     */
    public function addError(string $error): void
    {
        $errores = $this->errors ?? [];

        if (count($errores) >= 20) {
            return;
        }

        $errores[] = mb_substr($error, 0, 500);
        $this->errors = $errores;
    }
}
