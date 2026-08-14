<?php

namespace App\Evidence;

/**
 * A first-class state, never inferred from a null value.
 *
 * UNAVAILABLE must never be represented as a numeric zero: "no safe User to
 * Staff mapping" is a completely different fact from "zero contributions",
 * and collapsing them would let a real gap in the data quietly read as a
 * real, if unflattering, measurement.
 */
enum EvidenceAvailability: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
}
