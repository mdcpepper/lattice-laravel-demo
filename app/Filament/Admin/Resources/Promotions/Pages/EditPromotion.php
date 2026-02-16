<?php

namespace App\Filament\Admin\Resources\Promotions\Pages;

use App\Enums\QualificationContext;
use App\Enums\QualificationRuleKind;
use App\Enums\SimpleDiscountKind;
use App\Filament\Admin\Resources\Promotions\Concerns\BuildsPromotionFormData;
use App\Filament\Admin\Resources\Promotions\PromotionResource;
use App\Models\DirectDiscountPromotion;
use App\Models\Promotion;
use App\Models\Qualification;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @extends EditRecord<Model>
 */
class EditPromotion extends EditRecord
{
    use BuildsPromotionFormData;

    protected static string $resource = PromotionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Promotion $promotion */
        $promotion = $this->record;

        $promotion->load([
            "promotionable.discount",
            "promotionable.qualification.rules.tags",
            "qualifications.rules.tags",
        ]);

        $promotionable = $promotion->promotionable;

        $data["promotion_type"] = match (get_class($promotionable)) {
            DirectDiscountPromotion::class => "direct_discount",
        };

        $rawBudget = $promotion->getRawOriginal("monetary_budget");

        $data["monetary_budget"] =
            $rawBudget !== null
                ? number_format((float) $rawBudget / 100, 2, ".", "")
                : null;

        if ($promotionable instanceof DirectDiscountPromotion) {
            $discount = $promotionable->discount;

            $data["discount_kind"] = $discount->kind->value;
            $data["discount_percentage"] = $discount->percentage;
            $data["discount_amount"] =
                $discount->amount !== null
                    ? number_format((float) $discount->amount / 100, 2, ".", "")
                    : null;

            $rootQual = $promotionable->qualification;

            $data["qualification_op"] = $rootQual->op->value;
            $data["qualification_rules"] = $this->flattenRules(
                $rootQual,
                $promotion,
            );
        }

        return $data;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function flattenRules(
        Qualification $rootQual,
        Promotion $promotion,
    ): array {
        $rules = [];

        foreach ($rootQual->rules->sortBy("sort_order") as $rule) {
            if ($rule->kind === QualificationRuleKind::Group) {
                $groupQual = $promotion->qualifications->firstWhere(
                    "id",
                    $rule->group_qualification_id,
                );

                $subRules = [];

                foreach ($groupQual->rules->sortBy("sort_order") as $subRule) {
                    $subRules[] = [
                        "kind" => $subRule->kind->value,
                        "tags" => $subRule->tags
                            ->pluck("name")
                            ->values()
                            ->all(),
                    ];
                }

                $rules[] = [
                    "kind" => QualificationRuleKind::Group->value,
                    "tags" => [],
                    "group_op" => $groupQual->op->value,
                    "group_rules" => $subRules,
                ];
            } else {
                $rules[] = [
                    "kind" => $rule->kind->value,
                    "tags" => $rule->tags->pluck("name")->values()->all(),
                    "group_op" => null,
                    "group_rules" => [],
                ];
            }
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Promotion $record */
        return DB::transaction(
            fn(): Promotion => match ($data["promotion_type"]) {
                "direct_discount" => $this->updateDirectDiscountPromotion(
                    $record,
                    $data,
                ),
            },
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateDirectDiscountPromotion(
        Promotion $promotion,
        array $data,
    ): Promotion {
        $promotion->update([
            "name" => $data["name"],
            "application_budget" =>
                $data["application_budget"] !== null &&
                $data["application_budget"] !== ""
                    ? (int) $data["application_budget"]
                    : null,
            "monetary_budget" => $this->parseMonetaryBudget(
                $data["monetary_budget"] ?? null,
            ),
        ]);

        $promotion->load(["promotionable.discount", "qualifications.rules"]);

        $direct = $promotion->promotionable;
        $discount = $direct->discount;

        $kind = SimpleDiscountKind::from($data["discount_kind"]);
        $discountData = ["kind" => $kind->value];

        if ($kind === SimpleDiscountKind::PercentageOff) {
            $discountData["percentage"] = $data["discount_percentage"];
            $discountData["amount"] = null;
            $discountData["amount_currency"] = null;
        } else {
            $discountData["percentage"] = null;
            $discountData["amount"] = $this->parseAmountToMinor(
                $data["discount_amount"] ?? null,
            );
            $discountData["amount_currency"] = "GBP";
        }

        $discount->update($discountData);

        foreach ($promotion->qualifications as $qual) {
            foreach ($qual->rules as $rule) {
                $rule->delete();
            }
        }
        $promotion->qualifications()->delete();

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
