<?php

namespace App\Console\Commands;

use App\Models\PopmartAccount;
use App\Services\Api\PopMart\HoldsBoxes;
use App\Services\Api\PopMart\PopMartEndpoint;
use App\Services\Api\PopMart\PopMartException;
use App\Services\Api\RequiresAuthentication;
use App\Services\Locator\Country;
use App\Services\PopMart\PayloadInspector;
use App\Services\PopMart\Sync\Adapters\PopNowSetAdapter;
use App\Services\PopMart\Sync\Adapters\SkuStockAdapter;
use App\Services\PopMart\Sync\Adapters\SpuAdapter;
use App\Services\PopMart\Sync\UnexpectedPayload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Calls one Pop Mart endpoint once and saves the raw response to
 * storage/app/popmart-samples/. With --check, runs the sync adapter for that
 * endpoint over the response and says which expected fields are missing:
 * how the UNCONFIRMED adapters get confirmed or fixed.
 *
 *   php artisan popmart:capture FindSummariesByCollectionId '{"collectionId":"…"}' --check
 *   php artisan popmart:capture CheckSkuStock '{"spuId":"…"}' --check
 *   php artisan popmart:capture AssignSet '{"spuId":"…"}' --account=3 --check
 */
class PopMartCapture extends Command
{
    protected $signature = 'popmart:capture
        {endpoint : Endpoint class name, e.g. CheckSkuStock}
        {payload=[] : JSON request payload}
        {--account= : popmart_accounts.id, for endpoints that need a session}
        {--area=MY : X-Area to send}
        {--check : Run the sync adapter over the response}';

    protected $description = 'Call one Pop Mart endpoint, save the raw response, optionally check the sync adapter against it';

    public function handle(): int
    {
        $class = $this->resolve($this->argument('endpoint'));
        if ($class === null) {
            $this->error('No endpoint class named '.$this->argument('endpoint').' under App\Services\Api\PopMart\Endpoints.');

            return self::FAILURE;
        }

        if (is_subclass_of($class, HoldsBoxes::class)) {
            $this->error("{$class} takes or moves box holds; capture won't call it.");

            return self::FAILURE;
        }

        $payload = json_decode($this->argument('payload'), true);
        if (! is_array($payload)) {
            $this->error('The payload must be a JSON object, e.g. \'{"spuId":"…"}\'.');

            return self::FAILURE;
        }

        /** @var PopMartEndpoint $endpoint */
        $endpoint = new $class(Country::tryFrom($this->option('area')) ?? Country::Malaysia);

        if (is_subclass_of($class, RequiresAuthentication::class)) {
            $account = PopmartAccount::find($this->option('account'));
            if ($account === null) {
                $this->error("{$class} needs a session: pass --account=<popmart_accounts.id>.");

                return self::FAILURE;
            }
            if (class_basename($class) === 'AssignSet' && ! $this->confirm('AssignSet releases every box this account holds. Continue?')) {
                return self::FAILURE;
            }
            $endpoint->withSession($account->session_cookie);
        }

        try {
            $response = $endpoint->send($payload);
        } catch (PopMartException $e) {
            $this->error("{$e->error->value}: {$e->getMessage()}");

            return self::FAILURE;
        }

        $body = $response->json();
        $file = 'popmart-samples/'.class_basename($class).'-'.now()->format('Ymd-His').'.json';
        Storage::put($file, json_encode(['request' => $payload, 'status' => $response->status(), 'response' => $body], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->info("Saved {$file}");

        $this->line('Fields:');
        foreach (array_keys(PayloadInspector::paths(is_array($body) ? $body : [])) as $path) {
            $this->line("  {$path}");
        }

        return $this->option('check') ? $this->check(class_basename($class), $body['json'] ?? $body) : self::SUCCESS;
    }

    private function check(string $endpoint, mixed $json): int
    {
        $area = Country::tryFrom($this->option('area')) ?? Country::Malaysia;

        $adapter = match ($endpoint) {
            'FindSummariesByCollectionId', 'FindDrawSpusByCollectionId', 'FindByIds' => fn () => SpuAdapter::mapList($json, $area),
            'CheckSkuStock' => fn () => SkuStockAdapter::map($json),
            'AssignSet' => fn () => PopNowSetAdapter::mapSet($json ?? []),
            'CheckSetBoxLock' => fn () => PopNowSetAdapter::mapLock($json ?? []),
            default => null,
        };

        if ($adapter === null) {
            $this->warn("No sync adapter reads {$endpoint}; nothing to check.");

            return self::SUCCESS;
        }

        try {
            $mapped = $adapter();
        } catch (UnexpectedPayload $e) {
            $this->error('Adapter check failed: '.$e->getMessage());
            $this->line('Fix the field names in app/Services/PopMart/Sync/Adapters using the fields listed above.');

            return self::FAILURE;
        }

        $first = is_array($mapped) && array_is_list($mapped) ? ($mapped[0] ?? null) : $mapped;
        if (is_array($first)) {
            unset($first['raw_payload']);
        }

        $this->info('Adapter check passed. First mapped item:');
        $this->line(json_encode($first, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->line('Check the values look right (prices in minor units, times, stock), then set CONFIRMED = true on the adapter.');

        return self::SUCCESS;
    }

    /** @return class-string<PopMartEndpoint>|null */
    private function resolve(string $name): ?string
    {
        foreach (glob(app_path('Services/Api/PopMart/Endpoints/*/'.basename($name).'.php')) ?: [] as $file) {
            $class = 'App\\Services\\Api\\PopMart\\Endpoints\\'.basename(dirname($file)).'\\'.basename($file, '.php');
            if (is_subclass_of($class, PopMartEndpoint::class)) {
                return $class;
            }
        }

        return null;
    }
}
