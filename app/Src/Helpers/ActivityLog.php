<?php

namespace App\Src\Helpers;

use Spatie\Activitylog\Models\Activity;

class ActivityLog
{
    /**
     * Guarda una nueva actividad en la base de datos.
     *
     * @param array $data Los datos de la actividad. Debe contener las siguientes claves:
     *                    'log_name' => (string) El nombre del log.
     *                    'description' => (string) La descripción de la actividad.
     *                    'subject_type' => (string) El tipo del sujeto de la actividad.
     *                    'subject_id' => (int) El ID del sujeto de la actividad.
     *                    'causer_type' => (string) El tipo del causante de la actividad.
     *                    'causer_id' => (int) El ID del causante de la actividad.
     *                    'company_id' => (int) El ID de la compañía asociada a la actividad.
     *                    'properties' => (array) Las propiedades adicionales de la actividad.
     *                    'batch_uuid' => (string) El UUID del lote al que pertenece la actividad.
     *
     * @return void
     */
    public static function save($data): void
    {
        $activity = new Activity();
        $activity->log_name = $data['log_name'];
        $activity->description = $data['description'];
        $activity->subject_type = $data['subject_type'];
        $activity->subject_id = $data['subject_id'];
        $activity->causer_type = $data['causer_type'];
        $activity->causer_id = $data['causer_id'];
        $activity->company_id = $data['company_id'];
        $activity->properties = $data['properties'];
        $activity->batch_uuid = $data['batch_uuid'];

        $activity->save();
    }
}
