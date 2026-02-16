<?php

namespace App\Filament\Admin\Resources\Promotions\Pages;

use App\Enums\PromotionType;
use App\Enums\QualificationContext;
use App\Filament\Admin\Resources\Promotions\Concerns\BuildsPromotionFormData;
use App\Filament\Admin\Resources\Promotions\PromotionResource;
use App\Models\DirectDiscountPromotion;
use App\Models\Promotion;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreatePromotion extends CreateRecord
{
    use BuildsPromotionFormData;

    protected static string $resource = PromotionResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(
            fn(): Promotion => match ($data["promotion_type"]) {
                PromotionType::DirectDiscount->value
                    => $this->buildDirectDiscountPromotion($data),
            },
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildDirectDiscountPromotion(array $data): Promotion
    {
        $discount = $this->createSimpleDiscount($data);

        $direct = DirectDiscountPromotion::query()->create([
            "simple_discount_id" => $discount->id,
        ]);

        $promotion = Promotion::query()->create([
            "name" => $data["name"],
            "application_budget" =>
                $data["application_budget"] !== null &&
                $data["application_budget"] !== ""
                    ? (int) $data["application_budget"]
                    : null,
            "monetary_budget" => $this->parseMonetaryBudget(
                $data["monetary_budget"] ?? null,
            ),
            "promotionable_type" => $direct->getMorphClass(),
            "promotionable_id" => $direct->id,
        ]);

        $root = $direct->qualification()->create([
            "promotion_id" => $promotion->id,
            "context" => QualificationContext::Primary->value,
            "op" => $data["qualification_op"],
            "sort_order" => 0,
        ]);

        $this->createQualificationRules(
            $root,
            $data["qualification_rules"] ?? [],
            $promotion,
        );

        return $promotion;
    }
}
