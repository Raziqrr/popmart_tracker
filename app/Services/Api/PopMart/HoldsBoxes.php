<?php

namespace App\Services\Api\PopMart;

// Marker interface: an endpoint that takes or moves a box hold. The house
// account must never send one (PopMartEndpoint::send() refuses): it only
// watches, and its AssignSet polling would release anything it held.
interface HoldsBoxes {}
