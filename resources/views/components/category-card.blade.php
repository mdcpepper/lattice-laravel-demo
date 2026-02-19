<article class="category-card">
    <a href="{{ $url() }}">
        @if ($hasImage())
            <img
                class="category-card-image"
                src="{{ $imageSrc() }}"
                @if ($hasResponsiveSources())
                    srcset="{{ $imageSrcset() }}"
                    sizes="{{ $imageSizes() }}"
                @endif
                alt="{{ $imageAlt() }}"
            >
        @endif

        <h2 class="category-card-title">{{ $name() }}</h2>
    </a>
</article>
