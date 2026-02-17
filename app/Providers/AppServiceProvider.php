<?php

namespace App\Providers;

use App\Services\Lattice\DirectDiscountPromotionStrategy as LatticeDirectDiscountPromotionStrategy;
use App\Services\Lattice\LatticePromotionFactory;
use App\Services\Lattice\MixAndMatchPromotionStrategy as LatticeMixAndMatchPromotionStrategy;
use App\Services\Lattice\PositionalDiscountPromotionStrategy as LatticePositionalDiscountPromotionStrategy;
use App\Services\ProductQualificationChecker;
use App\Services\PromotionDiscount\DirectDiscountStrategy as PromotionDirectDiscountStrategy;
use App\Services\PromotionDiscount\MixAndMatchStrategy as PromotionMixAndMatchStrategy;
use App\Services\PromotionDiscount\PositionalDiscountStrategy as PromotionPositionalDiscountStrategy;
use App\Services\PromotionDiscount\PromotionDiscountFormatter;
use App\Services\PromotionQualification\DirectDiscountStrategy;
use App\Services\PromotionQualification\MixAndMatchStrategy;
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
                    $this->app->make(DirectDiscountStrategy::class),
                    $this->app->make(MixAndMatchStrategy::class),
                ],
            ),
        );

        $this->app->bind(
            PromotionDiscountFormatter::class,
            fn (): PromotionDiscountFormatter => new PromotionDiscountFormatter([
                $this->app->make(PromotionDirectDiscountStrategy::class),
                $this->app->make(PromotionPositionalDiscountStrategy::class),
                $this->app->make(PromotionMixAndMatchStrategy::class),
            ]),
        );

        $this->app->bind(
            LatticePromotionFactory::class,
            fn (): LatticePromotionFactory => new LatticePromotionFactory([
                $this->app->make(LatticeDirectDiscountPromotionStrategy::class),
                $this->app->make(
                    LatticePositionalDiscountPromotionStrategy::class,
                ),
                $this->app->make(LatticeMixAndMatchPromotionStrategy::class),
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
