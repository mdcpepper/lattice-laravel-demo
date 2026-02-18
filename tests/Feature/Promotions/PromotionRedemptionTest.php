<?php

namespace Tests\Feature\Promotions;

use App\Enums\QualificationOp;
use App\Enums\QualificationRuleKind;
use App\Enums\SimpleDiscountKind;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DirectDiscountPromotion;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use App\Models\PromotionStack;
use App\Models\SimpleDiscount;
use App\Models\Team;
use App\Services\Lattice\Promotions\LatticePromotionFactory;
use Lattice\Item;
use Lattice\Layer;
use Lattice\LayerOutput;
use Lattice\Money;
use Lattice\StackBuilder;

it('saves promotion redemptions from a Lattice receipt', function (): void {
    $team = Team::factory()->create();
    $stack = PromotionStack::factory()->for($team)->create();

    $product = \App\Models\Product::factory()
        ->for($team)
        ->create(['price' => 5_00]);

    $product->syncTags(['sale']);
    $product->load('tags');

    $cart = Cart::factory()->for($team)->create();
    $cartItem = CartItem::factory()
        ->for($cart)
        ->for($product)
        ->create([
            'price' => 500,
            'price_currency' => 'GBP',
            'offer_price' => 500,
            'offer_price_currency' => 'GBP',
        ]);

    $discount = SimpleDiscount::query()->create([
        'kind' => SimpleDiscountKind::PercentageOff,
        'percentage' => 10.0,
    ]);

    $direct = DirectDiscountPromotion::query()->create([
        'simple_discount_id' => $discount->id,
    ]);

    $promotion = Promotion::query()->create([
        'name' => '10% Off Sale Items',
        'team_id' => $team->id,
        'promotionable_type' => $direct->getMorphClass(),
        'promotionable_id' => $direct->id,
    ]);

    $qualification = $direct->qualification()->create([
        'promotion_id' => $promotion->id,
        'context' => 'primary',
        'op' => QualificationOp::And,
        'sort_order' => 0,
    ]);

    $rule = $qualification->rules()->create([
        'kind' => QualificationRuleKind::HasAny,
        'sort_order' => 0,
    ]);

    $rule->syncTags(['sale']);

    $promotion = Promotion::query()->withGraph()->findOrFail($promotion->id);

    $latticePromotion = app(LatticePromotionFactory::class)->make($promotion);

    $latticeProduct = new \Lattice\Product(
        reference: $product,
        name: $product->name,
        price: new Money((int) $product->price->getAmount(), 'GBP'),
        tags: $product->tags_array,
    );

    $latticeItem = Item::fromProduct(
        reference: $product,
        product: $latticeProduct,
    );

    $stackBuilder = new StackBuilder;

    $layer = $stackBuilder->addLayer(
        new Layer(
            reference: 'root',
            output: LayerOutput::passThrough(),
            promotions: [$latticePromotion],
        ),
    );

    $stackBuilder->setRoot($layer);

    $receipt = $stackBuilder->build()->process([$latticeItem]);

    $redemptions = collect($receipt->promotionApplications)
        ->values()
        ->map(
            fn (
                $application,
                int $index,
            ) => PromotionRedemption::createFromApplication(
                $application,
                $stack,
                $cartItem,
                $index,
            ),
        );

    expect($receipt->subtotal->amount)
        ->toBe(500)
        ->and($receipt->total->amount)
        ->toBe(450);

    expect($redemptions)->toHaveCount(1);

    $redemption = $redemptions->first();

    expect($redemption->promotion->is($promotion))
        ->toBeTrue()
        ->and($redemption->promotionStack->is($stack))
        ->toBeTrue()
        ->and((int) $redemption->original_price->getAmount())
        ->toBe(500)
        ->and((int) $redemption->final_price->getAmount())
        ->toBe(450);

    $this->assertDatabaseHas('promotion_redemptions', [
        'promotion_id' => $promotion->id,
        'promotion_stack_id' => $stack->id,
        'redeemable_type' => CartItem::class,
        'redeemable_id' => $cartItem->id,
        'sort_order' => 0,
        'original_price' => 500,
        'original_price_currency' => 'GBP',
        'final_price' => 450,
        'final_price_currency' => 'GBP',
    ]);
});
