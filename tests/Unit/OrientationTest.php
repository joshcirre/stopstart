<?php

use App\Enums\Orientation;

it('exposes landscape dimensions', function () {
    expect(Orientation::Landscape->width())->toBe(1920)
        ->and(Orientation::Landscape->height())->toBe(1080);
});

it('exposes portrait dimensions', function () {
    expect(Orientation::Portrait->width())->toBe(1080)
        ->and(Orientation::Portrait->height())->toBe(1920);
});
