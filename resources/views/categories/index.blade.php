<x-layout>
    <h1 class="page-title sr-only">Categories</h1>

    <div class="with-sidebar">
        <section class="switcher" aria-label="Category list">
            @forelse ($categories as $category)
                <x-category-card :category="$category" />
            @empty
                <article class="card category-card">
                    <h2 class="card-title category-card-title">No categories found</h2>
                    <p class="card-meta category-card-meta">Create categories in the admin panel to populate this list.</p>
                </article>
            @endforelse
        </section>

        <x-cart-sidebar />
    </div>
</x-layout>
