<?php

namespace App\View\Components;

use App\Models\Product;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ProductCard extends Component
{
    public function __construct(private Product $product) {}

    public function name(): string
    {
        return $this->product->name;
    }

    public function description(): string
    {
        $description = trim(
            (string) preg_replace("/\s+/", ' ', $this->product->description),
        );

        if ($description === '') {
            return '';
        }

        if (preg_match('/^.*?[.!?](?:\s|$)/u', $description, $matches) === 1) {
            return trim($matches[0]);
        }

        return $description;
    }

    public function price(): string
    {
        return '£'.number_format($this->priceInMinorUnits() / 100, 2);
    }

    public function imageSrc(): ?string
    {
        return $this->thumbnailUrl() ?? $this->imageUrl();
    }

    public function imageSrcset(): ?string
    {
        if (! $this->hasResponsiveSources()) {
            return null;
        }

        return "{$this->thumbnailUrl()} 300w, {$this->imageUrl()} 1000w";
    }

    public function imageSizes(): ?string
    {
        if (! $this->hasResponsiveSources()) {
            return null;
        }

        return '(max-width: 320px) 100vw, 320px';
    }

    public function imageAlt(): string
    {
        return "{$this->name()} image";
    }

    public function imageWidth(): int
    {
        return $this->thumbnailUrl() !== null ? 300 : 1000;
    }

    public function imageHeight(): int
    {
        return $this->thumbnailUrl() !== null ? 300 : 1000;
    }

    public function productId(): int
    {
        return $this->product->id;
    }

    public function hasImage(): bool
    {
        return $this->imageSrc() !== null;
    }

    public function hasResponsiveSources(): bool
    {
        return $this->thumbnailUrl() !== null && $this->imageUrl() !== null;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.product-card');
    }

    private function thumbnailUrl(): ?string
    {
        return $this->product->thumb_url;
    }

    private function imageUrl(): ?string
    {
        return $this->product->image_url;
    }

    private function priceInMinorUnits(): int
    {
        return (int) $this->product->getRawOriginal('price');
    }
}
