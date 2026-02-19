<article class="card product-card">
    @if ($hasImage())
        <img
            class="card-image product-card-image"
            src="{{ $imageSrc() }}"
            @if ($hasResponsiveSources())
                srcset="{{ $imageSrcset() }}"
                sizes="{{ $imageSizes() }}"
            @endif
            alt="{{ $imageAlt() }}"
            width="{{ $imageWidth() }}"
            height="{{ $imageHeight() }}"
        >
    @endif

    <h2 class="card-title product-card-title">{{ $name() }}</h2>
    <p class="product-card-price">{{ $price() }}</p>
    <p class="card-meta product-card-meta">{{ $description() }}</p>

    <form
        class="add-to-cart-form"
        method="post"
        action="{{ route('cart.items.store', absolute: false) }}"
        hx-post="{{ route('cart.items.store', absolute: false) }}"
        hx-target="#cart-sidebar"
        hx-swap="innerHTML"
    >
        @csrf
        <input type="hidden" name="product" value="{{ $productId() }}">
        <input class="button button--primary button--add-to-cart" type="submit" value="Add to cart">
    </form>
</article>
