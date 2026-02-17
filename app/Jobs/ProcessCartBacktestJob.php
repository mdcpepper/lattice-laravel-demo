<?php

namespace App\Jobs;

use App\Enums\BacktestStatus;
use App\Models\Backtest;
use App\Models\BacktestedCart;
use App\Models\BacktestedCartItem;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\PromotionRedemption;
use App\Services\Lattice\Stacks\LatticeStackFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Lattice\Item;
use Lattice\Money;
use Lattice\Product as LatticeProduct;

class ProcessCartBacktestJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $backtestRunId,
        public readonly int $cartId,
    ) {}

    public function handle(LatticeStackFactory $stackFactory): void
    {
        $backtestRun = Backtest::query()
            ->with('promotionStack.layers')
            ->findOrFail($this->backtestRunId);

        $promotionStack = $backtestRun->promotionStack;
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

        $simulatedCart = BacktestedCart::query()->create([
            'backtest_id' => $backtestRun->id,
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

            $price = (int) $product->price->getAmount();
            $currency = $product->price->getCurrency()->getCode();

            $offerPrice =
                count($applications) > 0
                    ? end($applications)->finalPrice->amount
                    : $price;

            $offerPriceCurrency =
                count($applications) > 0
                    ? end($applications)->finalPrice->currency
                    : $currency;

            $backtestedCartItem = BacktestedCartItem::query()->create([
                'backtest_id' => $backtestRun->id,
                'backtested_cart_id' => $simulatedCart->id,
                'cart_item_id' => $item->id,
                'product_id' => $product->id,
                'price' => $price,
                'price_currency' => $currency,
                'offer_price' => $offerPrice,
                'offer_price_currency' => $offerPriceCurrency,
            ]);

            foreach ($applications as $index => $application) {
                PromotionRedemption::createFromApplication(
                    $application,
                    $promotionStack,
                    $backtestedCartItem,
                    $index,
                );
            }
        }

        $backtestRun->increment('processed_carts');

        if (
            $backtestRun->fresh()->processed_carts >= $backtestRun->total_carts
        ) {
            $backtestRun->update([
                'status' => BacktestStatus::Completed,
            ]);
        }
    }
}
