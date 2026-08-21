<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Identifies anonymous visitors with a long-lived UUID cookie so projects
 * can be scoped to the browser that created them without user accounts.
 */
class EnsureOwnerToken
{
    public const COOKIE = 'owner_token';

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie(self::COOKIE);

        if (! is_string($token) || ! Str::isUuid($token)) {
            $token = (string) Str::uuid7();

            Cookie::queue(Cookie::forever(self::COOKIE, $token));
        }

        $request->attributes->set(self::COOKIE, $token);

        return $next($request);
    }
}
