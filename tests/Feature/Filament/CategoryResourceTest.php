<?php

namespace Tests\Feature\Filament;

use App\Filament\Admin\Resources\Carts\Tables\ProductSelectionTable;
use App\Filament\Admin\Resources\Categories\Pages\CreateCategory;
use App\Filament\Admin\Resources\Categories\Pages\EditCategory;
use App\Filament\Admin\Resources\Categories\Pages\ListCategories;
use App\Models\Category;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\TableSelect\Livewire\TableSelectLivewireComponent;
use Livewire\Livewire;

beforeEach(function (): void {
    $user = User::factory()->create();
    $this->team = Team::factory()->create();

    $this->team->members()->attach($user);

    Filament::setCurrentPanel('admin');
    Filament::setTenant($this->team, isQuiet: true);

    $this->actingAs($user);
});

it('can create a category with a main product', function (): void {
    $existingCategory = Category::factory()->for($this->team)->create();

    $mainProduct = Product::factory()
        ->for($this->team)
        ->for($existingCategory)
        ->create();

    Livewire::test(CreateCategory::class)
        ->fillForm([
            'name' => 'Seasonal',
            'slug' => 'seasonal',
            'main_product_id' => $mainProduct->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $category = Category::query()
        ->where('team_id', $this->team->id)
        ->where('slug', 'seasonal')
        ->first();

    expect($category)
        ->not->toBeNull()
        ->and($category?->main_product_id)
        ->toBe($mainProduct->id);
});

it(
    'shows the main product thumbnail in the categories table',
    function (): void {
        $category = Category::factory()->for($this->team)->create();

        $mainProduct = Product::factory()
            ->for($this->team)
            ->for($category)
            ->create([
                'thumb_url' => 'https://example.test/thumb.jpg',
                'image_url' => 'https://example.test/image.jpg',
            ]);

        $category->update(['main_product_id' => $mainProduct->id]);

        Livewire::test(ListCategories::class)
            ->assertCanSeeTableRecords([$category])
            ->assertSee('Thumbnail')
            ->assertSee('Featured Product')
            ->assertSee($mainProduct->name)
            ->assertSee('https://example.test/thumb.jpg');
    },
);

it('can update the main product on a category', function (): void {
    $category = Category::factory()->for($this->team)->create();

    $originalProduct = Product::factory()
        ->for($this->team)
        ->for($category)
        ->create();

    $replacementProduct = Product::factory()
        ->for($this->team)
        ->for($category)
        ->create();

    $category->update(['main_product_id' => $originalProduct->id]);

    Livewire::test(EditCategory::class, ['record' => $category->ulid])
        ->fillForm(['main_product_id' => $replacementProduct->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($category->fresh()->main_product_id)->toBe($replacementProduct->id);
});

it(
    'cannot set a main product from a different category when editing',
    function (): void {
        $category = Category::factory()->for($this->team)->create();

        $currentMainProduct = Product::factory()
            ->for($this->team)
            ->for($category)
            ->create();

        $otherCategory = Category::factory()->for($this->team)->create();

        $otherCategoryProduct = Product::factory()
            ->for($this->team)
            ->for($otherCategory)
            ->create();

        $category->update(['main_product_id' => $currentMainProduct->id]);

        Livewire::test(EditCategory::class, ['record' => $category->ulid])
            ->fillForm(['main_product_id' => $otherCategoryProduct->id])
            ->call('save')
            ->assertHasFormErrors(['main_product_id']);

        expect($category->fresh()->main_product_id)->toBe(
            $currentMainProduct->id,
        );
    },
);

it(
    'keeps main product category scoping after saving the edit form',
    function (): void {
        $category = Category::factory()->for($this->team)->create();

        $currentMainProduct = Product::factory()
            ->for($this->team)
            ->for($category)
            ->create();

        $otherCategory = Category::factory()->for($this->team)->create();

        $otherCategoryProduct = Product::factory()
            ->for($this->team)
            ->for($otherCategory)
            ->create();

        $category->update(['main_product_id' => $currentMainProduct->id]);

        Livewire::test(EditCategory::class, ['record' => $category->ulid])
            ->fillForm([
                'name' => 'Updated Name',
                'slug' => $category->slug,
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->fillForm(['main_product_id' => $otherCategoryProduct->id])
            ->call('save')
            ->assertHasFormErrors(['main_product_id']);

        expect($category->fresh()->main_product_id)->toBe(
            $currentMainProduct->id,
        );
    },
);

it('retains current_category_id hidden state after saving', function (): void {
    $category = Category::factory()->for($this->team)->create();

    Livewire::test(EditCategory::class, ['record' => $category->ulid])
        ->assertSet('data.current_category_id', $category->id)
        ->fillForm([
            'name' => 'Updated Name',
            'slug' => $category->slug,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertSet('data.current_category_id', $category->id);
});

it(
    'scopes the main product table to the edited category even without table arguments',
    function (): void {
        $editedCategory = Category::factory()->for($this->team)->create();
        $otherCategory = Category::factory()->for($this->team)->create();

        $editedCategoryProduct = Product::factory()
            ->for($this->team)
            ->for($editedCategory)
            ->create();

        $otherCategoryProduct = Product::factory()
            ->for($this->team)
            ->for($otherCategory)
            ->create();

        Livewire::test(TableSelectLivewireComponent::class, [
            'isDisabled' => false,
            'maxSelectableRecords' => null,
            'model' => Category::class,
            'record' => $editedCategory,
            'relationshipName' => null,
            'shouldIgnoreRelatedRecords' => false,
            'tableConfiguration' => base64_encode(ProductSelectionTable::class),
            'tableArguments' => [],
            'state' => null,
        ])
            ->assertCanSeeTableRecords([$editedCategoryProduct])
            ->assertCanNotSeeTableRecords([$otherCategoryProduct]);
    },
);
