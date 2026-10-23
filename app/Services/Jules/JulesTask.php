<?php

namespace App\Services\Jules;

use Illuminate\Support\Carbon;

class JulesTask
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_DISPATCHED = 'dispatched';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_TIMED_OUT = 'timed_out';

    public const PRIORITY_URGENT = 'urgent';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_LOW = 'low';

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $id,
        public string $prompt,
        public ?int $issueNumber = null,
        public string $branch = 'main',
        public string $priority = self::PRIORITY_NORMAL,
        public string $status = self::STATUS_QUEUED,
        public ?Carbon $createdAt = null,
        public ?Carbon $dispatchedAt = null,
        public ?Carbon $completedAt = null,
        public ?string $error = null,
        public array $metadata = [],
    ) {
        $this->createdAt ??= Carbon::now();
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_DISPATCHED, self::STATUS_RUNNING], true);
    }

    public function isQueued(): bool
    {
        return $this->status === self::STATUS_QUEUED;
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
            self::STATUS_TIMED_OUT,
        ], true);
    }

    public function priorityWeight(): int
    {
        return match (strtolower($this->priority)) {
            self::PRIORITY_URGENT => 4,
            self::PRIORITY_HIGH => 3,
            self::PRIORITY_NORMAL => 2,
            self::PRIORITY_LOW => 1,
            default => 2,
        };
    }

    public function isStale(int $timeoutMinutes = 60, ?Carbon $now = null): bool
    {
        if (! $this->isActive() || $this->dispatchedAt === null) {
            return false;
        }

        $now ??= Carbon::now();

        return $this->dispatchedAt->diffInMinutes($now) >= $timeoutMinutes;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'prompt' => $this->prompt,
            'issue_number' => $this->issueNumber,
            'branch' => $this->branch,
            'priority' => $this->priority,
            'status' => $this->status,
            'created_at' => $this->createdAt?->toIso8601String(),
            'dispatched_at' => $this->dispatchedAt?->toIso8601String(),
            'completed_at' => $this->completedAt?->toIso8601String(),
            'error' => $this->error,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? uniqid('jules-', true)),
            prompt: (string) ($data['prompt'] ?? ''),
            issueNumber: isset($data['issue_number']) && $data['issue_number'] !== '' ? (int) $data['issue_number'] : null,
            branch: (string) ($data['branch'] ?? 'main'),
            priority: (string) ($data['priority'] ?? self::PRIORITY_NORMAL),
            status: (string) ($data['status'] ?? self::STATUS_QUEUED),
            createdAt: isset($data['created_at']) ? Carbon::parse($data['created_at']) : Carbon::now(),
            dispatchedAt: isset($data['dispatched_at']) ? Carbon::parse($data['dispatched_at']) : null,
            completedAt: isset($data['completed_at']) ? Carbon::parse($data['completed_at']) : null,
            error: isset($data['error']) ? (string) $data['error'] : null,
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
        );
    }
}
