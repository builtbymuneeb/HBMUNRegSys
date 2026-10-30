<?php

use App\Actions\Inbox\FetchInbox;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('inbox:fetch', function (FetchInbox $fetchInbox) {
    $new = $fetchInbox->fetch(FetchInbox::mailbox());

    $this->info(trans_choice(':count new message|:count new messages', $new));
})->purpose('Copy new mail from the contact@ mailbox into the staff inbox');

Schedule::command('inbox:fetch')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->when(fn () => config('mail.inbox.enabled'));
