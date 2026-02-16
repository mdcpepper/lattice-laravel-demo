<?php

namespace App\Providers;

use App\Services\ProductQualificationChecker;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
