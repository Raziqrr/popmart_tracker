<?php

namespace App\Http\Controllers;

use App\Repositories\PopmartAccountRepository;
use App\Services\Api\PopMart\Endpoints\Auth\GetSession;
use App\Services\Api\PopMart\PopMartError;
use App\Services\Api\PopMart\PopMartException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PopmartAccountController extends Controller
{
    public function __construct(private PopmartAccountRepository $popmartAccounts) {}

    public function connect(Request $request)
    {
        $validated = $request->validate([
            'cookie' => ['required', 'string'],
            'expires_at' => ['nullable', 'date'],
        ]);

        // capability:connect_popmart guarantees a logged-in user.
        $user = $request->user();

        // Only store sessions Pop Mart actually accepts; this also tells us
        // which Pop Mart member the cookie belongs to.
        try {
            $session = (new GetSession)->withSession($validated['cookie'])->fetch();
        } catch (PopMartException $e) {
            if ($e->is(PopMartError::SessionExpired)) {
                throw ValidationException::withMessages([
                    'cookie' => 'Pop Mart did not accept this session. Log in on Pop Mart again, then reconnect.',
                ]);
            }

            throw $e;
        }

        $account = $this->popmartAccounts->connect(
            $user,
            $validated['cookie'],
            $session,
            $validated['expires_at'] ?? null,
        );

        return response()->json([
            'connected' => true,
            'account_id' => $account->id,
            'expires_at' => $account->session_expires_at,
        ]);
    }
}
