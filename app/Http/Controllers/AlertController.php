<?php

namespace App\Http\Controllers;

use App\Models\UserSetting;
use App\Services\Alerts\AlertPreferences;
use App\Services\PopMart\Sync\ProductEventType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Validation\Rule;

/**
 * The alerts inbox and alert settings (capability:alerts). Shapes are
 * AlertInbox / AlertSettings in resources/js/types/alerts.ts.
 */
class AlertController extends Controller
{
    private const PAGE_SIZE = 50;

    /** GET /api/alerts */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'alerts' => $user->notifications()->latest()->limit(self::PAGE_SIZE)->get()
                ->map(fn (DatabaseNotification $n) => ['id' => $n->id, 'read_at' => $n->read_at, 'created_at' => $n->created_at, ...$n->data]),
        ]);
    }

    /** POST /api/alerts/read: mark some (ids) or all alerts read. */
    public function markRead(Request $request): JsonResponse
    {
        $validated = $request->validate(['ids' => ['nullable', 'array'], 'ids.*' => ['string']]);
        $unread = $request->user()->unreadNotifications();

        if (isset($validated['ids'])) {
            $unread->whereIn('id', $validated['ids']);
        }

        $unread->update(['read_at' => now()]);

        return response()->json(['unread' => $request->user()->unreadNotifications()->count()]);
    }

    /** GET /api/alert-settings */
    public function settings(Request $request): JsonResponse
    {
        return response()->json($this->settingsPayload(AlertPreferences::for($request->user())));
    }

    /** PUT /api/alert-settings */
    public function updateSettings(Request $request): JsonResponse
    {
        $alertable = array_map(fn (ProductEventType $type) => $type->value, AlertPreferences::ALERTABLE_TYPES);

        $validated = $request->validate([
            'types' => ['required', 'array'],
            'types.*' => ['string', Rule::in($alertable)],
            'email' => ['required', 'boolean'],
            'telegram' => ['required', 'boolean'],
            'telegram_chat_id' => ['nullable', 'string', 'max:64', 'required_if:telegram,true'],
        ]);

        $preferences = AlertPreferences::fromArray($validated);
        $setting = UserSetting::firstOrNew(['user_id' => $request->user()->id]);
        $setting->preferences = [...($setting->preferences ?? []), 'alerts' => $preferences->toArray()];
        $setting->save();

        return response()->json($this->settingsPayload($preferences));
    }

    private function settingsPayload(AlertPreferences $preferences): array
    {
        return [
            ...$preferences->toArray(),
            'available_types' => array_map(fn (ProductEventType $type) => $type->value, AlertPreferences::ALERTABLE_TYPES),
            'telegram_available' => (bool) config('services.telegram.bot_token'),
        ];
    }
}
