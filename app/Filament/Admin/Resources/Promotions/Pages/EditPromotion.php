<?php

namespace App\Filament\Admin\Resources\Promotions\Pages;

use App\Enums\MixAndMatchDiscountKind;
use App\Enums\PromotionType;
use App\Enums\QualificationContext;
use App\Enums\QualificationOp;
use App\Enums\QualificationRuleKind;
use App\Enums\SimpleDiscountKind;
use App\Enums\TieredThresholdDiscountKind;
use App\Filament\Admin\Resources\Promotions\Concerns\BuildsPromotionFormData;
use App\Filament\Admin\Resources\Promotions\PromotionResource;
use App\Models\DirectDiscountPromotion;
use App\Models\MixAndMatchPromotion;
use App\Models\PositionalDiscountPromotion;
use App\Models\Promotion;
use App\Models\Qualification;
use App\Models\TieredThresholdDiscount;
use App\Models\TieredThresholdPromotion;
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

        $promotion->load(['promotionable', 'qualifications.rules.tags']);

        $promotionable = $promotion->promotionable;

        if ($promotionable instanceof DirectDiscountPromotion) {
            $promotionable->load(['discount', 'qualification.rules.tags']);
        }

        if ($promotionable instanceof MixAndMatchPromotion) {
            $promotionable->load([
                'discount',
                'slots.qualification.rules.tags',
            ]);
        }

        if ($promotionable instanceof PositionalDiscountPromotion) {
            $promotionable->load([
                'discount',
                'qualification.rules.tags',
                'positions',
            ]);
        }

        if ($promotionable instanceof TieredThresholdPromotion) {
            $promotionable->load([
                'tiers.discount',
                'tiers.qualification.rules.tags',
            ]);
        }

        $data['promotion_type'] = match (get_class($promotionable)) {
            DirectDiscountPromotion::class => PromotionType::DirectDiscount
                ->value,
            MixAndMatchPromotion::class => PromotionType::MixAndMatch->value,
            PositionalDiscountPromotion::class => PromotionType::PositionalDiscount->value,
            TieredThresholdPromotion::class => PromotionType::TieredThreshold
                ->value,
        };

        $rawBudget = $promotion->getRawOriginal('monetary_budget');

        $data['monetary_budget'] =
            $rawBudget !== null
                ? number_format((float) $rawBudget / 100, 2, '.', '')
                : null;

        if ($promotionable instanceof DirectDiscountPromotion) {
            $discount = $promotionable->discount;

            $data['discount_kind'] = $discount->kind->value;
            $data['discount_percentage'] = $discount->percentage;
            $data['discount_amount'] =
                $discount->amount !== null
                    ? number_format((float) $discount->amount / 100, 2, '.', '')
                    : null;

            $rootQual = $promotionable->qualification;

            $data['qualification_op'] = $rootQual->op->value;
            $data['qualification_rules'] = $this->flattenRules(
                $rootQual,
                $promotion,
            );
        }

        if ($promotionable instanceof MixAndMatchPromotion) {
            $discount = $promotionable->discount;

            $data['discount_kind'] = $discount->kind->value;
            $data['discount_percentage'] = $discount->percentage;
            $data['discount_amount'] =
                $discount->amount !== null
                    ? number_format((float) $discount->amount / 100, 2, '.', '')
                    : null;

            $data['slots'] = [];

            foreach ($promotionable->slots->sortBy('sort_order') as $slot) {
                $rootQual = $slot->qualification;

                $data['slots'][] = [
                    'min' => $slot->min,
                    'max' => $slot->max,
                    'qualification_op' => $rootQual?->op?->value ?? QualificationOp::And->value,
                    'qualification_rules' => $rootQual instanceof Qualification
                            ? $this->flattenRules($rootQual, $promotion)
                            : [],
                ];
            }
        }

        if ($promotionable instanceof PositionalDiscountPromotion) {
            $discount = $promotionable->discount;

            $data['size'] = $promotionable->size;
            $data['discount_kind'] = $discount->kind->value;
            $data['discount_percentage'] = $discount->percentage;
            $data['discount_amount'] =
                $discount->amount !== null
                    ? number_format((float) $discount->amount / 100, 2, '.', '')
                    : null;

            $rootQual = $promotionable->qualification;

            $data['qualification_op'] =
                $rootQual?->op?->value ?? QualificationOp::And->value;
            $data['qualification_rules'] =
                $rootQual instanceof Qualification
                    ? $this->flattenRules($rootQual, $promotion)
                    : [];

            $data['positions'] = $promotionable->positions
                ->sortBy('sort_order')
                ->values()
                ->map(
                    fn ($position): array => [
                        'position' => $position->position + 1,
                    ],
                )
                ->all();
        }

        if ($promotionable instanceof TieredThresholdPromotion) {
            $data['tiers'] = $promotionable->tiers
                ->sortBy('sort_order')
                ->values()
                ->map(function ($tier) use ($promotion): array {
                    $discount = $tier->discount;
                    $rootQual = $tier->qualification;

                    return [
                        'lower_monetary_threshold' => $tier->lower_monetary_threshold_minor !== null
                                ? number_format(
                                    (float) $tier->lower_monetary_threshold_minor /
                                        100,
                                    2,
                                    '.',
                                    '',
                                )
                                : null,
                        'lower_item_count_threshold' => $tier->lower_item_count_threshold,
                        'upper_monetary_threshold' => $tier->upper_monetary_threshold_minor !== null
                                ? number_format(
                                    (float) $tier->upper_monetary_threshold_minor /
                                        100,
                                    2,
                                    '.',
                                    '',
                                )
                                : null,
                        'upper_item_count_threshold' => $tier->upper_item_count_threshold,
                        'discount_kind' => $discount?->kind?->value,
                        'discount_percentage' => $discount?->percentage,
                        'discount_amount' => $discount?->amount !== null
                                ? number_format(
                                    (float) $discount->amount / 100,
                                    2,
                                    '.',
                                    '',
                                )
                                : null,
                        'qualification_op' => $rootQual?->op?->value ??
                            QualificationOp::And->value,
                        'qualification_rules' => $rootQual instanceof Qualification
                                ? $this->flattenRules($rootQual, $promotion)
                                : [],
                    ];
                })
                ->all();
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

        foreach ($rootQual->rules->sortBy('sort_order') as $rule) {
            if ($rule->kind === QualificationRuleKind::Group) {
                $groupQual = $promotion->qualifications->firstWhere(
                    'id',
                    $rule->group_qualification_id,
                );

                $subRules = [];

                foreach ($groupQual->rules->sortBy('sort_order') as $subRule) {
                    $subRules[] = [
                        'kind' => $subRule->kind->value,
                        'tags' => $subRule->tags
                            ->pluck('name')
                            ->values()
                            ->all(),
                    ];
                }

                $rules[] = [
                    'kind' => QualificationRuleKind::Group->value,
                    'tags' => [],
                    'group_op' => $groupQual->op->value,
                    'group_rules' => $subRules,
                ];
            } else {
                $rules[] = [
                    'kind' => $rule->kind->value,
                    'tags' => $rule->tags->pluck('name')->values()->all(),
                    'group_op' => null,
                    'group_rules' => [],
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
            fn (): Promotion => match ($data['promotion_type']) {
                PromotionType::DirectDiscount->value => $this->updateDirectDiscountPromotion($record, $data),
                PromotionType::MixAndMatch->value => $this->updateMixAndMatchPromotion($record, $data),
                PromotionType::PositionalDiscount->value => $this->updatePositionalDiscountPromotion($record, $data),
                PromotionType::TieredThreshold->value => $this->updateTieredThresholdPromotion($record, $data),
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
            'name' => $data['name'],
            'application_budget' => $data['application_budget'] !== null &&
                $data['application_budget'] !== ''
                    ? (int) $data['application_budget']
                    : null,
            'monetary_budget' => $this->parseMonetaryBudget(
                $data['monetary_budget'] ?? null,
            ),
        ]);

        $promotion->load(['promotionable.discount', 'qualifications.rules']);

        $direct = $promotion->promotionable;
        $discount = $direct->discount;

        $kind = SimpleDiscountKind::from($data['discount_kind']);
        $discountData = ['kind' => $kind->value];

        if ($kind === SimpleDiscountKind::PercentageOff) {
            $discountData['percentage'] = $data['discount_percentage'];
            $discountData['amount'] = null;
            $discountData['amount_currency'] = null;
        } else {
            $discountData['percentage'] = null;
            $discountData['amount'] = $this->parseAmountToMinor(
                $data['discount_amount'] ?? null,
            );
            $discountData['amount_currency'] = 'GBP';
        }

        $discount->update($discountData);

        foreach ($promotion->qualifications as $qual) {
            foreach ($qual->rules as $rule) {
                $rule->delete();
            }
        }
        $promotion->qualifications()->delete();

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
    private function updateMixAndMatchPromotion(
        Promotion $promotion,
        array $data,
    ): Promotion {
        $promotion->update([
            'name' => $data['name'],
            'application_budget' => $data['application_budget'] !== null &&
                $data['application_budget'] !== ''
                    ? (int) $data['application_budget']
                    : null,
            'monetary_budget' => $this->parseMonetaryBudget(
                $data['monetary_budget'] ?? null,
            ),
        ]);

        $promotion->load([
            'promotionable.discount',
            'promotionable.slots.qualification.rules',
            'qualifications.rules',
        ]);

        $mixAndMatch = $promotion->promotionable;
        $discount = $mixAndMatch->discount;

        $kind = MixAndMatchDiscountKind::from($data['discount_kind']);
        $discountData = ['kind' => $kind->value];

        if (
            in_array($kind->value, MixAndMatchDiscountKind::percentageTypes())
        ) {
            $discountData['percentage'] = $data['discount_percentage'] ?? null;
            $discountData['amount'] = null;
            $discountData['amount_currency'] = null;
        } elseif (
            in_array($kind->value, MixAndMatchDiscountKind::amountTypes())
        ) {
            $amount = $this->parseAmountToMinor(
                $data['discount_amount'] ?? null,
            );

            $discountData['percentage'] = null;
            $discountData['amount'] = $amount;
            $discountData['amount_currency'] = $amount !== null ? 'GBP' : null;
        } else {
            $discountData['percentage'] = null;
            $discountData['amount'] = null;
            $discountData['amount_currency'] = null;
        }

        $discount->update($discountData);

        foreach ($promotion->qualifications as $qualification) {
            foreach ($qualification->rules as $rule) {
                $rule->delete();
            }
        }

        $promotion->qualifications()->delete();
        $mixAndMatch->slots()->delete();

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
    private function updatePositionalDiscountPromotion(
        Promotion $promotion,
        array $data,
    ): Promotion {
        $promotion->update([
            'name' => $data['name'],
            'application_budget' => $data['application_budget'] !== null &&
                $data['application_budget'] !== ''
                    ? (int) $data['application_budget']
                    : null,
            'monetary_budget' => $this->parseMonetaryBudget(
                $data['monetary_budget'] ?? null,
            ),
        ]);

        $promotion->load([
            'promotionable.discount',
            'promotionable.positions',
            'qualifications.rules',
        ]);

        $positional = $promotion->promotionable;
        $discount = $positional->discount;
        $size = (int) ($data['size'] ?? 1);

        $positional->update([
            'size' => $size,
        ]);

        $kind = SimpleDiscountKind::from($data['discount_kind']);
        $discountData = ['kind' => $kind->value];

        if ($kind === SimpleDiscountKind::PercentageOff) {
            $discountData['percentage'] = $data['discount_percentage'];
            $discountData['amount'] = null;
            $discountData['amount_currency'] = null;
        } else {
            $discountData['percentage'] = null;
            $discountData['amount'] = $this->parseAmountToMinor(
                $data['discount_amount'] ?? null,
            );
            $discountData['amount_currency'] = 'GBP';
        }

        $discount->update($discountData);

        foreach ($promotion->qualifications as $qualification) {
            foreach ($qualification->rules as $rule) {
                $rule->delete();
            }
        }

        $promotion->qualifications()->delete();

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

        $positional->positions()->delete();

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
    private function updateTieredThresholdPromotion(
        Promotion $promotion,
        array $data,
    ): Promotion {
        $promotion->update([
            'name' => $data['name'],
            'application_budget' => $data['application_budget'] !== null &&
                $data['application_budget'] !== ''
                    ? (int) $data['application_budget']
                    : null,
            'monetary_budget' => $this->parseMonetaryBudget(
                $data['monetary_budget'] ?? null,
            ),
        ]);

        $promotion->load([
            'promotionable.tiers.discount',
            'promotionable.tiers.qualification.rules',
            'qualifications.rules',
        ]);

        $tieredThresholdPromotion = $promotion->promotionable;

        foreach ($promotion->qualifications as $qualification) {
            foreach ($qualification->rules as $rule) {
                $rule->delete();
            }
        }

        $promotion->qualifications()->delete();

        $oldDiscountIds = $tieredThresholdPromotion->tiers
            ->pluck('tiered_threshold_discount_id')
            ->filter(fn (mixed $id): bool => is_numeric($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        $tieredThresholdPromotion->tiers()->delete();

        if ($oldDiscountIds !== []) {
            TieredThresholdDiscount::query()
                ->whereIn('id', $oldDiscountIds)
                ->delete();
        }

        $tiers = $this->normalizeRepeaterRows($data['tiers'] ?? []);

        foreach ($tiers as $sortOrder => $tierData) {
            if (! is_array($tierData)) {
                continue;
            }

            $kind = TieredThresholdDiscountKind::from(
                $tierData['discount_kind'],
            );
            $discountData = ['kind' => $kind->value];

            if (
                in_array(
                    $kind->value,
                    TieredThresholdDiscountKind::percentageTypes(),
                    true,
                )
            ) {
                $discountData['percentage'] =
                    $tierData['discount_percentage'] ?? null;
                $discountData['amount'] = null;
                $discountData['amount_currency'] = null;
            } elseif (
                in_array(
                    $kind->value,
                    TieredThresholdDiscountKind::amountTypes(),
                    true,
                )
            ) {
                $amount = $this->parseAmountToMinor(
                    $tierData['discount_amount'] ?? null,
                );

                $discountData['percentage'] = null;
                $discountData['amount'] = $amount;
                $discountData['amount_currency'] =
                    $amount !== null ? 'GBP' : null;
            } else {
                $discountData['percentage'] = null;
                $discountData['amount'] = null;
                $discountData['amount_currency'] = null;
            }

            $discount = TieredThresholdDiscount::query()->create($discountData);

            $lowerMonetaryThreshold = $this->parseAmountToMinor(
                $tierData['lower_monetary_threshold'] ?? null,
            );
            $upperMonetaryThreshold = $this->parseAmountToMinor(
                $tierData['upper_monetary_threshold'] ?? null,
            );

            $tier = $tieredThresholdPromotion->tiers()->create([
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
}
