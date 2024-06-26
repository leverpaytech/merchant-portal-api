<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        Passport::tokensCan([
            'merchant' => 'Merchant permission',
            'admin' => 'Admin permission',
        ]);

        Passport::tokensExpireIn(now()->addDays(2));
        Passport::refreshTokensExpireIn(now()->addDays(2));
        Passport::personalAccessTokensExpireIn(now()->addDays(2));

        // Set token expiration times
        // Passport::tokensExpireIn(now()->addMinutes(5));
        // Passport::refreshTokensExpireIn(now()->addMinutes(5));
        // Passport::personalAccessTokensExpireIn(now()->addMinutes(5));

        $this->registerPolicies();
    }
}
