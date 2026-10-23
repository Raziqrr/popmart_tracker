<?php

use App\Models\PopNowBox;
use App\Models\PopNowBoxHint;
use App\Models\PopNowBoxReveal;
use App\Models\PopNowSet;
use App\Models\Sku;
use App\Repositories\PopNowBoxRepository;
use App\Services\Analytics\ExclusionBoxPredictor;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/*
| Builds sets in memory (no database rows): the repository's two lookups are
| stubbed, everything else is the real predictor and repository code.
*/

/** @return array{0: ExclusionBoxPredictor, 1: PopNowSet, 2: EloquentCollection<int, Sku>} */
function predictorFor(int $skuCount, array $hintsByBox, array $revealsByBox = []): array
{
    $skus = new EloquentCollection(array_map(
        fn ($i) => new Sku(['id' => "sku-{$i}", 'product_id' => 'product-1', 'name' => "Figure {$i}"]),
        range(0, $skuCount - 1),
    ));

    $boxes = new EloquentCollection;
    foreach ($hintsByBox as $boxIndex => $excludedSkuIndexes) {
        $box = new PopNowBox(['box_no' => (string) ($boxIndex + 1)]);
        $box->id = $boxIndex + 1;
        $box->setRelation('hints', new EloquentCollection(array_map(
            fn ($i) => new PopNowBoxHint(['sku_id' => "sku-{$i}"]),
            $excludedSkuIndexes,
        )));

        $reveals = new EloquentCollection;
        if (isset($revealsByBox[$boxIndex])) {
            $reveal = new PopNowBoxReveal(['sku_id' => "sku-{$revealsByBox[$boxIndex]}"]);
            $reveal->setRelation('sku', $skus[$revealsByBox[$boxIndex]]);
            $reveals->push($reveal);
        }
        $box->setRelation('reveals', $reveals);

        $boxes->push($box);
    }

    $set = new PopNowSet(['product_id' => 'product-1', 'set_no' => '1']);

    $repository = Mockery::mock(PopNowBoxRepository::class)->makePartial();
    $repository->shouldReceive('boxesForSet')->andReturn($boxes);
    $repository->shouldReceive('nonSecretSkusForProduct')->andReturn($skus);

    return [new ExclusionBoxPredictor($repository), $set, $skus];
}

/**
 * The old algorithm: try every assignment. Reference answer for the DP.
 *
 * @return array{0: array<int, array<string, int>>, 1: int}
 */
function bruteForceCompletions(array $allowed): array
{
    $n = count($allowed);
    $counts = array_fill(0, $n, []);
    $total = 0;
    $used = [];
    $assignment = [];

    $dfs = function (int $i) use (&$dfs, &$counts, &$total, &$used, &$assignment, $allowed, $n) {
        if ($i === $n) {
            $total++;
            foreach ($assignment as $box => $skuId) {
                $counts[$box][$skuId] = ($counts[$box][$skuId] ?? 0) + 1;
            }

            return;
        }
        foreach ($allowed[$i] as $skuId) {
            if (isset($used[$skuId])) {
                continue;
            }
            $used[$skuId] = true;
            $assignment[$i] = $skuId;
            $dfs($i + 1);
            unset($used[$skuId], $assignment[$i]);
        }
    };

    if ($n > 0) {
        $dfs(0);
    }

    return [$counts, $total];
}

it('matches the brute-force count on random sets', function () {
    mt_srand(20261002);

    for ($case = 0; $case < 200; $case++) {
        $skuCount = mt_rand(2, 7);
        $boxCount = mt_rand(1, $skuCount);
        $hintsByBox = [];
        for ($b = 0; $b < $boxCount; $b++) {
            $hintsByBox[$b] = array_values(array_filter(range(0, $skuCount - 1), fn () => mt_rand(0, 99) < 30));
        }

        [$predictor, $set, $skus] = predictorFor($skuCount, $hintsByBox);
        $predictions = $predictor->predictSet($set);

        $allowed = array_map(
            fn ($excluded) => array_values(array_diff($skus->pluck('id')->all(), array_map(fn ($i) => "sku-{$i}", $excluded))),
            $hintsByBox,
        );
        [$counts, $total] = bruteForceCompletions($allowed);

        foreach ($hintsByBox as $b => $_) {
            $prediction = $predictions[$b + 1];

            foreach ($skus as $sku) {
                $expected = $total === 0 ? 0.0 : ($counts[$b][$sku->id] ?? 0) / $total;
                expect($prediction->probabilityFor($sku))->toEqualWithDelta($expected, 1e-9);
            }
        }
    }
});

