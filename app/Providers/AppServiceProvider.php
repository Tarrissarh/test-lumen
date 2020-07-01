<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Mail\MailServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(
            'mailer',
            function ($app) {
                $app->configure('services');

                return $app->loadComponent('mail', MailServiceProvider::class, 'mailer');
            }
        );
    }
}
