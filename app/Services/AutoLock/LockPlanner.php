<?php

namespace App\Services\AutoLock;

/**
 * Which free boxes a rule should lock right now. Pure: the boxes and their
 * chances come in, a list of picks goes out, so every rule is unit tested.
 *
 * - random: any free boxes, lowest position first, up to what's owed.
 * - specific: for each figure, its best free box (highest chance) as many
 *   times as that figure is owed.
 * - ranked: work down the priority list, taking every qualifying box for
 *   figure #1 (best first), then #2, until the total owed is reached.
 *
 * A box qualifies for a figure when the trigger's condition holds:
 * chance_reached needs chance >= min_chance, narrowed needs the box to be
 * down to that one figure, sale_opens / restock just need a chance above 0.
 * A box is never picked twice.
 */
final class LockPlanner
{
    private const CERTAIN = 0.999999;

    /**
     * @param  array{kind: 'random'|'specific'|'ranked', owed: int, trigger: string, min_chance: float|null, picks: list<array{sku_id: string, owed: int, priority: int|null}>}  $rule
     * @param  list<array{box_id: int, position: int|null, chances: array<string, float>}>  $freeBoxes
     * @return list<array{box_id: int, sku_id: string|null, chance: float|null}>
     */
    public static function plan(array $rule, array $freeBoxes): array
    {
        if ($rule['owed'] <= 0 || $freeBoxes === []) {
            return [];
        }

        usort($freeBoxes, fn ($a, $b) => ($a['position'] ?? PHP_INT_MAX) <=> ($b['position'] ?? PHP_INT_MAX) ?: $a['box_id'] <=> $b['box_id']);

        if ($rule['kind'] === 'random') {
            return array_map(
                fn ($box) => ['box_id' => $box['box_id'], 'sku_id' => null, 'chance' => null],
                array_slice($freeBoxes, 0, $rule['owed']),
            );
        }

        $taken = [];
        $plan = [];
        $owed = $rule['owed'];

        $picks = $rule['picks'];
        if ($rule['kind'] === 'ranked') {
            usort($picks, fn ($a, $b) => ($a['priority'] ?? PHP_INT_MAX) <=> ($b['priority'] ?? PHP_INT_MAX));
        }

        foreach ($picks as $pick) {
            // Specific: this figure's own count. Ranked: as many as still fit in the total.
            $want = $rule['kind'] === 'specific' ? $pick['owed'] : $owed;

            while ($want > 0 && $owed > 0) {
                $best = self::bestBox($freeBoxes, $taken, $pick['sku_id'], $rule['trigger'], $rule['min_chance']);
                if ($best === null) {
                    break;
                }

                $taken[$best['box_id']] = true;
                $plan[] = ['box_id' => $best['box_id'], 'sku_id' => $pick['sku_id'], 'chance' => $best['chances'][$pick['sku_id']]];
                $want--;
                $owed--;
            }
        }

        return $plan;
    }

    public static function qualifies(float $chance, string $trigger, ?float $minChance): bool
    {
        return match ($trigger) {
            'chance_reached' => $minChance !== null && $chance >= $minChance,
            'narrowed' => $chance >= self::CERTAIN,
            default => $chance > 0,
        };
    }

    private static function bestBox(array $freeBoxes, array $taken, string $skuId, string $trigger, ?float $minChance): ?array
    {
        $best = null;

        foreach ($freeBoxes as $box) {
            $chance = $box['chances'][$skuId] ?? 0.0;
            if (isset($taken[$box['box_id']]) || ! self::qualifies($chance, $trigger, $minChance)) {
                continue;
            }
            // Strictly greater: on a tie the earlier (lower position) box wins.
            if ($best === null || $chance > $best['chances'][$skuId]) {
                $best = $box;
            }
        }

        return $best;
    }
}
