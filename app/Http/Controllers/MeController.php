<?php

namespace App\Http\Controllers;

use App\Auth\AuthState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/me: who's logged in and what they can do (App\Auth\AuthState).
 * Inertia pages get the same thing as the shared `auth` prop.
 */
class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(AuthState::for($request->user()));
    }
}
