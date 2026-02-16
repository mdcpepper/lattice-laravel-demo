<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use GuzzleHttp\Client;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Tags\Tag;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $client = $this->makeClient();

        $categories = $this->fetchCategories($client);
        $products = $this->fetchProducts($client);

        if (!empty($categories) && !empty($products)) {
            Category::query()->truncate();
            Product::query()->truncate();
        }

        $categoryIdsBySlug = $this->upsertCategoriesAndGetIds($categories);
        $categoryTagBySlug = $categories->mapWithKeys(
            fn(array $category): array => [
                $category["slug"] => Str::lower($category["name"]),
            ],
        );

        $seedableProducts = $this->buildSeedableProducts(
            $products,
            $categoryIdsBySlug,
            $categoryTagBySlug,
        );

        if ($seedableProducts->isEmpty()) {
            return;
        }

        $this->upsertProducts($seedableProducts);
        $this->syncCategoryTimestampsFromProducts($seedableProducts);
        $this->syncProductTags($seedableProducts);
    }

    private function makeClient(): Client
    {
        return new Client([
            "base_uri" => "https://dummyjson.com/",
            "timeout" => 10,
        ]);
    }

    /**
     * @return Collection<int, array{slug: string, name: string}>
     */
    private function fetchCategories(Client $client): Collection
    {
        $response = $client->request("GET", "products/categories");

        /** @var mixed $payload */
        $payload = json_decode(
            $response->getBody()->getContents(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        if (!is_array($payload)) {
            throw new RuntimeException(
                "DummyJson categories response did not include a valid array.",
            );
        }

        $categories = collect($payload)
            ->map(
                fn(mixed $category): ?array => $this->normalizeCategory(
                    $category,
                ),
            )
            ->filter()
            ->unique("slug")
            ->values();

        if ($categories->isEmpty()) {
            throw new RuntimeException(
                "DummyJson categories response did not include valid category rows.",
            );
        }

        return $categories;
    }

    /**
     * @param mixed $category
     * @return array{slug: string, name: string}|null
     */
    private function normalizeCategory(mixed $category): ?array
    {
        if (!is_array($category)) {
            return null;
        }

        $slug = $category["slug"] ?? null;
        $name = $category["name"] ?? null;

        if (!is_string($name) || $name == "") {
            return null;
        }

        if (!is_string($slug) || $slug == "") {
            $slug = Str::slug($name);
        }

        return [
            "slug" => $slug,
            "name" => $name,
        ];
    }

    /**
     * @param Collection<int, array{slug: string, name: string}> $categories
     * @return Collection<string, int>
     */
    private function upsertCategoriesAndGetIds(
        Collection $categories,
    ): Collection {
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

        return $categoryIdsBySlug;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchProducts(Client $client): array
    {
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

        return $products;
    }

    /**
     * @param array<int, array<string, mixed>> $products
     * @param Collection<string, int> $categoryIdsBySlug
     * @param Collection<string, string> $categoryTagBySlug
     * @return Collection<int, array{product: array<string, mixed>, tags: array<int, string>}>
     */
    private function buildSeedableProducts(
        array $products,
        Collection $categoryIdsBySlug,
        Collection $categoryTagBySlug,
    ): Collection {
        return collect($products)
            ->map(
                fn(array $product): ?array => $this->normalizeProduct(
                    $product,
                    $categoryIdsBySlug,
                    $categoryTagBySlug,
                ),
            )
            ->filter()
            ->values();
    }

    /**
     * @param array<string, mixed> $product
     * @param Collection<string, int> $categoryIdsBySlug
     * @param Collection<string, string> $categoryTagBySlug
     * @return array{product: array<string, mixed>, tags: array<int, string>}|null
     */
    private function normalizeProduct(
        array $product,
        Collection $categoryIdsBySlug,
        Collection $categoryTagBySlug,
    ): ?array {
        $categorySlug = $product["category"] ?? null;

        $rawCategoryId = is_string($categorySlug)
            ? $categoryIdsBySlug->get($categorySlug)
            : null;
        $categoryTag = is_string($categorySlug)
            ? $categoryTagBySlug->get($categorySlug)
            : null;

        $categoryId = is_numeric($rawCategoryId) ? (int) $rawCategoryId : null;
        $timestamps = $this->extractProductTimestamps($product["meta"] ?? null);

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
            $categoryId === null ||
            $timestamps === null
        ) {
            return null;
        }

        $images = $product["images"] ?? [];

        $imageUrl =
            is_array($images) && isset($images[0]) && is_string($images[0])
                ? $images[0]
                : "";

        $thumbUrl =
            isset($product["thumbnail"]) && is_string($product["thumbnail"])
                ? $product["thumbnail"]
                : $imageUrl;

        return [
            "product" => [
                "id" => $product["id"],
                "name" => $product["title"],
                "description" => $product["description"],
                "category_id" => $categoryId,
                "stock" => $product["stock"],
                "price" => (int) round(((float) $product["price"]) * 100),
                "image_url" => $imageUrl,
                "thumb_url" => $thumbUrl,
                "created_at" => $timestamps["created_at"],
                "updated_at" => $timestamps["updated_at"],
            ],
            "tags" => $this->normalizeTags(
                array_merge(
                    is_array($product["tags"] ?? null) ? $product["tags"] : [],
                    is_string($categoryTag) && $categoryTag !== ""
                        ? [$categoryTag]
                        : [],
                ),
            ),
        ];
    }

    /**
     * @param mixed $meta
     * @return array{created_at: CarbonInterface, updated_at: CarbonInterface}|null
     */
    private function extractProductTimestamps(mixed $meta): ?array
    {
        if (!is_array($meta)) {
            return null;
        }

        $createdAt = $this->parseIsoDate($meta["createdAt"] ?? null);
        $updatedAt = $this->parseIsoDate($meta["updatedAt"] ?? null);

        if ($createdAt === null || $updatedAt === null) {
            return null;
        }

        return [
            "created_at" => $createdAt,
            "updated_at" => $updatedAt,
        ];
    }

    private function parseIsoDate(mixed $value): ?CarbonInterface
    {
        if (!is_string($value) || $value === "") {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param mixed $tags
     * @return array<int, string>
     */
    private function normalizeTags(mixed $tags): array
    {
        if (!is_array($tags)) {
            return [];
        }

        return collect($tags)
            ->filter(fn(mixed $tag): bool => is_string($tag) && $tag !== "")
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, array{product: array<string, mixed>, tags: array<int, string>}> $seedableProducts
     */
    private function upsertProducts(Collection $seedableProducts): void
    {
        $productRows = $seedableProducts->pluck("product")->all();

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

    /**
     * @param Collection<int, array{product: array<string, mixed>, tags: array<int, string>}> $seedableProducts
     */
    private function syncCategoryTimestampsFromProducts(
        Collection $seedableProducts,
    ): void {
        $earliestCreatedAtByCategory = $seedableProducts
            ->pluck("product")
            ->groupBy("category_id")
            ->map(function (Collection $products): CarbonInterface {
                /** @var CarbonInterface $earliest */
                $earliest = $products
                    ->pluck("created_at")
                    ->reduce(function (
                        ?CarbonInterface $carry,
                        CarbonInterface $timestamp,
                    ): CarbonInterface {
                        if ($carry === null) {
                            return $timestamp;
                        }

                        return $timestamp->lessThan($carry)
                            ? $timestamp
                            : $carry;
                    });

                return $earliest;
            });

        $earliestCreatedAtByCategory->each(function (
            CarbonInterface $timestamp,
            int|string $categoryId,
        ): void {
            Category::query()
                ->whereKey((int) $categoryId)
                ->update([
                    "created_at" => $timestamp,
                    "updated_at" => $timestamp,
                ]);
        });
    }

    /**
     * @param Collection<int, array{product: array<string, mixed>, tags: array<int, string>}> $seedableProducts
     */
    private function syncProductTags(Collection $seedableProducts): void
    {
        $tagNames = $seedableProducts
            ->flatMap(fn(array $row): array => $row["tags"])
            ->unique()
            ->values();

        if ($tagNames->isEmpty()) {
            return;
        }

        $tagIdsByName = $this->resolveTagIds($tagNames);

        $taggableRows = $this->buildTaggableRows(
            $seedableProducts,
            $tagIdsByName,
        );

        $productIds = $seedableProducts->pluck("product.id")->values()->all();

        DB::table("taggables")
            ->where("taggable_type", Product::class)
            ->whereIn("taggable_id", $productIds)
            ->delete();

        DB::table("taggables")->insertOrIgnore($taggableRows);
    }

    /**
     * @param Collection<int, string> $tagNames
     * @return array<string, int>
     */
    private function resolveTagIds(Collection $tagNames): array
    {
        $locale = Tag::getLocale();
        $names = $tagNames->values()->all();

        $existing = Tag::query()
            ->whereNull("type")
            ->whereIn("name->{$locale}", $names)
            ->get();

        /** @var array<string, int> $tagIdsByName */
        $tagIdsByName = $existing
            ->mapWithKeys(
                fn(Tag $tag): array => [
                    $tag->getTranslation("name", $locale) => (int) $tag->id,
                ],
            )
            ->all();

        $missingNames = $tagNames
            ->filter(fn(string $name): bool => !isset($tagIdsByName[$name]))
            ->values();

        if ($missingNames->isNotEmpty()) {
            $now = now();

            $rows = $missingNames
                ->map(function (string $name) use ($locale, $now): array {
                    $translations = [$locale => $name];
                    $slugs = [$locale => Str::slug($name)];

                    return [
                        "name" => json_encode(
                            $translations,
                            JSON_THROW_ON_ERROR,
                        ),
                        "slug" => json_encode($slugs, JSON_THROW_ON_ERROR),
                        "type" => null,
                        "created_at" => $now,
                        "updated_at" => $now,
                    ];
                })
                ->all();

            DB::table("tags")->insert($rows);

            $inserted = Tag::query()
                ->whereNull("type")
                ->whereIn("name->{$locale}", $missingNames->all())
                ->get();

            foreach ($inserted as $tag) {
                $tagName = $tag->getTranslation("name", $locale);
                $tagIdsByName[$tagName] = (int) $tag->id;
            }
        }

        return $tagIdsByName;
    }

    /**
     * @param Collection<int, array{product: array<string, mixed>, tags: array<int, string>}> $seedableProducts
     * @param array<string, int> $tagIdsByName
     * @return array<int, array{tag_id: int, taggable_id: int, taggable_type: string}>
     */
    private function buildTaggableRows(
        Collection $seedableProducts,
        array $tagIdsByName,
    ): array {
        return $seedableProducts
            ->flatMap(function (array $row) use ($tagIdsByName): array {
                $productId = $row["product"]["id"];
                $tags = $row["tags"];

                return collect($tags)
                    ->map(
                        fn(string $tag): array => [
                            "tag_id" => $tagIdsByName[$tag],
                            "taggable_id" => $productId,
                            "taggable_type" => Product::class,
                        ],
                    )
                    ->all();
            })
            ->values()
            ->all();
    }
}
