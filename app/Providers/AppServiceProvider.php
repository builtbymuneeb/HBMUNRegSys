<?php

namespace App\Providers;

use App\Listeners\RecordMail;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Minishlink\WebPush\WebPush;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // On Windows, `php artisan serve` drops TEMP/TMP, so PHP cannot store uploaded files.
        ServeCommand::$passthroughVariables = [...ServeCommand::$passthroughVariables, 'TEMP', 'TMP'];

        $this->app->bind(WebPush::class, fn (): WebPush => new WebPush(['VAPID' => [
            'subject' => config('services.webpush.subject'),
            'publicKey' => config('services.webpush.public_key'),
            'privateKey' => config('services.webpush.private_key'),
        ]]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::define('manage-staff', fn (User $user): bool => $user->isAdmin());
        Gate::define('manage-conference', fn (User $user): bool => $user->canManageConference());
        Gate::define('manage-ambassadors', fn (User $user): bool => $user->canManageAmbassadors());

        Event::listen(MessageSent::class, [RecordMail::class, 'sent']);
        Event::listen(JobFailed::class, [RecordMail::class, 'failed']);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // 8+ characters with upper and lower case letters and a number; symbols are optional.
        Password::defaults(fn (): Password => app()->isProduction()
            ? Password::min(8)->mixedCase()->numbers()->uncompromised()
            : Password::min(8)->mixedCase()->numbers(),
        );
    }
}
