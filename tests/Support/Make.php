<?php

namespace Tests\Support;

use App\Models\Collection;
use App\Models\PopmartAccount;
use App\Models\PopNowBox;
use App\Models\PopNowSet;
use App\Models\Product;
use App\Models\Sku;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Support\Str;

/** Builders for the database tests (tests/Feature/Database): rows with just the columns that matter. */
final class Make
{
    public static function user(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    public static function account(User $user, bool $live = true, array $attributes = []): PopmartAccount
    {
        return PopmartAccount::create([
            'user_id' => $user->id,
            'popmart_member_id' => 'member-'.Str::random(6),
            'area' => 'MY',
            'session_cookie' => 'cookie',
            'session_expires_at' => $live ? now()->addDay() : now()->subMinute(),
            'last_synced_at' => now(),
            ...$attributes,
        ]);
    }

    public static function theme(?string $name = null): Theme
    {
        $name ??= 'Theme '.Str::random(5);

        return Theme::create(['id' => (string) Str::uuid(), 'name' => $name, 'slug' => Str::slug($name).'-'.Str::random(4)]);
    }

    public static function collection(?Theme $theme = null, string $area = 'MY'): Collection
    {
        $theme ??= self::theme();

        return Collection::create(['id' => (string) Str::uuid(), 'theme_id' => $theme->id, 'area' => $area, 'name' => $theme->name, 'slug' => $theme->slug]);
    }

    /**
     * A product, optionally with figures (SKU names; ones starting with "secret:" are secrets).
     *
     * @param  list<string>  $figures
     */
    public static function product(array $attributes = [], array $figures = []): Product
    {
        $product = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Product '.Str::random(5),
            'slug' => 'product-'.Str::random(5),
            'price' => 5580,
            'currency' => 'MYR',
            'business_type' => 'shop',
            'area_codes' => ['MY'],
            'tags' => [],
            'raw_payload' => [],
            'last_seen_at' => now(),
            ...$attributes,
        ]);

        foreach ($figures as $name) {
            self::sku($product, $name);
        }

        return $product->load('skus');
    }

    public static function sku(Product $product, string $name = 'Figure'): Sku
    {
        $secret = str_starts_with($name, 'secret:');

        return Sku::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'name' => $secret ? substr($name, 7) : $name,
            'box_type' => $secret ? 'secret' : 'normal',
            'price' => $product->price,
            'currency' => $product->currency,
        ]);
    }

    /**
     * A POP NOW set seen just now, with its boxes numbered 1..$boxes.
     *
     * @return array{0: PopNowSet, 1: list<PopNowBox>}
     */
    public static function set(Product $product, int $boxes, string $setNo = 'S-1'): array
    {
        $set = PopNowSet::create(['product_id' => $product->id, 'set_no' => $setNo, 'total_boxes' => $boxes, 'last_seen_at' => now()]);
        $made = [];

        for ($i = 1; $i <= $boxes; $i++) {
            $made[] = PopNowBox::create([
                'pop_now_set_id' => $set->id,
                'box_no' => sprintf('%02d', $i),
                'position' => $i,
                'status' => 'available',
                'is_locked' => false,
                'last_seen_at' => now(),
            ]);
        }

        return [$set, $made];
    }
}
