<?php

namespace App\Src\Helpers;

use Spatie\Activitylog\Models\Activity;

class ActivityLog
{
    /**
     * Method save
     *
     * @param $data $data [explicite description]
     * $data['log_name'];
     * $data['description'];
     * $data['subject_type'];
     * $data['subject_id'];
     * $data['causer_type'];
     * $data['causer_id'];
     * $data['company_id'];
     * $data['properties'];
     * $data['batch_uuid'];
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
