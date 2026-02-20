<?php

namespace Database\Seeders;

use App\Events\CartRecalculationRequested;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Team;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use GuzzleHttp\Client;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use RuntimeException;

class CartSeeder extends Seeder
{
    use DummyJsonSeeder;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $client = $this->makeClient();
        $team = $this->getDefaultTeam();

        $validProductIds = $this->fetchValidProductIds($team);
        $carts = $this->fetchCarts($client);

        Cart::query()->where('team_id', $team->id)->delete();

        $carts->each(function (array $cart) use (
            $team,
            $validProductIds,
        ): void {
            $this->seedCart($cart, $team, $validProductIds);
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fetchCarts(Client $client): Collection
    {
        $response = $client->request('GET', 'carts?limit=0');

        /** @var mixed $payload */
        $payload = json_decode(
            $response->getBody()->getContents(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $carts = $payload['carts'] ?? null;

        if (! is_array($carts)) {
            throw new RuntimeException(
                'DummyJson carts response did not include a valid carts array.',
            );
        }

        return collect($carts);
    }

    /**
     * @return array<int, int>
     */
    private function fetchValidProductIds(Team $team): array
    {
        return Product::query()
            ->where('team_id', $team->id)
            ->pluck('id')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $cartData
     * @param  array<int, int>  $validProductIds
     */
    private function seedCart(
        array $cartData,
        Team $team,
        array $validProductIds,
    ): void {
        $userId = $cartData['userId'] ?? null;
        $products = $cartData['products'] ?? null;

        if (! is_int($userId) || ! is_array($products)) {
            return;
        }

        $customer = Customer::query()
            ->where('team_id', $team->id)
            ->find($userId);

        $cartCreatedAt = Carbon::now()->subSeconds(
            random_int(0, 90 * 24 * 60 * 60),
        );

        $cart = new Cart([
            'team_id' => $team->id,
            'customer_id' => $customer?->id,
            'email' => $customer?->email,
        ]);

        $cart->created_at = $cartCreatedAt;
        $cart->updated_at = $cartCreatedAt;

        Cart::withoutTimestamps(fn () => $cart->save());

        $itemTime = $cartCreatedAt->copy();

        foreach ($products as $item) {
            $itemTime = $this->seedCartItem(
                $cart,
                $item,
                $validProductIds,
                $itemTime,
            );
        }

        CartRecalculationRequested::dispatch($cart->id);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, int>  $validProductIds
     */
    private function seedCartItem(
        Cart $cart,
        mixed $item,
        array $validProductIds,
        CarbonInterface $itemTime,
    ): CarbonInterface {
        if (! is_array($item)) {
            return $itemTime;
        }

        $productId = $item['id'] ?? null;
        $quantity = $item['quantity'] ?? null;

        if (
            ! is_int($productId) ||
            ! is_int($quantity) ||
            $quantity < 1 ||
            ! in_array($productId, $validProductIds, true)
        ) {
            return $itemTime;
        }

        for ($i = 0; $i < $quantity; $i++) {
            $itemTime = $itemTime->addMinutes(random_int(2, 15));

            $cartItem = new CartItem([
                'cart_id' => $cart->id,
                'product_id' => $productId,
            ]);

            $cartItem->created_at = $itemTime;
            $cartItem->updated_at = $itemTime;

            CartItem::withoutTimestamps(fn () => $cartItem->save());
        }

        return $itemTime;
    }
}
