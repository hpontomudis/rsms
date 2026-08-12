<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Composite foreign keys can only point at columns carrying a unique index.
 * Annual and semester programmes lean on four such anchors so the database --
 * not the service -- can prove that a plan's academic year matches its roster,
 * that a period belongs to that year, and that an item belongs to the pathway
 * its programme selected.
 *
 * All four are redundant on their own (each begins with a primary key) and
 * purely additive.
 */
return new class extends Migration
{
    private const INDEXES = [
        'classes_year_anchor_unique' => 'classes (id, academic_year_id)',
        'teaching_groups_year_anchor_unique' => 'teaching_groups (id, academic_year_id)',
        'academic_periods_year_anchor_unique' => 'academic_periods (id, academic_year_id)',
        'learning_pathway_items_pathway_anchor_unique' => 'learning_pathway_items (id, learning_pathway_id)',
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $name => $target) {
            DB::statement("CREATE UNIQUE INDEX {$name} ON {$target}");
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::INDEXES) as $name) {
            DB::statement("DROP INDEX {$name}");
        }
    }
};
