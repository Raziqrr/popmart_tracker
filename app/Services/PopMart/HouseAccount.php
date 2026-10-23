<?php

namespace App\Services\PopMart;

use App\Models\PopmartAccount;

/**
 * The server's own Pop Mart account (apis.popmart.house_account_id), connected
 * like any other through the extension. It only polls POP NOW grids
 * (AssignSet): it never locks boxes, and its calls draw from the background
 * share of the budget.
 */
class HouseAccount
{
    public function get(): ?PopmartAccount
    {
        $id = config('apis.popmart.house_account_id');

        return $id ? PopmartAccount::find($id) : null;
    }
}
