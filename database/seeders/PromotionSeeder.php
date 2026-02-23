<?php

namespace Database\Seeders;

use App\Enums\QualificationOp;
use App\Enums\QualificationRuleKind;
use App\Enums\SimpleDiscountKind;
use App\Models\Promotions\DirectDiscountPromotion;
use App\Models\Promotions\Promotion;
use App\Models\Promotions\SimpleDiscount;
use App\Models\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Tags\Tag;

class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        $team = Team::query()->first();

        if (!$team instanceof Team) {
            return;
        }

        $this->createBeautyPromotion($team);
    }

    private function createBeautyPromotion(Team $team): void
    {
        $existing = Promotion::query()
            ->where("team_id", "=", $team->id)
            ->where("name", "=", "10% off Beauty")
            ->first();

        if ($existing instanceof Promotion) {
            return;
        }

        $discount = SimpleDiscount::query()->create([
            "kind" => SimpleDiscountKind::PercentageOff,
            "percentage" => 100,
        ]);

        $direct = DirectDiscountPromotion::query()->create([
            "simple_discount_id" => $discount->id,
        ]);

        $promotion = Promotion::query()->create([
            "name" => "10% off Beauty",
            "team_id" => $team->id,
            "promotionable_type" => $direct->getMorphClass(),
            "promotionable_id" => $direct->id,
        ]);

        $qualification = $direct->qualification()->create([
            "promotion_id" => $promotion->id,
            "context" => "primary",
            "op" => QualificationOp::And,
            "sort_order" => 0,
        ]);

        $rule = $qualification->rules()->create([
            "kind" => QualificationRuleKind::HasAny,
            "sort_order" => 0,
        ]);

        $rule->syncTags([$this->resolveTag("category:beauty")]);
    }

    private function resolveTag(string $name): Tag
    {
        $locale = Tag::getLocale();

        $tag = Tag::query()
            ->whereNull("type")
            ->where("name->{$locale}", "=", $name)
            ->first();

        if ($tag instanceof Tag) {
            return $tag;
        }

        return Tag::query()->create([
            "name" => [$locale => $name],
            "slug" => [$locale => Str::slug($name)],
            "type" => null,
        ]);
    }
}
