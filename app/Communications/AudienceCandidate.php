<?php

namespace App\Communications;

/**
 * One resolved candidate recipient, before deduplication or materialization.
 * `type` matches exactly one of communication_recipients' four canonical
 * identity columns -- staff/guardian/student/user -- never a bare integer
 * whose meaning depends on context.
 */
final readonly class AudienceCandidate
{
    public function __construct(
        public string $type,
        public int $id,
    ) {}

    public function key(): string
    {
        return "{$this->type}:{$this->id}";
    }
}
