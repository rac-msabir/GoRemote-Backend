<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Response;

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
        Response::macro('api', function ($data = null, $error = false, $errorMessage = null, $statusCode = 200) {
            return Response::json([
                'status_code'  => $statusCode,
                'error'        => $error,
                'errorMessage' => $errorMessage,
                'data'         => $data,
            ], $statusCode);
         });
    }
}
