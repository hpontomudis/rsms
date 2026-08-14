<?php

namespace App\Evidence\Contracts;

use App\Evidence\SystemEvidence;
use App\Models\Staff;
use Illuminate\Support\Carbon;

interface EvidenceProvider
{
    public function compute(Staff $staff, Carbon $start, Carbon $end): SystemEvidence;
}
