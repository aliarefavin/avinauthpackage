<?php
namespace AliArefAvin\AvinAuthPackage;

use AliArefAvin\AvinAuthPackage\Contracts\AvinAuthInterface;
use AliArefAvin\AvinAuthPackage\Services\AvinAuthService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class AvinAuthServiceProvider extends ServiceProvider
{


    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/config/avinauthconfig.php', 'avinauthconfig');
        $this->app->bind(AvinAuthInterface::class, AvinAuthService::class);

    }
    public function boot()
    {
        // Publish config
        // Publish migrations
        $this->publishes([
            __DIR__.'/config/avinauthconfig.php' => config_path('avinauthconfig'),
            __DIR__.'/database/migrations/' => database_path('migrations'),
        ], 'avinauth-config');


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