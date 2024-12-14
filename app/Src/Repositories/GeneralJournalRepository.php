<?php

namespace App\Src\Repositories;

use Exception;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\GeneralJournal;

class GeneralJournalRepository
{

    /**
     * Method addDebito
     *
     * @param $uuid $uuid [explicite description]
     * @param $date $date [explicite description]
     * @param $description $description [explicite description]
     * @param $debited_account_id $debited_account_id [explicite description]
     * @param $amount $amount [explicite description]
     * @param $company_id $company_id [explicite description]
     *
     * @return void
     */
    public function addDebito($uuid, $date, $description, $debited_account_id, $amount, $company_id)
    {
        $gj = new GeneralJournal();

        $gj->uuid = $uuid;

        $gj->date = $date;

        $gj->referencia = 'DEBE';

        $gj->description = $description;

        $gj->debited_account_id = $debited_account_id;

        $gj->amount = $amount;

        $gj->company_id = $company_id;

        $gj->user_id = auth()->user()->id;

        $gj->save();
    }

    /**
     * Method addCredito
     *
     * @param $uuid $uuid [explicite description]
     * @param $date $date [explicite description]
     * @param $description $description [explicite description]
     * @param $credited_account_id $credited_account_id [explicite description]
     * @param $amount $amount [explicite description]
     * @param $company_id $company_id [explicite description]
     *
     * @return void
     */
    public function addCredito($uuid, $date, $description, $credited_account_id, $amount, $company_id)
    {
        $gj = new GeneralJournal();

        $gj->uuid = $uuid;

        $gj->date = $date;

        $gj->referencia = 'HABER';

        $gj->description = $description;

        $gj->credited_account_id = $credited_account_id;

        $gj->amount = $amount;

        $gj->company_id = $company_id;

        $gj->user_id = auth()->user()->id;

        $gj->save();
    }
}
