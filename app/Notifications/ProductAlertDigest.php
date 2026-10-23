<?php

namespace App\Notifications;

use App\Models\ProductEvent;
use App\Services\Alerts\AlertMessage;
use App\Services\Alerts\AlertPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Many watched products changed in one run (a big drop): one message
 * listing them instead of a burst of separate alerts.
 */
class ProductAlertDigest extends Notification
{
    use Queueable;

    /** @param Collection<int, ProductEvent> $events */
    public function __construct(public readonly Collection $events) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return AlertPreferences::for($notifiable)->channels();
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'digest',
            'title' => "{$this->events->count()} updates on things you watch",
            'items' => $this->events->map(fn (ProductEvent $event) => [
                'event_id' => $event->id,
                'event_type' => $event->type->value,
                'product_id' => $event->product->id,
                'product_name' => $event->product->name,
                'title' => AlertMessage::title($event->type),
                'detail' => AlertMessage::detail($event, $event->product),
                'url' => AlertMessage::url($event->product),
            ])->values()->all(),
            'occurred_at' => $this->events->max('occurred_at')?->toIso8601String(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject("{$this->events->count()} updates on things you watch");

        foreach ($this->events as $event) {
            $mail->line(AlertMessage::title($event->type).": {$event->product->name} — ".AlertMessage::detail($event, $event->product));
        }

        return $mail->action('Open Popmart Tracker', (string) config('app.url'));
    }

    public function toTelegram(object $notifiable): string
    {
        return "{$this->events->count()} updates on things you watch\n"
            .$this->events->map(fn (ProductEvent $event) => '• '.AlertMessage::title($event->type).": {$event->product->name}")->implode("\n");
    }
}
