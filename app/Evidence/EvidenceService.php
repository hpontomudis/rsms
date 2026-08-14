<?php

namespace App\Evidence;

use App\Evidence\Contracts\EvidenceProvider;
use App\Evidence\Providers\AnnualProgrammeContextProvider;
use App\Evidence\Providers\AnnualProgrammeContributionProvider;
use App\Evidence\Providers\AssessmentCountProvider;
use App\Evidence\Providers\DailyJournalCountProvider;
use App\Evidence\Providers\JournalConductedCountProvider;
use App\Evidence\Providers\SemesterProgrammeContextProvider;
use App\Evidence\Providers\SemesterProgrammeContributionProvider;
use App\Evidence\Providers\TeachingModuleCountProvider;
use App\Models\PerformanceIndicator;
use App\Models\Staff;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * The one place a `system_evidence_key` is dispatched to its provider.
 *
 * Always computes LIVE. Nothing here writes to `performance_evidence` --
 * that happens only inside PerformanceEvaluationService::finalize(), which
 * calls this and snapshots the result. A draft's evidence panel calls this on
 * every render, which is precisely why nothing is ever stale to "refresh".
 */
class EvidenceService
{
    /** @var array<string, EvidenceProvider> */
    private array $providers;

    public function __construct()
    {
        $this->providers = [
            'teaching_module_count' => new TeachingModuleCountProvider(),
            'daily_journal_count' => new DailyJournalCountProvider(),
            'journal_conducted_count' => new JournalConductedCountProvider(),
            'assessment_count' => new AssessmentCountProvider(),
            'annual_programme_context' => new AnnualProgrammeContextProvider(),
            'semester_programme_context' => new SemesterProgrammeContextProvider(),
            'annual_programme_contribution' => new AnnualProgrammeContributionProvider(),
            'semester_programme_contribution' => new SemesterProgrammeContributionProvider(),
        ];
    }

    public function compute(string $key, Staff $staff, Carbon $start, Carbon $end): SystemEvidence
    {
        if (! isset($this->providers[$key])) {
            throw new InvalidArgumentException("\"{$key}\" is not a registered evidence source.");
        }

        return $this->providers[$key]->compute($staff, $start, $end);
    }

    /** Live evidence for one indicator, or null if it has no system source. */
    public function forIndicator(PerformanceIndicator $indicator, Staff $staff, Carbon $start, Carbon $end): ?SystemEvidence
    {
        if (! $indicator->hasSystemEvidence()) {
            return null;
        }

        return $this->compute($indicator->system_evidence_key, $staff, $start, $end);
    }
}
