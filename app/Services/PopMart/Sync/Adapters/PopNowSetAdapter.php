<?php

namespace App\Services\PopMart\Sync\Adapters;

use App\Services\Api\PopMart\Endpoints\Draw\AssignSet;
use App\Services\Api\PopMart\Endpoints\Draw\CheckSetBoxLock;

/**
 * POP NOW grids. AssignSet's shape is CONFIRMED (scripts/popnow-lock-test.mjs,
 * 2026-09-29): { setNo, boxes: [{ position, boxNo, status, isLocked }] }.
 * CheckSetBoxLock is only confirmed to return lockRemainingSeconds; isLocked
 * there is a guess.
 */
class PopNowSetAdapter extends Adapter
{
    public const CONFIRMED = true;

    public static function assignEndpoint(): string
    {
        return AssignSet::class;
    }

    public static function lockEndpoint(): string
    {
        return CheckSetBoxLock::class;
    }

    public static function assignRequest(string $spuId, ?string $setNo = null): array
    {
        return $setNo === null ? ['spuId' => $spuId] : ['spuId' => $spuId, 'setNo' => $setNo];
    }

    public static function lockRequest(string $spuId, string $setNo, string $boxNo): array
    {
        return ['spuId' => $spuId, 'setNo' => $setNo, 'boxNo' => $boxNo];
    }

    /**
     * @return array{set_no: string, boxes: list<array{box_no: string, position: int|null, status: string|null, is_locked: bool}>}
     */
    public static function mapSet(array $json): array
    {
        return [
            'set_no' => (string) self::need($json, ['setNo'], 'setNo'),
            'boxes' => array_map(fn (array $box) => [
                'box_no' => (string) self::need($box, ['boxNo'], 'boxNo'),
                'position' => self::pick($box, ['position']),
                'status' => self::pick($box, ['status']),
                'is_locked' => (bool) self::pick($box, ['isLocked'], false),
            ], self::items($json, ['boxes'])),
        ];
    }

    /** @return array{is_locked: bool, lock_remaining_seconds: int|null} */
    public static function mapLock(array $json): array
    {
        $remaining = self::pick($json, ['lockRemainingSeconds']);

        return [
            'is_locked' => (bool) self::pick($json, ['isLocked', 'locked'], $remaining !== null && $remaining > 0),
            'lock_remaining_seconds' => $remaining !== null ? (int) $remaining : null,
        ];
    }
}
