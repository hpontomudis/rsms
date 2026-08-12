<?php

namespace App\Documents;

/**
 * One block of a planning document: either a table or a prose field.
 *
 * Two shapes cover all five documents, which is why there is no template DSL
 * here. A generic document engine would need a schema, a rendering language and
 * a permission model to serve five known cases that a builder each already
 * serves.
 */
class PlanningDocumentSection
{
    /**
     * @param  array<int, string>  $columns  empty for a prose section
     * @param  array<int, array<int, string|null>>  $rows
     */
    public function __construct(
        public readonly string $heading,
        public readonly array $columns = [],
        public readonly array $rows = [],
        public readonly ?string $body = null,
        public readonly ?string $emptyMessage = null,
    ) {}

    public function isTable(): bool
    {
        return $this->columns !== [];
    }

    public function isEmpty(): bool
    {
        return $this->isTable() ? $this->rows === [] : ($this->body === null || trim($this->body) === '');
    }
}
