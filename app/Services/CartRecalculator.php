<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\PromotionRedemption;
use App\Models\PromotionStack;
use App\Services\Lattice\Stacks\LatticeStackFactory;
use Illuminate\Support\Collection;
use Lattice\Item;
use Lattice\Money;
use Lattice\Product as LatticeProduct;

class CartRecalculator
{
    public function __construct(
        private readonly LatticeStackFactory $stackFactory,
    ) {}

    public function recalculate(Cart $cart): void
    {
        $cart->loadMissing(['promotionStack.layers', 'items.product.tags']);

        /** @var Collection<int, CartItem> $items */
        $items = $cart->items->values();

        /** @var array<int, Item> $latticeItems */
        $latticeItems = [];

        if (
            $cart->promotionStack instanceof PromotionStack &&
            $items->isNotEmpty()
        ) {
            $latticeItems = $this->buildLatticeItems($items);
        }

        $cart
            ->getConnection()
            ->transaction(function () use ($cart, $items, $latticeItems): void {
                $this->deleteExistingCartItemRedemptions($cart);

                /** @var \Lattice\Receipt|null $receipt */
                $receipt = null;

                /** @var array<int, list<\Lattice\PromotionRedemption>> $redemptionsByCartItemId */
                $redemptionsByCartItemId = [];

                if (
                    $cart->promotionStack instanceof PromotionStack &&
                    $latticeItems !== []
                ) {
                    $latticeStack = $this->stackFactory->make(
                        $cart->promotionStack,
                    );

                    $receipt = $latticeStack->process($latticeItems);

                    foreach ($receipt->promotionRedemptions as $redemption) {
                        /** @var CartItem $cartItem */
                        $cartItem = $redemption->item->reference;

                        $redemptionsByCartItemId[$cartItem->id][] = $redemption;
                    }
                }

                $cartSubtotal = 0;
                $cartTotal = 0;
                $fallbackCurrency = 'GBP';

                foreach ($items as $item) {
                    $product = $item->product;

                    $redemptions = $redemptionsByCartItemId[$item->id] ?? [];

                    $price = (int) $product->price->getAmount();

                    $priceCurrency = $product->price->getCurrency()->getCode();

                    $finalRedemption =
                        $redemptions !== []
                            ? $redemptions[array_key_last($redemptions)]
                            : null;

                    $offerPrice =
                        $finalRedemption?->finalPrice->amount ?? $price;

                    $offerPriceCurrency =
                        $finalRedemption?->finalPrice->currency ??
                        $priceCurrency;

                    $item->update([
                        'price' => $price,
                        'price_currency' => $priceCurrency,
                        'offer_price' => $offerPrice,
                        'offer_price_currency' => $offerPriceCurrency,
                    ]);

                    if ($cart->promotionStack instanceof PromotionStack) {
                        foreach ($redemptions as $index => $redemption) {
                            PromotionRedemption::createFromRedemption(
                                $redemption,
                                $cart->promotionStack,
                                $item,
                                $index,
                            );
                        }
                    }

                    $cartSubtotal += $price;
                    $cartTotal += $offerPrice;
                    $fallbackCurrency = $priceCurrency;
                }

                $cart->update([
                    'subtotal' => $receipt?->subtotal->amount ?? $cartSubtotal,
                    'subtotal_currency' => $receipt?->subtotal->currency ?? $fallbackCurrency,
                    'total' => $receipt?->total->amount ?? $cartTotal,
                    'total_currency' => $receipt?->total->currency ?? $fallbackCurrency,
                ]);
            });
    }

    /**
     * @param  Collection<int, CartItem>  $items
     * @return array<int, Item>
     */
    private function buildLatticeItems(Collection $items): array
    {
        /** @var array<int, LatticeProduct> $latticeProductsByProductId */
        $latticeProductsByProductId = [];

        foreach ($items as $item) {
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

        return $items
            ->map(
                fn (CartItem $item): Item => Item::fromProduct(
                    reference: $item,
                    product: $latticeProductsByProductId[$item->product->id],
                ),
            )
            ->all();
    }

    private function deleteExistingCartItemRedemptions(Cart $cart): void
    {
        $cartItemIds = CartItem::query()
            ->withTrashed()
            ->where('cart_id', $cart->id)
            ->pluck('id');

        if ($cartItemIds->isEmpty()) {
            return;
        }

        PromotionRedemption::query()
            ->where('redeemable_type', new CartItem()->getMorphClass())
            ->whereIn('redeemable_id', $cartItemIds)
            ->delete();
    }
}
