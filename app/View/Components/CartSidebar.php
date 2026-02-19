<?php

namespace App\View\Components;

use App\Models\Cart;
use App\Models\CartItem;
use Closure;
use Illuminate\Contracts\Session\Session;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\Component;

class CartSidebar extends Component
{
    private ?Cart $resolvedCart = null;

    private bool $hasResolvedCart = false;

    /**
     * @var Collection<int, CartItem>|null
     */
    private ?Collection $resolvedItems = null;

    public function __construct(private Session $session) {}

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
        return $this->items()->isNotEmpty();
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

    public function productName(CartItem $item): string
    {
        return $item->product?->name ?? 'Unknown product';
    }

    public function productThumbnail(CartItem $item): ?string
    {
        return $item->product?->thumb_url;
    }

    public function quantityLabel(CartItem $item): string
    {
        return '× 1';
    }

    public function hasDiscountedItemTotal(CartItem $item): bool
    {
        return $this->itemSubtotalInMinorUnits($item) !==
            $this->itemTotalInMinorUnits($item);
    }

    public function formattedItemSubtotal(CartItem $item): string
    {
        return $this->formatMinorUnits($this->itemSubtotalInMinorUnits($item));
    }

    public function formattedItemTotal(CartItem $item): string
    {
        return $this->formatMinorUnits($this->itemTotalInMinorUnits($item));
    }

    private function cart(): ?Cart
    {
        if ($this->hasResolvedCart) {
            return $this->resolvedCart;
        }

        $ulid = $this->session->get('cart_ulid');

        if (! is_string($ulid) || $ulid === '') {
            $this->hasResolvedCart = true;

            return null;
        }

        $cart = Cart::query()
            ->where('ulid', $ulid)
            ->with(['items.product'])
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

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.cart-sidebar');
    }
}
