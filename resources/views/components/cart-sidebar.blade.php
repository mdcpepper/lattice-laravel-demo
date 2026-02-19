<aside aria-label="Cart">
    <article class="card cart-sidebar-card">
        <h2 class="card-title cart-sidebar-title">
            Cart
            @if ($itemCount() > 0)
                ({{ $itemCount() }})
            @endif
        </h2>

        @if ($hasItems())
            <ul class="cart-sidebar-items">
                @foreach ($groupedItems() as $itemGroup)
                    <li class="cart-sidebar-item">
                        @if ($productThumbnail($itemGroup) !== null)
                            <img
                                class="cart-sidebar-item-thumb"
                                src="{{ $productThumbnail($itemGroup) }}"
                                alt="{{ $productName($itemGroup) }}"
                                width="300"
                                height="300"
                            >
                        @endif

                        <div class="cart-sidebar-item-copy">
                            <p class="cart-sidebar-item-heading">
                                <span class="cart-sidebar-item-name" title="{{ $productName($itemGroup) }}">{{ $productName($itemGroup) }}</span>
                                <span class="cart-sidebar-item-quantity">{{ $quantityLabel($itemGroup) }}</span>
                            </p>
                            <p class="cart-sidebar-item-pricing">
                                @if ($hasDiscountedItemTotal($itemGroup))
                                    <span>{{ $formattedItemTotal($itemGroup) }}</span>
                                    <del>{{ $formattedItemSubtotal($itemGroup) }}</del>
                                @else
                                    <span>{{ $formattedItemTotal($itemGroup) }}</span>
                                @endif
                            </p>
                            @if ($itemGroupPromotionNames($itemGroup) !== [])
                                <ul class="cart-sidebar-item-promotions">
                                    @foreach ($itemGroupPromotionNames($itemGroup) as $name)
                                        <li class="cart-sidebar-item-promotion">{{ $name }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        <form class="cart-sidebar-item-actions" method="post">
                            @csrf
                            <input type="hidden" name="product" value="{{ $groupedItemProductId($itemGroup) }}">
                            <input
                                class="button button--primary cart-sidebar-item-action"
                                type="submit"
                                value="-"
                                aria-label="Decrease quantity"
                                formaction="{{ route('cart.items.remove', ['item' => $groupedItemRemovalId($itemGroup)], absolute: false) }}"
                                hx-post="{{ route('cart.items.remove', ['item' => $groupedItemRemovalId($itemGroup)], absolute: false) }}"
                                hx-target="#cart-sidebar"
                                hx-swap="innerHTML"
                                hx-include="closest form"
                            >
                            <input
                                class="button button--primary cart-sidebar-item-action"
                                type="submit"
                                value="+"
                                aria-label="Increase quantity"
                                formaction="{{ route('cart.items.store', absolute: false) }}"
                                hx-post="{{ route('cart.items.store', absolute: false) }}"
                                hx-target="#cart-sidebar"
                                hx-swap="innerHTML"
                                hx-include="closest form"
                            >
                         </form>
                    </li>
                @endforeach
            </ul>

            <dl class="cart-sidebar-totals">
                @if ($hasDiscountedTotal())
                    <div class="cart-sidebar-row subtotal">
                        <dt>Subtotal</dt>
                        <dd>{{ $formattedSubtotal() }}</dd>
                    </div>
                @endif

                @if ($hasPromotionSavings())
                    <div class="cart-sidebar-row savings">
                        <dt>Savings</dt>
                        <dd>-{{ $formattedSavings() }}</dd>
                    </div>

                    @foreach ($promotionSavings() as $saving)
                        <div class="cart-sidebar-row savings-detail">
                            <dt>
                                {{ $saving->promotionName() }} × {{ $saving->redemptionCount() }}
                                ({{ $saving->itemCount() }} {{ $saving->itemCount() === 1 ? 'item' : 'items' }})
                            </dt>
                            <dd>-{{ $formattedPromotionSavingAmount($saving) }}</dd>
                        </div>
                    @endforeach
                @endif

                <div class="cart-sidebar-row total">
                    <dt>Total</dt>
                    <dd>{{ $formattedTotal() }}</dd>
                </div>
            </dl>
        @else
            <p class="card-meta cart-sidebar-meta">Your cart is empty.</p>
        @endif
    </article>
</aside>
