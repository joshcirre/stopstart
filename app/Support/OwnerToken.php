<?php

namespace App\Support;

use App\Http\Middleware\EnsureOwnerToken;
use Illuminate\Http\Request;

final class OwnerToken
{
    public static function from(Request $request): string
    {
        return (string) $request->attributes->get(EnsureOwnerToken::COOKIE);
    }
}
