<?php

namespace App\View\Components;

use App\DTOs\CartItemGroup;
use App\DTOs\CartPromotionSaving;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Promotions\PromotionRedemption;
use Closure;
use Illuminate\Contracts\Session\Session;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\View\Component;

class CartSidebar extends Component
{
    private ?Cart $resolvedCart = null;

    private bool $hasResolvedCart = false;

    /**
     * @var Collection<int, CartItem>|null
     */
    private ?Collection $resolvedItems = null;

    /**
     * @var SupportCollection<int, CartItemGroup>|null
     */
    private ?SupportCollection $resolvedGroupedItems = null;

    /**
     * @var SupportCollection<int, CartPromotionSaving>|null
     */
    private ?SupportCollection $resolvedPromotionSavings = null;

    public function __construct(private Session $session) {}

    public function itemCount(): int
    {
        return $this->items()->count();
    }

    /**
     * @return Collection<int, CartItem>
     */
    public function items(): Collection
    {
        if ($this->resolvedItems !== null) {
            return $this->resolvedItems;
        }

        $cart = $this->cart();

        if (! $cart instanceof Cart) {
            return $this->resolvedItems = new Collection;
        }

        return $this->resolvedItems = $cart->items;
    }

    public function hasItems(): bool
    {
        return $this->groupedItems()->isNotEmpty();
    }

    /**
     * @return SupportCollection<int, CartItemGroup>
     */
    public function groupedItems(): SupportCollection
    {
        if ($this->resolvedGroupedItems !== null) {
            return $this->resolvedGroupedItems;
        }

        return $this->resolvedGroupedItems = $this->items()
            ->groupBy($this->itemGroupKey(...))
            ->map(function (Collection $groupedItems): ?CartItemGroup {
                $firstItem = $groupedItems->first();

                if (! $firstItem instanceof CartItem) {
                    return null;
                }

                $product = $firstItem->product;

                if ($product === null) {
                    return null;
                }

                $promotionNames = $firstItem->redemptions
                    ->sortBy('sort_order')
                    ->map(
                        fn (PromotionRedemption $r): string => $r->promotion
                            ->name,
                    )
                    ->unique()
                    ->values()
                    ->all();

                return new CartItemGroup(
                    product: $product,
                    quantity: $groupedItems->count(),
                    subtotalInMinorUnits: (int) $groupedItems->sum(
                        fn (
                            CartItem $item,
                        ): int => $this->itemSubtotalInMinorUnits($item),
                    ),
                    totalInMinorUnits: (int) $groupedItems->sum(
                        fn (CartItem $item): int => $this->itemTotalInMinorUnits(
                            $item,
                        ),
                    ),
                    promotionNames: $promotionNames,
                );
            })
            ->filter(
                fn (?CartItemGroup $itemGroup): bool => $itemGroup instanceof CartItemGroup,
            )
            ->values();
    }

    public function hasDiscountedTotal(): bool
    {
        return $this->subtotalInMinorUnits() !== $this->totalInMinorUnits();
    }

    public function formattedSubtotal(): string
    {
        return $this->formatMinorUnits($this->subtotalInMinorUnits());
    }

    public function formattedTotal(): string
    {
        return $this->formatMinorUnits($this->totalInMinorUnits());
    }

    public function productName(CartItemGroup $itemGroup): string
    {
        return $itemGroup->product()->name;
    }

    public function productThumbnail(CartItemGroup $itemGroup): ?string
    {
        return $itemGroup->product()->thumb_url;
    }

    public function quantityLabel(CartItemGroup $itemGroup): string
    {
        return '× '.$itemGroup->quantity();
    }

    public function hasDiscountedItemTotal(CartItemGroup $itemGroup): bool
    {
        return $this->itemGroupSubtotalUnitInMinorUnits($itemGroup) !==
            $this->itemGroupTotalUnitInMinorUnits($itemGroup);
    }

    public function formattedItemSubtotal(CartItemGroup $itemGroup): string
    {
        return $this->formatMinorUnits(
            $this->itemGroupSubtotalUnitInMinorUnits($itemGroup),
        );
    }

    public function formattedItemTotal(CartItemGroup $itemGroup): string
    {
        return $this->formatMinorUnits(
            $this->itemGroupTotalUnitInMinorUnits($itemGroup),
        );
    }

    public function groupedItemProductId(CartItemGroup $itemGroup): int
    {
        return (int) $itemGroup->product()->getKey();
    }

