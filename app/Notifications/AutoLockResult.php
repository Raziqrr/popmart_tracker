<?php

namespace App\Notifications;

use App\Models\AutoLockAttempt;
use App\Models\AutoLockRule;
use App\Models\PopNowSet;
use App\Models\UserCheckout;
use App\Services\Alerts\AlertPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * What an auto-lock run did: which boxes are held (and until when), which
 * failed, and whether they're waiting at checkout. Sent by AutoLockRunner to
 * the inbox plus the user's email / Telegram, whatever their alert types,
 * since a held box needs paying for within minutes.
 */
class AutoLockResult extends Notification
{
    use Queueable;

    /** @param Collection<int, AutoLockAttempt> $attempts */
    public function __construct(
        public readonly AutoLockRule $rule,
        public readonly PopNowSet $set,
        public readonly Collection $attempts,
        public readonly ?UserCheckout $checkout,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return AlertPreferences::for($notifiable)->channels();
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'auto_lock',
            'rule_id' => $this->rule->id,
            'product_id' => $this->rule->product_id,
            'product_name' => $this->rule->product->name,
            'set_no' => $this->set->set_no,
            'title' => $this->title(),
            'detail' => $this->detail(),
            'held' => $this->held()->map(fn (AutoLockAttempt $a) => [
                'box_id' => $a->pop_now_box_id,
                'sku_id' => $a->sku_id,
                'chance' => $a->chance_at_lock,
                'lock_expires_at' => $a->lock_expires_at?->toIso8601String(),
            ])->values()->all(),
            'failed' => $this->attempts->where('status', AutoLockAttempt::FAILED)->pluck('error')->values()->all(),
            'checkout_status' => $this->checkout?->status,
            'pay_by' => $this->payBy()?->toIso8601String(),
            'url' => rtrim((string) config('app.url'), '/').'/auto-lock',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title().': '.$this->rule->product->name)
            ->line($this->detail())
            ->action('Open auto-lock', rtrim((string) config('app.url'), '/').'/auto-lock');
    }

    public function toTelegram(object $notifiable): string
    {
        return $this->title().': '.$this->rule->product->name."\n".$this->detail();
    }

    private function held(): Collection
    {
        return $this->attempts->where('status', AutoLockAttempt::LOCKED);
    }

    private function payBy()
    {
        return $this->checkout?->expires_at ?? $this->held()->min('lock_expires_at');
    }

    private function title(): string
    {
        $held = $this->held()->count();

        return $held > 0 ? ($held === 1 ? '1 box held for you' : "{$held} boxes held for you") : 'Auto-lock missed';
    }

    private function detail(): string
    {
        $held = $this->held()->count();
        $failed = $this->attempts->count() - $held;

        if ($held === 0) {
            return "Set {$this->set->set_no}: ".($this->attempts->first()?->error ?? 'no box could be held.');
        }

        $payBy = $this->payBy();
        $parts = ["Set {$this->set->set_no}"];
        $parts[] = $this->checkout?->status === UserCheckout::STATUS_READY
            ? 'at checkout, pay before '.$payBy?->timezone(config('app.timezone'))->format('H:i')
            : 'held until '.$payBy?->timezone(config('app.timezone'))->format('H:i').', check out on Pop Mart before then';
        if ($failed > 0) {
            $parts[] = "{$failed} more couldn't be held";
        }

        return implode(' · ', $parts);
    }
}
