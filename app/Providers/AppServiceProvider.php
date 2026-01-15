<?php

namespace App\Providers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
            Schema::blueprintResolver(function ($table, $callback) {
                return new class($table, $callback) extends Blueprint {
                    public function timestamps($precision = 7)
                    {
                        // Força datetime2 no SQL Server
                        $this->addColumn('datetime2', 'created_at', ['precision' => $precision, 'nullable' => true]);
                        $this->addColumn('datetime2', 'updated_at', ['precision' => $precision, 'nullable' => true]);
                    }
                };
            });

        Schema::defaultStringLength(191);
    }

}
