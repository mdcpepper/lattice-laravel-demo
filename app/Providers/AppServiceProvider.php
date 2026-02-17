<?php

namespace App\Providers;

use App\Services\Lattice\Promotions\DirectDiscountPromotionStrategy as DirectLatticeFactoryStrategy;
use App\Services\Lattice\Promotions\LatticePromotionFactory;
use App\Services\Lattice\Promotions\MixAndMatchPromotionStrategy as MixAndMatchLatticeFactoryStrategy;
use App\Services\Lattice\Promotions\PositionalDiscountPromotionStrategy as PositionalLatticeFactoryStrategy;
use App\Services\Lattice\Promotions\TieredThresholdPromotionStrategy as TieredThresholdLatticeFactoryStrategy;
use App\Services\Lattice\Stacks\LatticeLayerFactory;
use App\Services\Lattice\Stacks\LatticeLayerOutputFactory;
use App\Services\Lattice\Stacks\LatticeStackFactory;
use App\Services\Lattice\Stacks\PassThroughLayerOutputStrategy;
use App\Services\Lattice\Stacks\PromotionLayerStrategy;
use App\Services\Lattice\Stacks\PromotionStackStrategy;
use App\Services\Lattice\Stacks\SplitLayerOutputStrategy;
use App\Services\ProductQualificationChecker;
use App\Services\PromotionDiscount\DirectDiscountStrategy as DirectFormattingStrategy;
use App\Services\PromotionDiscount\MixAndMatchStrategy as MixAndMatchFormattingStrategy;
use App\Services\PromotionDiscount\PositionalDiscountStrategy as PositionalFormattingStrategy;
use App\Services\PromotionDiscount\PromotionDiscountFormatter;
use App\Services\PromotionDiscount\TieredThresholdStrategy as TieredThresholdFormattingStrategy;
use App\Services\PromotionQualification\DirectDiscountStrategy as DirectQualificationStrategy;
use App\Services\PromotionQualification\MixAndMatchStrategy as MixAndMatchQualificationStrategy;
use App\Services\PromotionQualification\PositionalDiscountStrategy as PositionalQualificationStrategy;
use App\Services\PromotionQualification\TieredThresholdStrategy as TieredThresholdQualificationStrategy;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            ProductQualificationChecker::class,
            fn (): ProductQualificationChecker => new ProductQualificationChecker(
                [
                    $this->app->make(DirectQualificationStrategy::class),
                    $this->app->make(MixAndMatchQualificationStrategy::class),
                    $this->app->make(PositionalQualificationStrategy::class),
                    $this->app->make(
                        TieredThresholdQualificationStrategy::class,
                    ),
                ],
            ),
        );

        $this->app->bind(
            PromotionDiscountFormatter::class,
            fn (): PromotionDiscountFormatter => new PromotionDiscountFormatter([
                $this->app->make(DirectFormattingStrategy::class),
                $this->app->make(MixAndMatchFormattingStrategy::class),
                $this->app->make(PositionalFormattingStrategy::class),
                $this->app->make(TieredThresholdFormattingStrategy::class),
            ]),
        );

        $this->app->bind(
            LatticePromotionFactory::class,
            fn (): LatticePromotionFactory => new LatticePromotionFactory([
                $this->app->make(DirectLatticeFactoryStrategy::class),
                $this->app->make(PositionalLatticeFactoryStrategy::class),
                $this->app->make(MixAndMatchLatticeFactoryStrategy::class),
                $this->app->make(TieredThresholdLatticeFactoryStrategy::class),
            ]),
        );

        $this->app->bind(
            LatticeLayerFactory::class,
            fn (): LatticeLayerFactory => new LatticeLayerFactory([
                $this->app->make(PromotionLayerStrategy::class),
            ]),
        );

        $this->app->bind(
            LatticeLayerOutputFactory::class,
            fn (): LatticeLayerOutputFactory => new LatticeLayerOutputFactory([
                $this->app->make(PassThroughLayerOutputStrategy::class),
                $this->app->make(SplitLayerOutputStrategy::class),
            ]),
        );

        $this->app->bind(
            LatticeStackFactory::class,
            fn (): LatticeStackFactory => new LatticeStackFactory([
                $this->app->make(PromotionStackStrategy::class),
            ]),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
