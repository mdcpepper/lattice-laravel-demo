<?php

namespace App\Jobs;

use App\Enums\SimulationRunStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\PromotionRedemption;
use App\Models\SimulatedCart;
use App\Models\SimulatedCartItem;
use App\Models\SimulationRun;
use App\Services\Lattice\Stacks\LatticeStackFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Lattice\Item;
use Lattice\Money;
use Lattice\Product as LatticeProduct;

class ProcessSimulationCartJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $simulationRunId,
        public readonly int $cartId,
    ) {}

    public function handle(LatticeStackFactory $stackFactory): void
    {
        $simulationRun = SimulationRun::query()
            ->with('promotionStack.layers')
            ->findOrFail($this->simulationRunId);

        $promotionStack = $simulationRun->promotionStack;
        $latticeStack = $stackFactory->make($promotionStack);

        $cart = Cart::query()
            ->with('items.product.tags')
            ->findOrFail($this->cartId);

        /** @var array<int, LatticeProduct> $latticeProductsByProductId */
        $latticeProductsByProductId = [];

        foreach ($cart->items as $item) {
            $product = $item->product;

            if (! isset($latticeProductsByProductId[$product->id])) {
                $latticeProductsByProductId[$product->id] = new LatticeProduct(
                    reference: $product,
                    name: $product->name,
                    price: new Money((int) $product->price->getAmount(), 'GBP'),
                    tags: $product->tags_array,
                );
            }
        }

        $latticeItems = $cart->items
            ->map(
                fn (CartItem $item): Item => Item::fromProduct(
                    reference: $item,
                    product: $latticeProductsByProductId[$item->product->id],
                ),
            )
            ->all();

        $receipt = $latticeStack->process($latticeItems);

        $simulatedCart = SimulatedCart::query()->create([
            'simulation_run_id' => $simulationRun->id,
            'cart_id' => $cart->id,
            'team_id' => $cart->team_id,
            'email' => $cart->email,
            'customer_id' => $cart->customer_id,
            'subtotal' => $receipt->subtotal->amount,
            'subtotal_currency' => $receipt->subtotal->currency,
            'total' => $receipt->total->amount,
            'total_currency' => $receipt->total->currency,
        ]);

        /** @var array<int, list<\Lattice\PromotionApplication>> $applicationsByCartItemId */
        $applicationsByCartItemId = [];

        foreach ($receipt->promotionApplications as $application) {
            /** @var CartItem $cartItem */
            $cartItem = $application->item->reference;
            $applicationsByCartItemId[$cartItem->id][] = $application;
        }

        foreach ($cart->items as $item) {
            $product = $item->product;
            $applications = $applicationsByCartItemId[$item->id] ?? [];

            $subtotal = (int) $product->price->getAmount();
            $currency = $product->price->getCurrency()->getCode();

            $total =
                count($applications) > 0
                    ? end($applications)->finalPrice->amount
                    : $subtotal;

            $totalCurrency =
                count($applications) > 0
                    ? end($applications)->finalPrice->currency
                    : $currency;

            $simulatedCartItem = SimulatedCartItem::query()->create([
                'simulation_run_id' => $simulationRun->id,
                'simulated_cart_id' => $simulatedCart->id,
                'cart_item_id' => $item->id,
                'product_id' => $product->id,
                'subtotal' => $subtotal,
                'subtotal_currency' => $currency,
                'total' => $total,
                'total_currency' => $totalCurrency,
            ]);

            foreach ($applications as $index => $application) {
                PromotionRedemption::createFromApplication(
                    $application,
                    $promotionStack,
                    $simulatedCartItem,
                    $index,
                );
            }
        }

        $simulationRun->increment('processed_carts');

        if (
            $simulationRun->fresh()->processed_carts >=
            $simulationRun->total_carts
        ) {
            $simulationRun->update([
                'status' => SimulationRunStatus::Completed,
            ]);
        }
    }
}
