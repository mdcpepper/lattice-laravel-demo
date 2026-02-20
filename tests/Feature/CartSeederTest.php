<?php

use App\Events\CartRecalculationRequested;
use App\Models\Cart\Cart;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Team;
use Database\Seeders\CartSeeder;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Event;

it('dispatches cart recalculation for each seeded cart', function (): void {
    Event::fake([CartRecalculationRequested::class]);

    $team = Team::factory()->create();

    $customerA = Customer::factory()->for($team)->create();
    $customerB = Customer::factory()->for($team)->create();

    $productA = Product::factory()->for($team)->create();
    $productB = Product::factory()->for($team)->create();

    $payload = [
        'carts' => [
            [
                'userId' => $customerA->id,
                'products' => [
                    ['id' => $productA->id, 'quantity' => 2],
                    ['id' => $productB->id, 'quantity' => 1],
                ],
            ],
            [
                'userId' => $customerB->id,
                'products' => [['id' => $productB->id, 'quantity' => 1]],
            ],
        ],
    ];

    $seeder = new class($team, $payload) extends CartSeeder
    {
        /**
         * @param  array<string, mixed>  $payload
         */
        public function __construct(
            private readonly Team $team,
            private readonly array $payload,
        ) {}

        protected function makeClient(): Client
        {
            return new Client([
                'base_uri' => 'https://dummyjson.test/',
                'handler' => HandlerStack::create(
                    new MockHandler([
                        new Response(
                            200,
                            ['Content-Type' => 'application/json'],
                            json_encode($this->payload, JSON_THROW_ON_ERROR),
                        ),
                    ]),
                ),
            ]);
        }

        protected function getDefaultTeam(): Team
        {
            return $this->team;
        }
    };

    $seeder->run();

    Event::assertDispatchedTimes(CartRecalculationRequested::class, 2);

    Event::assertDispatched(
        CartRecalculationRequested::class,
        fn (CartRecalculationRequested $event): bool => Cart::query()
            ->where('team_id', $team->id)
            ->whereKey($event->cartId)
            ->exists(),
    );
});
