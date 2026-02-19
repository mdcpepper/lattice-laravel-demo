<aside class="cart-sidebar" aria-label="Cart">
    <article class="card cart-sidebar-card">
        <h2 class="card-title cart-sidebar-title">Cart</h2>

        @if ($hasItems())
            <ul class="cart-sidebar-items">
                @foreach ($items() as $item)
                    <li class="cart-sidebar-item">
                        @if ($productThumbnail($item) !== null)
                            <img
                                class="cart-sidebar-item-thumb"
                                src="{{ $productThumbnail($item) }}"
                                alt="{{ $productName($item) }}"
                            >
                        @endif

                        <div class="cart-sidebar-item-copy">
                            <p class="cart-sidebar-item-heading">
                                <span class="cart-sidebar-item-name" title="{{ $productName($item) }}">{{ $productName($item) }}</span>
                                <span class="cart-sidebar-item-quantity">{{ $quantityLabel($item) }}</span>
                            </p>
                            <p class="cart-sidebar-item-pricing">
                                @if ($hasDiscountedItemTotal($item))
                                    <span>{{ $formattedItemTotal($item) }}</span>
                                    <del>{{ $formattedItemSubtotal($item) }}</del>
                                @else
                                    <span>{{ $formattedItemTotal($item) }}</span>
                                @endif
                            </p>
                        </div>

                        <form class="cart-sidebar-item-actions" method="post">
                            @csrf
                            <input type="hidden" name="product" value="{{ $item->product_id }}">
                            <input
                                class="button button--primary cart-sidebar-item-action"
                                type="submit"
                                value="-"
                                aria-label="Decrease quantity"
                                formaction="{{ route('cart.items.remove', ['item' => $item->id], absolute: false) }}"
                            >
                            <input
                                class="button button--primary cart-sidebar-item-action"
                                type="submit"
                                value="+"
                                aria-label="Increase quantity"
                                formaction="{{ route('cart.items.store', absolute: false) }}"
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
