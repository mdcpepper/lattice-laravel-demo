<?php

namespace App\Providers;

use App\Services\Lattice\DirectDiscountPromotionStrategy as DirectLatticeFactoryStrategy;
use App\Services\Lattice\LatticePromotionFactory;
use App\Services\Lattice\MixAndMatchPromotionStrategy as MixAndMatchLatticeFactoryStrategy;
use App\Services\Lattice\PositionalDiscountPromotionStrategy as PositionalLatticeFactoryStrategy;
use App\Services\ProductQualificationChecker;
use App\Services\PromotionDiscount\DirectDiscountStrategy as DirectFormattingStrategy;
use App\Services\PromotionDiscount\MixAndMatchStrategy as MixAndMatchFormattingStrategy;
use App\Services\PromotionDiscount\PositionalDiscountStrategy as PositionalFormattingStrategy;
use App\Services\PromotionDiscount\PromotionDiscountFormatter;
use App\Services\PromotionQualification\DirectDiscountStrategy as DirectQualificationStrategy;
use App\Services\PromotionQualification\MixAndMatchStrategy as MixAndMatchQualificationStrategy;
use App\Services\PromotionQualification\PositionalDiscountStrategy as PositionalQualificationStrategy;
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
                ],
            ),
        );

        $this->app->bind(
            PromotionDiscountFormatter::class,
            fn (): PromotionDiscountFormatter => new PromotionDiscountFormatter([
                $this->app->make(DirectFormattingStrategy::class),
                $this->app->make(MixAndMatchFormattingStrategy::class),
                $this->app->make(PositionalFormattingStrategy::class),
            ]),
        );

        $this->app->bind(
            LatticePromotionFactory::class,
            fn (): LatticePromotionFactory => new LatticePromotionFactory([
                $this->app->make(DirectLatticeFactoryStrategy::class),
                $this->app->make(PositionalLatticeFactoryStrategy::class),
                $this->app->make(MixAndMatchLatticeFactoryStrategy::class),
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
