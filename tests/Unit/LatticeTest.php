<?php

namespace Tests\Unit;

test('the lattice extension is available', function () {
    expect(extension_loaded('lattice-php-ext'))->toBeTrue();
});
