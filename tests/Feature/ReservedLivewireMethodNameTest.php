<?php

namespace Tests\Feature;

use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Structural guard against an entire class of silent, shipped-to-production
 * bug.
 *
 * Livewire's `$wire` proxy keeps an alias map of reserved magic methods
 * (`upload`, `call`, `get`, `set`, `dispatch`, `entangle`, ...). A component
 * method sharing one of those names is UNREACHABLE from the template:
 * `wire:submit="upload"` resolves to Livewire's built-in function, not the
 * component, and typically fails client-side with an opaque JavaScript
 * TypeError while the UI simply appears to do nothing.
 *
 * Nothing in PHP or Blade warns about this -- it is not a syntax error, the
 * method looks perfectly normal, and server-side tests that call the method
 * directly still pass. It cost a live-site debugging session to find once.
 *
 * The list below mirrors `aliases` in livewire/livewire's dist bundle.
 */
class ReservedLivewireMethodNameTest extends TestCase
{
    /** @var string[] */
    private const RESERVED = [
        'on', 'el', 'id', 'js', 'get', 'set', 'refs', 'call', 'hook', 'watch',
        'dirty', 'effect', 'commit', 'errors', 'island', 'upload', 'entangle',
        'dispatch', 'intercept', 'interceptAction', 'interceptMessage',
        'interceptRequest', 'dispatchTo', 'dispatchSelf', 'dispatchEl',
        'dispatchRef', 'removeUpload', 'cancelUpload', 'uploadMultiple',
    ];

    public function test_no_livewire_component_defines_a_reserved_wire_method_name(): void
    {
        $offenders = [];

        foreach ($this->componentClasses() as $class) {
            $reflection = new ReflectionClass($class);

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                // Only methods this project actually declares -- inherited
                // Livewire internals are not ours to rename.
                if ($method->getDeclaringClass()->getName() !== $class) {
                    continue;
                }

                if (in_array($method->getName(), self::RESERVED, true)) {
                    $offenders[] = "{$class}::{$method->getName()}()";
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n", [
            'These Livewire component methods collide with reserved $wire magic',
            'method names and are unreachable from their templates. Rename them:',
            ...$offenders,
        ]));
    }

    /** @return string[] */
    private function componentClasses(): array
    {
        $classes = [];

        foreach (Finder::create()->files()->in(app_path('Livewire'))->name('*.php') as $file) {
            /** @var SplFileInfo $file */
            $relative = str_replace(
                [app_path('Livewire').DIRECTORY_SEPARATOR, '.php', DIRECTORY_SEPARATOR],
                ['', '', '\\'],
                $file->getRealPath(),
            );

            $class = 'App\\Livewire\\'.$relative;

            if (class_exists($class) && is_subclass_of($class, \Livewire\Component::class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
