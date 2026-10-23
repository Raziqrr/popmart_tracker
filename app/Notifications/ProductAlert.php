<?php

namespace App\Notifications;

use App\Models\ProductEvent;
use App\Services\Alerts\AlertMessage;
use App\Services\Alerts\AlertPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One watched product changed (restock, sale opened, pre-order, low stock…).
 * Sent by AlertDispatcher to the inbox, plus email / Telegram when the user
 * turned those on. The database payload is AlertItem in resources/js/types/alerts.ts.
 */
class ProductAlert extends Notification
{
    use Queueable;

    public function __construct(public readonly ProductEvent $event) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return AlertPreferences::for($notifiable)->channels();
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $product = $this->event->product;

        return [
            'kind' => 'product',
            'event_id' => $this->event->id,
            'event_type' => $this->event->type->value,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'image_url' => $product->raw_payload['mainImage'] ?? null,
            'title' => AlertMessage::title($this->event->type),
            'detail' => AlertMessage::detail($this->event, $product),
            'url' => AlertMessage::url($product),
            'occurred_at' => $this->event->occurred_at?->toIso8601String(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $product = $this->event->product;
        $title = AlertMessage::title($this->event->type);

        return (new MailMessage)
            ->subject("{$title}: {$product->name}")
            ->line($product->name)
            ->line(AlertMessage::detail($this->event, $product))
            ->action('View on Popmart Tracker', AlertMessage::url($product))
            ->line('You get this because you watch it. Change alerts in your settings.');
    }

    public function toTelegram(object $notifiable): string
    {
        $product = $this->event->product;

        return AlertMessage::title($this->event->type).": {$product->name}\n"
            .AlertMessage::detail($this->event, $product)."\n"
            .AlertMessage::url($product);
    }
}
