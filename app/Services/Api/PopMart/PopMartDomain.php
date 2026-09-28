<?php

namespace App\Services\Api\PopMart;

enum PopMartDomain: string
{
    case Ec = 'ec';
    case StorePick = 'storePick';
    case Activity = 'activity';
    case Draw = 'draw';
    case Store = 'store';
    case Cms = 'cms';
}
