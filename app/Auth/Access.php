<?php

namespace App\Auth;

/** The levels a Capability can require, lowest first. */
enum Access: string
{
    /** Anyone, logged in or not. */
    case Guest = 'guest';

    /** Logged in to Popmart Tracker (Fortify). */
    case Member = 'member';

    /** Logged in, with a connected Pop Mart account whose session still works. */
    case PopMart = 'popmart';
}
