<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Repositories\PopmartAccountRepository;
use Illuminate\Http\Request;

class PopmartAccountController extends Controller
{
    public function __construct(private PopmartAccountRepository $popmartAccounts) {}

    public function connect(Request $request)
    {
        $validated = $request->validate([
            'cookie' => ['required', 'string'],
            'expires_at' => ['nullable', 'date'],
        ]);

        // TODO: replace with the real authenticated user once login exists.
        // Stubbed to the seeded Test User so the extension flow is testable end-to-end today.
        $user = $request->user() ?? User::firstOrFail();

        $account = $this->popmartAccounts->connect(
            $user,
            $validated['cookie'],
            $validated['expires_at'] ?? null,
        );

        return response()->json([
            'connected' => true,
            'expires_at' => $account->session_expires_at,
        ]);
    }
}
