<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Illuminate\Database\Seeder;
use RuntimeException;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $client = new Client([
            "base_uri" => "https://dummyjson.com/",
            "timeout" => 10,
        ]);

        $categoriesResponse = $client->request("GET", "products/categories");
        /** @var mixed $categoriesPayload */
        $categoriesPayload = json_decode(
            $categoriesResponse->getBody()->getContents(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        if (!is_array($categoriesPayload)) {
            throw new RuntimeException(
                "DummyJson categories response did not include a valid array.",
            );
        }

        $categories = collect($categoriesPayload)
            ->map(function (mixed $category): ?array {
                if (!is_array($category)) {
                    return null;
                }

                $slug = $category["slug"] ?? null;
                $name = $category["name"] ?? null;

                if (
                    !is_string($slug) ||
                    $slug === "" ||
                    !is_string($name) ||
                    $name === ""
                ) {
                    return null;
                }

                return [
                    "slug" => $slug,
                    "name" => $name,
                ];
            })
            ->filter()
            ->unique("slug")
            ->values();

        if ($categories->isEmpty()) {
            throw new RuntimeException(
                "DummyJson categories response did not include valid category rows.",
            );
        }

        $now = now();

        $categories->each(function (array $category): void {
            Category::query()->updateOrCreate(
                ["slug" => $category["slug"]],
                ["name" => $category["name"]],
            );
        });

        /** @var Collection<string, int> $categoryIdsBySlug */
        $categoryIdsBySlug = Category::query()
            ->whereIn("slug", $categories->pluck("slug"))
            ->pluck("id", "slug");

        $response = $client->request("GET", "products?limit=0");

        /** @var array<string, mixed> $payload */
        $payload = json_decode(
            $response->getBody()->getContents(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $products = $payload["products"] ?? null;

        if (!is_array($products)) {
            throw new RuntimeException(
                "DummyJson response did not include a valid products array.",
            );
        }

        $productsCollection = collect($products);

        $productRows = $productsCollection
            ->map(function (array $product) use (
                $categoryIdsBySlug,
                $now,
            ): ?array {
                $categorySlug = $product["category"] ?? null;

                $rawCategoryId = is_string($categorySlug)
                    ? $categoryIdsBySlug->get($categorySlug)
                    : null;

                $categoryId = is_numeric($rawCategoryId)
                    ? (int) $rawCategoryId
                    : null;

                if (
                    !isset(
                        $product["id"],
                        $product["title"],
                        $product["description"],
                        $product["stock"],
                        $product["price"],
                    ) ||
                    !is_int($product["id"]) ||
                    !is_string($product["title"]) ||
                    !is_string($product["description"]) ||
                    !is_int($product["stock"]) ||
                    !is_numeric($product["price"]) ||
                    $categoryId === null
                ) {
                    return null;
                }

                $images = $product["images"] ?? [];

                $imageUrl =
                    is_array($images) &&
                    isset($images[0]) &&
                    is_string($images[0])
                        ? $images[0]
                        : "";

                $thumbUrl =
                    isset($product["thumbnail"]) &&
                    is_string($product["thumbnail"])
                        ? $product["thumbnail"]
                        : $imageUrl;

                return [
                    "id" => $product["id"],
                    "name" => $product["title"],
                    "description" => $product["description"],
                    "category_id" => $categoryId,
                    "stock" => $product["stock"],
                    "price" => (int) round(((float) $product["price"]) * 100),
                    "image_url" => $imageUrl,
                    "thumb_url" => $thumbUrl,
                    "created_at" => $now,
                    "updated_at" => $now,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($productRows === []) {
            return;
        }

        Product::query()->upsert(
            $productRows,
            ["id"],
            [
                "name",
                "description",
                "category_id",
                "stock",
                "price",
                "image_url",
                "thumb_url",
                "updated_at",
            ],
        );
    }
}
