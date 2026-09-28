<?php

namespace App\Services\Locator;

// Area codes confirmed so far — add more as new regions are confirmed against real API responses.
enum Country: string
{
    case Malaysia = 'MY';
    case Singapore = 'SG';
    case Thailand = 'TH';
}
