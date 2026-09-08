<?php

use App\Models\Frame;
use App\Models\Project;
use App\Models\Video;
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

/**
 * The project id in the URL is guessable, so the owner cookie — not the id —
 * is what gates access. This walks every id-bound route so a new one cannot
 * be added without an owner check. Routes bound by {project:remote_token}
 * are deliberately excluded: the unguessable token is their credential.
 */
it('404s every project-id route for a non-owner', function () {
    $project = Project::factory()->create();
    $frame = Frame::factory()->for($project)->create();
    $video = Video::factory()->for($project)->completed()->create();

    $bindings = ['project' => $project->id, 'frame' => $frame->id, 'video' => $video->id];

    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route): bool => collect($route->parameterNames())->isNotEmpty()
            && collect($route->parameterNames())->every(fn (string $name): bool => isset($bindings[$name]))
            && ! str_contains($route->uri(), 'remote_token'))
        ->reject(fn ($route): bool => in_array('HEAD', $route->methods(), true) && count($route->methods()) === 1);

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        $method = collect($route->methods())->first(fn (string $m): bool => $m !== 'HEAD');
        $uri = collect($route->parameterNames())->reduce(
            fn (string $uri, string $name): string => str_replace('{'.$name.'}', (string) $bindings[$name], $uri),
            $route->uri(),
        );

        $this->withCookie('owner_token', ownerToken())
            ->call($method, '/'.$uri)
            ->assertNotFound("Route [{$method} {$uri}] did not 404 for a non-owner.");
    }
});
