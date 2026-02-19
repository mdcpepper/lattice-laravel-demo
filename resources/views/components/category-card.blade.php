<article class="card category-card">
    <a class="card-link category-card-link" href="{{ $url() }}">
        @if ($hasImage())
            <img
                class="card-image category-card-image"
                src="{{ $imageSrc() }}"
                @if ($hasResponsiveSources())
                    srcset="{{ $imageSrcset() }}"
                    sizes="{{ $imageSizes() }}"
                @endif
                alt="{{ $imageAlt() }}"
            >
        @endif

        <h2 class="card-title category-card-title">{{ $name() }}</h2>
    </a>
</article>
