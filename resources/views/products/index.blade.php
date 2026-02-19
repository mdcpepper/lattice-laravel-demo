<x-layout>
    <x-slot:breadcrumbs>
        <ol class="breadcrumbs-list">
            <li class="breadcrumbs-item">
                <a href="{{ route('categories.index', absolute: false) }}">Home</a>
            </li>
            <li class="breadcrumbs-item">
                <span aria-current="page">{{ $category->name }}</span>
            </li>
        </ol>
    </x-slot:breadcrumbs>

    <h1 class="page-title">{{ $category->name }}</h1>

    <div class="with-sidebar">
        <section class="switcher" aria-label="Products in {{ $category->name }}">
            @forelse ($products as $product)
                <x-product-card :product="$product" />
            @empty
                <article class="card product-card">
                    <h2 class="card-title product-card-title">No products found</h2>
                    <p class="card-meta product-card-meta">Add products in the admin panel to populate this category.</p>
                </article>
            @endforelse
        </section>

        <x-cart-sidebar />
    </div>
</x-layout>
