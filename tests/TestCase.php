<?php

namespace Citricguy\PostmarkWebhooks\Tests;

use Citricguy\PostmarkWebhooks\PostmarkWebhooksServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Get package providers.
     *
     * @param  Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            PostmarkWebhooksServiceProvider::class,
        ];
    }
}
