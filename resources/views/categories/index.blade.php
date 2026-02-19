<x-layout>
    <x-slot:breadcrumbs>
        <ol class="breadcrumbs-list">
            <li class="breadcrumbs-item">
                <span aria-current="page">Categories</span>
            </li>
        </ol>
    </x-slot:breadcrumbs>

    <h1 class="page-title sr-only">Categories</h1>

    <section class="switcher">
        @forelse ($categories as $category)
            <x-category-card :category="$category" />
        @empty
            <article class="card category-card">
                <h2 class="card-title category-card-title">No categories found</h2>
                <p class="card-meta category-card-meta">Create categories in the admin panel to populate this list.</p>
            </article>
        @endforelse
    </section>
</x-layout>
