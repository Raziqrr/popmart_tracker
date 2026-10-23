<?php

namespace App\Services\Alerts;

use App\Models\User;
use App\Models\UserSetting;
use App\Services\PopMart\Sync\ProductEventType;

/**
 * A user's alert settings, stored under user_settings.preferences.alerts.
 * Mirrored by AlertSettings in resources/js/types/alerts.ts.
 *
 * - channels: where alerts go. The in-app inbox is always on; email and
 *   Telegram are opt-in (Telegram also needs a chat id).
 * - types: which product events alert. Defaults below.
 */
final class AlertPreferences
{
    /** Events that alert unless the user turns them off. */
    public const DEFAULT_TYPES = [
        ProductEventType::Restocked,
        ProductEventType::SaleStarted,
        ProductEventType::SaleTimeChanged,
        ProductEventType::PreorderOpened,
        ProductEventType::LowStock,
        ProductEventType::PriceDrop,
        ProductEventType::Listed,
    ];

    /** Events a user may choose to be alerted about. Tag changes and price rises are feed-only. */
    public const ALERTABLE_TYPES = [
        ProductEventType::Restocked,
        ProductEventType::SaleStarted,
        ProductEventType::SaleTimeChanged,
        ProductEventType::PreorderOpened,
        ProductEventType::PreorderClosed,
        ProductEventType::LowStock,
        ProductEventType::SoldOut,
        ProductEventType::PriceDrop,
        ProductEventType::Listed,
    ];

    /**
     * @param  list<ProductEventType>  $types
     */
    public function __construct(
        public readonly array $types,
        public readonly bool $email,
        public readonly bool $telegram,
        public readonly ?string $telegramChatId,
    ) {}

    public static function defaults(): self
    {
        return new self(self::DEFAULT_TYPES, email: false, telegram: false, telegramChatId: null);
    }

    public static function for(User $user): self
    {
        $setting = $user->relationLoaded('setting') ? $user->setting : UserSetting::where('user_id', $user->id)->first();

        return self::fromArray($setting?->preferences['alerts'] ?? []);
    }

    public static function fromArray(array $alerts): self
    {
        $defaults = self::defaults();
        $types = isset($alerts['types']) && is_array($alerts['types'])
            ? array_values(array_filter(
                array_map(fn ($type) => ProductEventType::tryFrom((string) $type), $alerts['types']),
                fn (?ProductEventType $type) => $type !== null && in_array($type, self::ALERTABLE_TYPES, true),
            ))
            : $defaults->types;

        $chatId = isset($alerts['telegram_chat_id']) && $alerts['telegram_chat_id'] !== '' ? (string) $alerts['telegram_chat_id'] : null;

        return new self(
            types: $types,
            email: (bool) ($alerts['email'] ?? false),
            telegram: (bool) ($alerts['telegram'] ?? false) && $chatId !== null,
            telegramChatId: $chatId,
        );
    }

    public function wants(ProductEventType $type): bool
    {
        return in_array($type, $this->types, true);
    }

    /** @return list<string> notification channels (Laravel names, plus TelegramChannel) */
    public function channels(): array
    {
        return array_values(array_filter([
            'database',
            $this->email ? 'mail' : null,
            $this->telegram ? TelegramChannel::class : null,
        ]));
    }

    /** @return array{types: list<string>, email: bool, telegram: bool, telegram_chat_id: string|null} */
    public function toArray(): array
    {
        return [
            'types' => array_map(fn (ProductEventType $type) => $type->value, $this->types),
            'email' => $this->email,
            'telegram' => $this->telegram,
            'telegram_chat_id' => $this->telegramChatId,
        ];
    }
}
