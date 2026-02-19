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
        >
    @endif

    <h2 class="card-title product-card-title">{{ $name() }}</h2>
    <p class="product-card-price">{{ $price() }}</p>
    <p class="card-meta product-card-meta">{{ $description() }}</p>
</article>
