<?php

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;

function decryptedOwnerCookie(TestResponse $response): string
{
    $cookie = collect($response->baseResponse->headers->getCookies())
        ->first(fn (Cookie $cookie): bool => $cookie->getName() === 'owner_token');

    return CookieValuePrefix::remove(
        app(Encrypter::class)->decrypt((string) $cookie->getValue(), false)
    );
}

it('sets an owner token cookie on the first visit', function () {
    $response = $this->get(route('projects.index'));

    $response->assertSuccessful()->assertCookie('owner_token');

    expect(decryptedOwnerCookie($response))->toBeUuid();
});

it('keeps an existing owner token cookie', function () {
    $token = ownerToken();

    $this->withCookie('owner_token', $token)
        ->get(route('projects.index'))
        ->assertSuccessful()
        ->assertCookieMissing('owner_token');
});

it('replaces a garbage owner token cookie with a fresh uuid', function () {
    $response = $this->withCookie('owner_token', 'not-a-uuid')
        ->get(route('projects.index'));

    $response->assertSuccessful()->assertCookie('owner_token');

    expect(decryptedOwnerCookie($response))->toBeUuid()
        ->not->toBe('not-a-uuid');
});
