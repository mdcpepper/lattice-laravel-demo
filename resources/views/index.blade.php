@extends('layouts.app')

@section('content')
    <h1 class="page-title">Lattice Demo</h1>

    <section class="switcher" aria-label="Categories">
        @forelse ($categories as $category)
            <x-category-card :category="$category" />
        @empty
            <article class="category-card">
                <h2 class="category-card-title">No categories found</h2>
                <p class="category-card-meta">Create categories in the admin panel to populate this list.</p>
            </article>
        @endforelse
    </section>
@endsection
