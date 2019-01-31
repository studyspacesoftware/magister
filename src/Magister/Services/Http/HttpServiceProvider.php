<?php

namespace Magister\Services\Http;

use GuzzleHttp\Client;
use Magister\Services\Support\ServiceProvider;

/**
 * Class HttpServiceProvider.
 */
class HttpServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        $this->registerGuzzle();
    }

    /**
     * Register the Guzzle driver.
     *
     * @return void
     */
    protected function registerGuzzle()
    {
        $this->app->singleton('http', function ($app) {
            $client = new Client([
                'base_uri' => "https://{$app['school']}.{$app['apidomain']}/api/",
                'headers' => [
                    'Authorization' => 'Bearer '.$app['apikey'],
                ],
                'cookies' => new SessionCookieJar($app['cookie']),
            ]);

            return $client;
        });
    }
}
