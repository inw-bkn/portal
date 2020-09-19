<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class PatientDataProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('App\Contracts\PatientDataAPI', config('app.PATIENT_DATA_PROVIDER'));
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
