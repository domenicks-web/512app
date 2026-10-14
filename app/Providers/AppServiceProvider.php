<?php

namespace App\Providers;

use App\Mail\ResetPasswordMail;
use App\Mail\VerifyEmailMail;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::define('admin', fn (User $user): bool => $user->is_admin === true);

        VerifyEmail::toMailUsing(fn (User $user, string $url): VerifyEmailMail => (new VerifyEmailMail(
            $user,
            $url,
            (int) config('auth.verification.expire', 60),
        ))->to($user->email));

        ResetPassword::toMailUsing(fn (User $user, string $token): ResetPasswordMail => (new ResetPasswordMail(
            $user,
            route('password.reset.link', ['token' => $token, 'email' => $user->email]),
            (int) config('auth.passwords.users.expire'),
        ))->to($user->email));
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

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
