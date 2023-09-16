<?php

namespace Citricguy\PostmarkWebhooks\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyPostmarkWebhookIPAddresses
{

    /**
     * Array of IP addresses from Postmark that are white listed.
     *
     * @see https://postmarkapp.com/support/article/800-ips-for-firewalls#webhooks
     *
     * @var array
     */
    private array $webhook_ip_addresses = [
        '18.217.206.57',
        '3.134.147.250',
        '50.31.156.6',
        '50.31.156.77',
    ];

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (app()->environment('local')) {
            // Bypass the middleware and proceed with the request
            return $next($request);
        }

        if (collect($this->webhook_ip_addresses)->contains($request->getClientIp())) {
            return $next($request);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }
}