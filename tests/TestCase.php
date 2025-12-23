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
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            PostmarkWebhooksServiceProvider::class,
        ];
    }
}
