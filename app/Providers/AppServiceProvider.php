<?php

namespace App\Providers;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\User;
use App\Policies\EmployeePolicy;
use App\Policies\PayrollRunPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        // Register model policies
        Gate::policy(Employee::class,   EmployeePolicy::class);
        Gate::policy(PayrollRun::class, PayrollRunPolicy::class);
        Gate::policy(User::class,       UserPolicy::class);
    }
}
