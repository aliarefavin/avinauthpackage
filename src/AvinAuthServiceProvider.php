<?php

use App\Services\Auth\AvinAuthService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;

class AvinAuthServiceProvider
{


    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/config/avinauthconfig.php', 'avinauthconfig.php');
    }
    public function boot()
    {
        // Publish config
        // Publish migrations
        $this->publishes([
            __DIR__.'/config/avinauthconfig.php' => config_path('avinauthconfig.php'),
            __DIR__.'/database/migrations/' => database_path('migrations'),
        ], 'avinauthpackage');


        // Load migrations automatically (optional)
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');


        Validator::extend('avin_verify', function ($attribute, $value, $parameters = [], $validator = null) {
            $success = false;
            $message = '';
            if (isset($validator->getData()[$parameters[0] ?? 'receiver']) && !empty($validator->getData()[$parameters[0]])) {
                $result = app(AvinAuthService::class)->validate($validator->getData()[$parameters[0] ?? 'receiver'], $value);
                $message = $result['message'];
                $success = $result['success'];
            }
            App::singleton('avin_verify_validation_message', function () use ($message) {
                return $message;
            });
            return $success;
        });
        Validator::replacer('avin_verify', function ($message) {
            return app('avin_verify_validation_message');
        });

    }
}