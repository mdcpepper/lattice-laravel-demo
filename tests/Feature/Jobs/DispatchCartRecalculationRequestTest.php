<?php

namespace Tests\Feature\Jobs;

use App\Events\CartRecalculationRequested;
use App\Jobs\DispatchCartRecalculationRequest;
use Illuminate\Support\Facades\Event;

it('dispatches a cart recalculation request event for the provided cart id', function (): void {
    Event::fake([CartRecalculationRequested::class]);

    $job = new DispatchCartRecalculationRequest(cartId: 123);

    $job->handle();

    Event::assertDispatched(
        CartRecalculationRequested::class,
        fn (CartRecalculationRequested $event): bool => $event->cartId === 123,
    );
});
