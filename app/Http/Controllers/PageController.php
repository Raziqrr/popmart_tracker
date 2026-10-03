<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ListController as Lists;
use App\Http\Presenters\CollectionCards;
use App\Http\Presenters\EventFeed;
use App\Http\Presenters\ProductCards;
use App\Models\Collection;
use App\Models\PopmartAccountPoints;
use App\Models\Product;
use App\Services\Alerts\AlertPreferences;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The site's Inertia pages (resources/js/Pages). Each page's props are typed
 * in resources/js/types/page.ts. Pages read only our own database; the Pop
 * Mart sync keeps it fresh.
 */
class PageController extends Controller
{
    /** Most products a listing page sends; the catalog filters them client-side. */
    private const MAX_PRODUCTS = 500;

    public function __construct(
        private ProductCards $products,
        private CollectionCards $collections,
        private EventFeed $events,
    ) {}

    public function dashboard(Request $request): Response
    {
        $user = $request->user();
        $lists = Lists::listsFor($user);
        $account = $user?->livePopmartAccount();
        $points = $account ? PopmartAccountPoints::where('popmart_account_id', $account->id)->first() : null;

        return Inertia::render('Dashboard', [
            'products' => $this->products->present($this->recentProducts()),
            'collections' => $this->collections->present(Collection::orderByDesc('created_at')->limit(50)->get()),
            'pinned_collections' => $this->collections->present(Collection::whereIn('id', $lists['pinned_collection_ids'])->get()),
            'events' => $this->events->latest(),
            'lucky_points' => $points && $account ? LuckyPointsController::present($points, $account) : null,
        ]);
    }

    public function catalog(): Response
    {
        return Inertia::render('Catalog', ['products' => $this->products->present($this->recentProducts())]);
    }

    public function drops(): Response
    {
        $upcoming = Product::query()
            ->where(fn ($q) => $q->where('sale_start_at', '>=', now()->subDay())->orWhere('is_coming_soon', true))
            ->orderBy('sale_start_at')
            ->limit(self::MAX_PRODUCTS)
            ->get();

        return Inertia::render('Drops', ['products' => $this->products->present($upcoming)]);
    }

    public function watchlist(Request $request): Response
    {
        $lists = Lists::listsFor($request->user());
        $ids = array_values(array_unique([...$lists['pinned_product_ids'], ...$lists['watched_product_ids']]));

        return Inertia::render('Watchlist', ['products' => $this->products->present(Product::whereIn('id', $ids)->get())]);
    }

    public function alerts(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Alerts', [
            'settings' => [
                ...AlertPreferences::for($user)->toArray(),
                'available_types' => array_map(fn ($type) => $type->value, AlertPreferences::ALERTABLE_TYPES),
                'telegram_available' => (bool) config('services.telegram.bot_token'),
            ],
        ]);
    }

    private function recentProducts()
    {
        return Product::orderByDesc('last_seen_at')->limit(self::MAX_PRODUCTS)->get();
    }
}
