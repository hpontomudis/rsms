<?php

namespace App\Evidence;

/**
 * The ONLY place a `system_evidence_key` is defined.
 *
 * Deliberately a hardcoded PHP list, not a database table and not arbitrary
 * SQL/JSON configuration -- Rahai has a known, small, controlled set of
 * automated evidence sources, the same reasoning that kept document
 * generation to explicit builders rather than a generic engine. Adding a
 * ninth key is a code change, not a data change, and that is the point: it
 * forces a real provider class to be written and reviewed rather than letting
 * an indicator quietly reference a query nobody vetted.
 *
 * Setting `performance_indicators.system_evidence_key` to anything outside
 * this list is refused at save time (see PerformanceIndicator::booted()).
 */
final class EvidenceRegistry
{
    public const KEYS = [
        'teaching_module_count',
        'daily_journal_count',
        'journal_conducted_count',
        'assessment_count',
        'annual_programme_context',
        'semester_programme_context',
        'annual_programme_contribution',
        'semester_programme_contribution',
    ];

    /** Human-readable, for the indicator-authoring form only -- never used to compute anything. */
    public const DESCRIPTIONS = [
        'teaching_module_count' => 'Teaching modules recorded (assignment responsibility)',
        'daily_journal_count' => 'Daily journals recorded (assignment responsibility)',
        'journal_conducted_count' => 'Sessions actually conducted (journal author)',
        'assessment_count' => 'Assessments recorded (assignment responsibility)',
        'annual_programme_context' => 'Annual Programme exists for the roster (context, not authorship)',
        'semester_programme_context' => 'Semester Programme exists for the roster (context, not authorship)',
        'annual_programme_contribution' => 'Annual Programme edits attributed to this staff member (audit-derived)',
        'semester_programme_contribution' => 'Semester Programme edits attributed to this staff member (audit-derived)',
    ];

    public static function has(string $key): bool
    {
        return in_array($key, self::KEYS, true);
    }
}
