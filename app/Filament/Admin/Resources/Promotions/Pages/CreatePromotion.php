<?php

namespace App\Filament\Admin\Resources\Promotions\Pages;

use App\Enums\PromotionType;
use App\Enums\QualificationContext;
use App\Enums\QualificationOp;
use App\Filament\Admin\Resources\Promotions\Concerns\BuildsPromotionFormData;
use App\Filament\Admin\Resources\Promotions\PromotionResource;
use App\Models\Promotions\DirectDiscountPromotion;
use App\Models\Promotions\MixAndMatchPromotion;
use App\Models\Promotions\PositionalDiscountPromotion;
use App\Models\Promotions\Promotion;
use App\Models\Promotions\TieredThresholdPromotion;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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
            fn (): Promotion => match ($data['promotion_type']) {
                PromotionType::DirectDiscount->value => $this->buildDirectDiscountPromotion($data),
                PromotionType::MixAndMatch->value => $this->buildMixAndMatchPromotion($data),
                PromotionType::PositionalDiscount->value => $this->buildPositionalDiscountPromotion($data),
                PromotionType::TieredThreshold->value => $this->buildTieredThresholdPromotion($data),
            },
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildPositionalDiscountPromotion(array $data): Promotion
    {
        $discount = $this->createSimpleDiscount($data);

        $size = (int) ($data['size'] ?? 1);

        $positional = PositionalDiscountPromotion::query()->create([
            'simple_discount_id' => $discount->id,
            'size' => $size,
        ]);

        $promotion = Promotion::query()->create([
            'team_id' => $this->currentTeamId(),
            'name' => $data['name'],
            'application_budget' => $data['application_budget'] !== null &&
                $data['application_budget'] !== ''
                    ? (int) $data['application_budget']
                    : null,
            'monetary_budget' => $this->parseMonetaryBudget(
                $data['monetary_budget'] ?? null,
            ),
            'promotionable_type' => $positional->getMorphClass(),
            'promotionable_id' => $positional->id,
        ]);

        $root = $positional->qualification()->create([
            'promotion_id' => $promotion->id,
            'context' => QualificationContext::Primary->value,
            'op' => $data['qualification_op'],
            'sort_order' => 0,
        ]);

        $this->createQualificationRules(
            $root,
            $data['qualification_rules'] ?? [],
            $promotion,
        );

        $positions = $this->normalizeRepeaterRows($data['positions'] ?? []);

        foreach ($positions as $sortOrder => $positionData) {
            if (
                ! is_array($positionData) ||
                ! array_key_exists('position', $positionData)
            ) {
                continue;
            }

            $selectedPosition = (int) $positionData['position'];

            if ($selectedPosition < 1 || $selectedPosition > $size) {
                continue;
            }

            $positional->positions()->create([
                'position' => $selectedPosition - 1,
                'sort_order' => $sortOrder,
            ]);
        }

        return $promotion;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildDirectDiscountPromotion(array $data): Promotion
    {
        $discount = $this->createSimpleDiscount($data);

        $direct = DirectDiscountPromotion::query()->create([
            'simple_discount_id' => $discount->id,
        ]);

        $promotion = Promotion::query()->create([
            'team_id' => $this->currentTeamId(),
            'name' => $data['name'],
            'application_budget' => $data['application_budget'] !== null &&
                $data['application_budget'] !== ''
                    ? (int) $data['application_budget']
                    : null,
            'monetary_budget' => $this->parseMonetaryBudget(
                $data['monetary_budget'] ?? null,
            ),
            'promotionable_type' => $direct->getMorphClass(),
            'promotionable_id' => $direct->id,
        ]);

        $root = $direct->qualification()->create([
            'promotion_id' => $promotion->id,
            'context' => QualificationContext::Primary->value,
            'op' => $data['qualification_op'],
            'sort_order' => 0,
        ]);

        $this->createQualificationRules(
            $root,
            $data['qualification_rules'] ?? [],
            $promotion,
        );

        return $promotion;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildMixAndMatchPromotion(array $data): Promotion
    {
        $discount = $this->createMixAndMatchDiscount($data);

        $mixAndMatch = MixAndMatchPromotion::query()->create([
            'mix_and_match_discount_id' => $discount->id,
        ]);

        $promotion = Promotion::query()->create([
            'team_id' => $this->currentTeamId(),
            'name' => $data['name'],
            'application_budget' => $data['application_budget'] !== null &&
                $data['application_budget'] !== ''
                    ? (int) $data['application_budget']
                    : null,
            'monetary_budget' => $this->parseMonetaryBudget(
                $data['monetary_budget'] ?? null,
            ),
            'promotionable_type' => $mixAndMatch->getMorphClass(),
            'promotionable_id' => $mixAndMatch->id,
        ]);

        $slots = $data['slots'] ?? [];

        if (! is_array($slots)) {
            $slots = [];
        }

        foreach (array_values($slots) as $sortOrder => $slotData) {
            if (! is_array($slotData)) {
                continue;
            }

            $slot = $mixAndMatch->slots()->create([
                'min' => (int) ($slotData['min'] ?? 1),
                'max' => isset($slotData['max']) && $slotData['max'] !== ''
                        ? (int) $slotData['max']
                        : null,
                'sort_order' => $sortOrder,
            ]);

            $root = $slot->qualification()->create([
                'promotion_id' => $promotion->id,
                'context' => QualificationContext::Primary->value,
                'op' => $slotData['qualification_op'] ??
                    QualificationOp::And->value,
                'sort_order' => 0,
            ]);

            $this->createQualificationRules(
                $root,
                is_array($slotData['qualification_rules'] ?? null)
                    ? $slotData['qualification_rules']
                    : [],
                $promotion,
            );
        }

        return $promotion;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildTieredThresholdPromotion(array $data): Promotion
    {
        $tieredThreshold = TieredThresholdPromotion::query()->create();

        $promotion = Promotion::query()->create([
            'team_id' => $this->currentTeamId(),
            'name' => $data['name'],
            'application_budget' => $data['application_budget'] !== null &&
                $data['application_budget'] !== ''
                    ? (int) $data['application_budget']
                    : null,
            'monetary_budget' => $this->parseMonetaryBudget(
                $data['monetary_budget'] ?? null,
            ),
            'promotionable_type' => $tieredThreshold->getMorphClass(),
            'promotionable_id' => $tieredThreshold->id,
        ]);

        $tiers = $this->normalizeRepeaterRows($data['tiers'] ?? []);

        foreach ($tiers as $sortOrder => $tierData) {
            if (! is_array($tierData)) {
                continue;
            }

            $discount = $this->createTieredThresholdDiscount($tierData);

            $lowerMonetaryThreshold = $this->parseAmountToMinor(
                $tierData['lower_monetary_threshold'] ?? null,
            );
            $upperMonetaryThreshold = $this->parseAmountToMinor(
                $tierData['upper_monetary_threshold'] ?? null,
            );

            $tier = $tieredThreshold->tiers()->create([
                'tiered_threshold_discount_id' => $discount->id,
                'sort_order' => $sortOrder,
                'lower_monetary_threshold_minor' => $lowerMonetaryThreshold,
                'lower_monetary_threshold_currency' => $lowerMonetaryThreshold !== null ? 'GBP' : null,
                'lower_item_count_threshold' => isset($tierData['lower_item_count_threshold']) &&
                    $tierData['lower_item_count_threshold'] !== ''
                        ? (int) $tierData['lower_item_count_threshold']
                        : null,
                'upper_monetary_threshold_minor' => $upperMonetaryThreshold,
                'upper_monetary_threshold_currency' => $upperMonetaryThreshold !== null ? 'GBP' : null,
                'upper_item_count_threshold' => isset($tierData['upper_item_count_threshold']) &&
                    $tierData['upper_item_count_threshold'] !== ''
                        ? (int) $tierData['upper_item_count_threshold']
                        : null,
            ]);

            $root = $tier->qualification()->create([
                'promotion_id' => $promotion->id,
                'context' => QualificationContext::Primary->value,
                'op' => $tierData['qualification_op'] ??
                    QualificationOp::And->value,
                'sort_order' => 0,
            ]);

            $this->createQualificationRules(
                $root,
                is_array($tierData['qualification_rules'] ?? null)
                    ? $tierData['qualification_rules']
                    : [],
                $promotion,
            );
        }

        return $promotion;
    }

    private function currentTeamId(): int
    {
        $tenant = Filament::getTenant();

        if ($tenant === null) {
            throw new RuntimeException(
                'A tenant must be selected to create promotions.',
            );
        }

        return (int) $tenant->getKey();
    }
}
