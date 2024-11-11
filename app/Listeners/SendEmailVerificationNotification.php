<?php

namespace App\Listeners;

use App\Events\RegisteredUser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendEmailVerificationNotification
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(RegisteredUser $event): void
    {
        $user = $event->user;
        $user->sendEmailVerificationNotification();
    }
}
