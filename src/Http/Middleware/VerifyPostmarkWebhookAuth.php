<?php

namespace Citricguy\PostmarkWebhooks\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyPostmarkWebhookAuth
{
    /**
     * Array of IP addresses from Postmark that are whitelisted.
     *
     * @see https://postmarkapp.com/support/article/800-ips-for-firewalls#webhooks
     *
     * @var array<int, string>
     */
    private array $webhook_ip_addresses = [
        '18.217.206.57',
        '3.134.147.250',
        '50.31.156.6',
        '50.31.156.77',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (! app()->environment('production') || ! config('postmark-webhooks.firewall_enabled')) {
            // Bypass the middleware and proceed with the request
            return $next($request);
        }

        if ($request->getUser() || $request->getPassword() || config('postmark-webhooks.auth_user') || config('postmark-webhooks.auth_pass')) {
            if ($request->getUser() !== config('postmark-webhooks.auth_user') || $request->getPassword() !== config('postmark-webhooks.auth_pass')) {
                Log::warning('Postmark webhook username/password failure! Check configuration. Attempt by: '.$request->getClientIp());

                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }

        $clientIp = $request->getClientIp();
        if ($clientIp && collect($this->webhook_ip_addresses)->contains($clientIp)) {
            return $next($request);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }
}
