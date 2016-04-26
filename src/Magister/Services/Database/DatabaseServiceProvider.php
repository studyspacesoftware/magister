<?php

namespace Magister\Services\Database;

use Magister\Services\Database\Elegant\Model;
use Magister\Services\Support\ServiceProvider;

/**
 * Class DatabaseServiceProvider
 * @package Magister
 */
class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Perform booting of services.
     *
     * @return void
     */
    public function boot()
    {
        Model::setEventDispatcher($this->app['events']);
        
        Model::setConnectionResolver($this->app['db']);
    }
    
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('db', function ($app) {
            return new DatabaseManager($app);
        });
    }
}
