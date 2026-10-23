<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ListController as Lists;
use App\Http\Presenters\CollectionCards;
use App\Http\Presenters\EventFeed;
use App\Http\Presenters\PopNowSets;
use App\Http\Presenters\ProductCards;
use App\Models\AutoLockAttempt;
use App\Models\AutoLockRule;
use App\Models\Collection;
use App\Models\PopmartAccountPoints;
use App\Models\PopNowSet;
use App\Models\Product;
use App\Services\Alerts\AlertPreferences;
use App\Services\AutoLock\AutoLockRunner;
use App\Services\PopMart\Sync\PopNowSync;
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
        private PopNowSets $popNowSets,
        private AutoLockController $autoLock,
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

    public function popNow(Request $request): Response
    {
        $drawProducts = Product::where('business_type', 'draw')->orderByDesc('last_seen_at')->get();
        $selectedProductId = (string) ($request->query('product')
            ?? $drawProducts->firstWhere('is_coming_soon', false)?->id
            ?? $drawProducts->first()?->id
            ?? '');

        $sets = collect();
        if ($selectedProductId !== '') {
            PopNowSync::markViewed($selectedProductId);

            $sets = PopNowSet::where('product_id', $selectedProductId)
                ->with(['boxes.hints.sku', 'boxes.reveals.sku', 'product.theme', 'product.skus'])
                ->orderByDesc('set_no')
                ->get();
        }

        return Inertia::render('PopNow', [
            'products' => $this->products->present($drawProducts),
            'selected_product_id' => $selectedProductId,
            'sets' => $this->popNowSets->present($sets, $request->user()),
        ]);
    }

    public function autoLock(Request $request): Response
    {
        $user = $request->user();
        $account = $user?->livePopmartAccount();
        $drawProducts = Product::where('business_type', 'draw')->orderByDesc('last_seen_at')->get();

        $rules = AutoLockRule::where('user_id', $user->id)->with(['product', 'picks.sku'])->latest()->get();
        $attempts = AutoLockAttempt::where('user_id', $user->id)->with(['box.set', 'sku'])->latest('attempted_at')->limit(50)->get();

        return Inertia::render('AutoLock', [
            'products' => $this->products->present($drawProducts),
            'rules' => $this->autoLock->presentRules($rules),
            'attempts' => $this->autoLock->presentAttempts($attempts),
            'limits' => [
                'max_lock_seconds' => 300,
            ],
            'enabled_on_server' => AutoLockRunner::enabled(),
            'account' => $account ? [
                'label' => $account->popmart_member_id ?: 'Pop Mart Account',
                'area' => $account->area,
                'session_valid' => $account->sessionIsValid(),
            ] : null,
        ]);
    }

    private function recentProducts()
    {
        return Product::orderByDesc('last_seen_at')->limit(self::MAX_PRODUCTS)->get();
    }
}