it('handles a fresh 48-box set instantly', function () {
    [$predictor, $set, $skus] = predictorFor(48, array_fill(0, 48, []));

    $started = microtime(true);
    $predictions = $predictor->predictSet($set);

    // Enumerating used to mean 48! assignments here.
    expect(microtime(true) - $started)->toBeLessThan(1.0)
        ->and($predictions)->toHaveCount(48)
        ->and($predictions[1]->probabilityFor($skus[0]))->toEqualWithDelta(1 / 48, 1e-9);
});

it('confirms a box by elimination', function () {
    // Box 1 rules out figures 1 and 2, so it must be figure 0.
    [$predictor, $set, $skus] = predictorFor(3, [[1, 2], [], []]);

    $prediction = $predictor->predictSet($set)[1];

    expect($prediction->confirmed)->toBeTrue()
        ->and($prediction->confirmedVia)->toBe('elimination')
        ->and($prediction->confirmedSku->id)->toBe('sku-0');
});

it('removes a revealed figure from the other boxes', function () {
    [$predictor, $set, $skus] = predictorFor(3, [[], [], []], revealsByBox: [0 => 0]);

    $predictions = $predictor->predictSet($set);

    expect($predictions[1]->confirmedVia)->toBe('reveal')
        ->and($predictions[2]->probabilityFor($skus[0]))->toBe(0.0)
        ->and($predictions[2]->probabilityFor($skus[1]))->toEqualWithDelta(0.5, 1e-9);
});

it('reads reveals from the eager-loaded relation without querying', function () {
    // The theory from the audit: confirmedSku() used to run reveals()->value()
    // plus Sku::find() for every box even though reveals were already loaded.
    [$predictor, $set] = predictorFor(4, [[], [], [], []], revealsByBox: [0 => 2, 3 => 1]);

    DB::enableQueryLog();
    $predictor->predictSet($set);

    expect(DB::getQueryLog())->toBe([]);
});

/**
 * With as many figures as boxes, every figure is in exactly one box, so its
 * chances across the boxes add up to 1. The per-box fallback breaks that,
 * so this shows the count was exact.
 */
function expectExact($predictions, $skus): void
{
    foreach ($skus as $sku) {
        expect($predictions->sum(fn ($p) => $p->probabilityFor($sku)))->toEqualWithDelta(1.0, 1e-9);
    }
}

/** Hints on the first $hinted boxes, each ruling out a few figures (seeded, so repeatable). */
function realisticHints(int $boxes, int $hinted, int $seed): array
{
    mt_srand($seed);

    return array_map(
        fn ($b) => $b < $hinted ? array_values(array_filter(range(0, $boxes - 1), fn () => mt_rand(0, 99) < 15)) : [],
        range(0, $boxes - 1),
    );
}

it('counts three 16-box sets with every box hinted exactly', function () {
    $started = microtime(true);

    foreach ([1, 2, 3] as $seed) {
        [$predictor, $set, $skus] = predictorFor(16, realisticHints(16, 16, $seed));
        expectExact($predictor->predictSet($set), $skus);
    }

    expect(microtime(true) - $started)->toBeLessThan(10.0);
});

it('counts a 48-box set exactly', function (int $hinted) {
    [$predictor, $set, $skus] = predictorFor(48, realisticHints(48, $hinted, $hinted));

    expectExact($predictor->predictSet($set), $skus);
})->with([0, 6, 12]);

it('falls back to per-box odds when the hints are too irregular to count', function () {
    // 19 boxes, each ruling out a different figure: 2^19 states, over MAX_DP_STATES.
    [$predictor, $set, $skus] = predictorFor(20, array_map(fn ($b) => [$b], range(0, 18)));

    $prediction = $predictor->predictSet($set)[1];

    expect($prediction->probabilityFor($skus[0]))->toBe(0.0)
        ->and($prediction->probabilityFor($skus[1]))->toEqualWithDelta(1 / 19, 1e-9);
});
