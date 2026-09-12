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

        $user = $request->getUser();
        $password = $request->getPassword();
        $expectedUser = config('postmark-webhooks.auth_user');
        $expectedPassword = config('postmark-webhooks.auth_pass');

        if ($this->hasCredential($user) || $this->hasCredential($password) || $this->hasCredential($expectedUser) || $this->hasCredential($expectedPassword)) {
            $userMatches = $this->credentialsMatch($user, $expectedUser);
            $passwordMatches = $this->credentialsMatch($password, $expectedPassword);

            if (! $userMatches || ! $passwordMatches) {
                Log::warning('Postmark webhook username/password failure! Check configuration. Attempt by: '.$request->getClientIp());

                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }

        $clientIp = $request->getClientIp();
        if ($clientIp !== null && in_array($clientIp, $this->webhook_ip_addresses, true)) {
            return $next($request);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    private function hasCredential(mixed $value): bool
    {
        // Keep legacy empty configuration behavior, but "0" is a real credential.
        return is_string($value) ? $value !== '' : (bool) $value;
    }

    private function credentialsMatch(?string $provided, mixed $expected): bool
    {
        if (is_string($provided) && is_string($expected)) {
            return hash_equals($expected, $provided);
        }

        return $provided === $expected;
    }
}
