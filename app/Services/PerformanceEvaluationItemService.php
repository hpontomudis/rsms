<?php

namespace App\Services;

use App\Models\PerformanceEvaluationItem;
use App\Models\PerformanceEvidence;
use App\Models\PerformanceRatingOption;
use Illuminate\Validation\ValidationException;

/**
 * Writing a human response to one indicator, and manual evidence to support
 * it. THE ONLY place `rating_option_id` / `numeric_value` / `boolean_value` /
 * `narrative_response` are ever written -- system evidence never touches
 * these columns, anywhere, which is the rating/evidence firewall stated as
 * code rather than merely as a rule.
 */
class PerformanceEvaluationItemService
{
    /**
     * Exactly one type-appropriate field is accepted per indicator_type; the
     * other three are refused if supplied non-null, not silently dropped.
     */
    public function respond(PerformanceEvaluationItem $item, array $attributes): PerformanceEvaluationItem
    {
        $this->assertDraft($item);

        $indicator = $item->indicator;
        $fields = ['rating_option_id', 'numeric_value', 'boolean_value', 'narrative_response'];
        $allowed = match ($indicator->indicator_type) {
            'rubric' => 'rating_option_id',
            'numeric' => 'numeric_value',
            'boolean' => 'boolean_value',
            'narrative' => 'narrative_response',
        };

        foreach ($fields as $field) {
            if ($field !== $allowed && array_key_exists($field, $attributes) && $attributes[$field] !== null && $attributes[$field] !== '') {
                $this->fail($field, "{$indicator->name} is a {$indicator->indicator_type} indicator and does not accept a {$field}.");
            }
        }

        $changes = array_fill_keys($fields, null);

        if ($allowed === 'rating_option_id') {
            $optionId = $attributes['rating_option_id'] ?? null;

            if ($optionId !== null && $optionId !== '') {
                $option = PerformanceRatingOption::find($optionId);

                if (! $option || $option->performance_framework_id !== $item->performance_framework_id) {
                    $this->fail('rating_option_id', 'That rating option does not belong to this indicator\'s framework.');
                }

                $changes['rating_option_id'] = $option->id;
            }
        } elseif ($allowed === 'numeric_value') {
            $changes['numeric_value'] = ($attributes['numeric_value'] ?? '') !== '' ? $attributes['numeric_value'] : null;
        } elseif ($allowed === 'boolean_value') {
            $changes['boolean_value'] = array_key_exists('boolean_value', $attributes) ? $attributes['boolean_value'] : null;
        } else {
            $text = $attributes['narrative_response'] ?? null;
            $changes['narrative_response'] = ($text === null || trim($text) === '') ? null : $text;
        }

        $comment = $attributes['evaluator_comment'] ?? null;
        $changes['evaluator_comment'] = ($comment === null || trim($comment) === '') ? null : $comment;

        $item->update($changes);

        return $item->refresh();
    }

    public function addManualEvidence(PerformanceEvaluationItem $item, string $label, string $note): PerformanceEvidence
    {
        $this->assertDraft($item);

        if (trim($note) === '') {
            $this->fail('note', 'Manual evidence needs a note describing what was observed.');
        }

        return PerformanceEvidence::create([
            'performance_evaluation_item_id' => $item->id,
            'source_type' => 'manual',
            'source_key' => null,
            'source_label' => $label,
            'availability' => 'available',
            'note' => $note,
            'captured_at' => now(),
        ]);
    }

    public function removeManualEvidence(PerformanceEvidence $evidence): void
    {
        if (! $evidence->isManual()) {
            $this->fail('source_type', 'Only manual evidence can be removed directly; system evidence is recomputed at finalization.');
        }

        $this->assertDraft($evidence->item);

        $evidence->delete();
    }

    private function assertDraft(PerformanceEvaluationItem $item): void
    {
        if (! $item->evaluation?->isDraft()) {
            $this->fail('status', 'A finalized evaluation\'s responses are immutable.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
