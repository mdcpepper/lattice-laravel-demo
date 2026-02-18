<?php

namespace App\Jobs;

use App\Events\CartRecalculationRequested;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchCartRecalculationRequest implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $cartId)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return (string) $this->cartId;
    }

    public function handle(): void
    {
        CartRecalculationRequested::dispatch($this->cartId);
    }
}