    public function groupedItemRemovalId(CartItemGroup $itemGroup): int
    {
        $offerPriceInMinorUnits = $this->itemGroupTotalUnitInMinorUnits(
            $itemGroup,
        );

        $matchingItem = $this->items()
            ->filter(
                fn (CartItem $item): bool => (int) $item->product_id ===
                    $this->groupedItemProductId($itemGroup) &&
                    $this->itemTotalInMinorUnits($item) ===
                        $offerPriceInMinorUnits,
            )
            ->sortBy('id')
            ->last();

        return $matchingItem instanceof CartItem ? (int) $matchingItem->id : 0;
    }

    /**
     * @return array<int, string>
     */
    public function itemGroupPromotionNames(CartItemGroup $itemGroup): array
    {
        return $itemGroup->promotionNames();
    }

    /**
     * @return SupportCollection<int, CartPromotionSaving>
     */
    public function promotionSavings(): SupportCollection
    {
        if ($this->resolvedPromotionSavings !== null) {
            return $this->resolvedPromotionSavings;
        }

        return $this->resolvedPromotionSavings = $this->items()
            ->flatMap(fn (CartItem $item) => $item->redemptions)
            ->groupBy(fn (PromotionRedemption $r) => (int) $r->promotion_id)
            ->map(function (SupportCollection $group): CartPromotionSaving {
                $first = $group->first();

                $redemptionCount = $group
                    ->pluck('redemption_idx')
                    ->unique()
                    ->count();

                return new CartPromotionSaving(
                    promotionName: $first->promotion->name,
                    redemptionCount: $redemptionCount,
                    itemCount: $group->count(),
                    savingsInMinorUnits: (int) $group->sum(
                        fn (
                            PromotionRedemption $r,
                        ): int => (int) $r->getRawOriginal('original_price') -
                            (int) $r->getRawOriginal('final_price'),
                    ),
                );
            })
            ->values();
    }

    public function hasPromotionSavings(): bool
    {
        return $this->promotionSavings()->isNotEmpty();
    }

    public function formattedSavings(): string
    {
        return $this->formatMinorUnits(
            $this->subtotalInMinorUnits() - $this->totalInMinorUnits(),
        );
    }

    public function formattedPromotionSavingAmount(
        CartPromotionSaving $saving,
    ): string {
        return $this->formatMinorUnits($saving->savingsInMinorUnits());
    }

    private function cart(): ?Cart
    {
        if ($this->hasResolvedCart) {
            return $this->resolvedCart;
        }

        $ulid = $this->session->get('cart_ulid');

        if (! \is_string($ulid) || $ulid === '') {
            $this->hasResolvedCart = true;

            return null;
        }

        $cart = Cart::query()
            ->where('ulid', $ulid)
            ->with(['items.product', 'items.redemptions.promotion'])
            ->first();

        if (! $cart instanceof Cart) {
            $this->hasResolvedCart = true;

            return null;
        }

        $this->resolvedCart = $cart;
        $this->hasResolvedCart = true;

        return $this->resolvedCart;
    }

    private function subtotalInMinorUnits(): int
    {
        $cart = $this->cart();

        return $cart instanceof Cart
            ? (int) $cart->getRawOriginal('subtotal')
            : 0;
    }

    private function totalInMinorUnits(): int
    {
        $cart = $this->cart();

        return $cart instanceof Cart ? (int) $cart->getRawOriginal('total') : 0;
    }

    private function formatMinorUnits(int $amount): string
    {
        return '£'.number_format($amount / 100, 2);
    }

    private function itemSubtotalInMinorUnits(CartItem $item): int
    {
        return (int) $item->getRawOriginal('price');
    }

    private function itemTotalInMinorUnits(CartItem $item): int
    {
        return (int) $item->getRawOriginal('offer_price');
    }

    private function itemGroupKey(CartItem $item): string
    {
        return (string) $item->product_id.
            ':'.
            $this->itemTotalInMinorUnits($item);
    }

    private function itemGroupSubtotalUnitInMinorUnits(
        CartItemGroup $itemGroup,
    ): int {
        return intdiv(
            $itemGroup->subtotalInMinorUnits(),
            max($itemGroup->quantity(), 1),
        );
    }

    private function itemGroupTotalUnitInMinorUnits(
        CartItemGroup $itemGroup,
    ): int {
        return intdiv(
            $itemGroup->totalInMinorUnits(),
            max($itemGroup->quantity(), 1),
        );
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.cart-sidebar');
    }
}
