<?php

namespace Clarification\MailDrivers\Sendgrid;

use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Mail\TransportManager;
use Illuminate\Support\ServiceProvider;
use Clarification\MailDrivers\Sendgrid\Transport\SendgridTransport;

class SendgridServiceProvider extends ServiceProvider
{
    /**
     * After register is called on all service providers, then boot is called
     */
    public function boot()
    {
        //
    }

    /**
     * Register is called on all service providers first.
     *
     * We must register the extension before anything tries to use the mailing functionality.
     * None of the closures are executed until someone tries to send an email.
     *
     * This will register a closure which will be run when 'swift.transport' (the transport manager) is first resolved.
     * Then we extend the transport manager, by adding the spark post transport object as the 'sparkpost' driver.
     */
    public function register()
    {
        $this->app->extend('swift.transport', function(TransportManager $manager) {
            $manager->extend('sendgrid', function() {
                $config = $this->app['config']->get('services.sendgrid', []);
                $client = new Client(Arr::get($config, 'guzzle', []));
                return new SendgridTransport($client, $config['api_key']);
            });
            return $manager;
        });
    }
}
