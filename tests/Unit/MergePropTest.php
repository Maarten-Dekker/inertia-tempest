<?php

declare(strict_types=1);

namespace Inertia\Tests\Unit;

use Inertia\Props\MergeProp;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MergePropTest extends TestCase
{
    #[Test]
    public function can_invoke_with_a_non_callback(): void
    {
        $mergeProp = new MergeProp(['key' => 'value']);

        $this->assertSame(['key' => 'value'], $mergeProp());
    }

    #[Test]
    public function string_function_names_are_not_invoked(): void
    {
        $mergeProp = new MergeProp('date');

        $this->assertSame('date', $mergeProp());
    }

    #[Test]
    public function appends_by_default(): void
    {
        $mergeProp = new MergeProp([]);

        $this->assertTrue($mergeProp->appendsAtRoot());
        $this->assertFalse($mergeProp->prependsAtRoot());
        $this->assertSame([], $mergeProp->appendsAtPaths());
        $this->assertSame([], $mergeProp->prependsAtPaths());
        $this->assertSame([], $mergeProp->matchesOn());
    }

    #[Test]
    public function prepends(): void
    {
        $mergeProp = new MergeProp([])->prepend();

        $this->assertFalse($mergeProp->appendsAtRoot());
        $this->assertTrue($mergeProp->prependsAtRoot());
        $this->assertSame([], $mergeProp->appendsAtPaths());
        $this->assertSame([], $mergeProp->prependsAtPaths());
        $this->assertSame([], $mergeProp->matchesOn());
    }

    #[Test]
    public function appends_with_nested_merge_paths(): void
    {
        $mergeProp = new MergeProp([])->append('data');

        $this->assertFalse($mergeProp->appendsAtRoot());
        $this->assertFalse($mergeProp->prependsAtRoot());
        $this->assertSame(['data'], $mergeProp->appendsAtPaths());
        $this->assertSame([], $mergeProp->prependsAtPaths());
        $this->assertSame([], $mergeProp->matchesOn());
    }

    #[Test]
    public function appends_with_nested_merge_paths_and_match_on(): void
    {
        $mergeProp = new MergeProp([])->append('data', 'id');

        $this->assertFalse($mergeProp->appendsAtRoot());
        $this->assertFalse($mergeProp->prependsAtRoot());
        $this->assertSame(['data'], $mergeProp->appendsAtPaths());
        $this->assertSame([], $mergeProp->prependsAtPaths());
        $this->assertSame(['data.id'], $mergeProp->matchesOn());
    }

    #[Test]
    public function prepends_with_nested_merge_paths(): void
    {
        $mergeProp = new MergeProp([])->prepend('data');

        $this->assertFalse($mergeProp->appendsAtRoot());
        $this->assertFalse($mergeProp->prependsAtRoot());
        $this->assertSame([], $mergeProp->appendsAtPaths());
        $this->assertSame(['data'], $mergeProp->prependsAtPaths());
        $this->assertSame([], $mergeProp->matchesOn());
    }

    #[Test]
    public function prepends_with_nested_merge_paths_and_match_on(): void
    {
        $mergeProp = new MergeProp([])->prepend('data', 'id');

        $this->assertFalse($mergeProp->appendsAtRoot());
        $this->assertFalse($mergeProp->prependsAtRoot());
        $this->assertSame([], $mergeProp->appendsAtPaths());
        $this->assertSame(['data'], $mergeProp->prependsAtPaths());
        $this->assertSame(['data.id'], $mergeProp->matchesOn());
    }

    #[Test]
    public function append_with_nested_merge_paths_as_array(): void
    {
        $mergeProp = new MergeProp([])->append(['data', 'items']);

        $this->assertFalse($mergeProp->appendsAtRoot());
        $this->assertFalse($mergeProp->prependsAtRoot());
        $this->assertSame(['data', 'items'], $mergeProp->appendsAtPaths());
        $this->assertSame([], $mergeProp->prependsAtPaths());
        $this->assertSame([], $mergeProp->matchesOn());
    }

    #[Test]
    public function append_with_nested_merge_paths_and_match_on_as_array(): void
    {
        $mergeProp = new MergeProp([])->append(['data' => 'id', 'items' => 'uid']);

        $this->assertFalse($mergeProp->appendsAtRoot());
        $this->assertFalse($mergeProp->prependsAtRoot());
        $this->assertSame(['data', 'items'], $mergeProp->appendsAtPaths());
        $this->assertSame([], $mergeProp->prependsAtPaths());
        $this->assertSame(['data.id', 'items.uid'], $mergeProp->matchesOn());
    }

    #[Test]
    public function prepend_with_nested_merge_paths_as_array(): void
    {
        $mergeProp = new MergeProp([])->prepend(['data', 'items']);

        $this->assertFalse($mergeProp->appendsAtRoot());
        $this->assertFalse($mergeProp->prependsAtRoot());
        $this->assertSame([], $mergeProp->appendsAtPaths());
        $this->assertSame(['data', 'items'], $mergeProp->prependsAtPaths());
        $this->assertSame([], $mergeProp->matchesOn());
    }

    #[Test]
    public function prepend_with_nested_merge_paths_and_match_on_as_array(): void
    {
        $mergeProp = new MergeProp([])->prepend(['data' => 'id', 'items' => 'uid']);

        $this->assertFalse($mergeProp->appendsAtRoot());
        $this->assertFalse($mergeProp->prependsAtRoot());
        $this->assertSame([], $mergeProp->appendsAtPaths());
        $this->assertSame(['data', 'items'], $mergeProp->prependsAtPaths());
        $this->assertSame(['data.id', 'items.uid'], $mergeProp->matchesOn());
    }

    #[Test]
    public function mix_of_append_and_prepend_with_nested_merge_paths_and_match_on_as_array(): void
    {
        $mergeProp = new MergeProp([])
            ->append('data')
            ->append('users', 'id')
            ->append(['items' => 'uid', 'posts'])
            ->prepend('categories')
            ->prepend('companies', 'id')
            ->prepend(['tags' => 'name', 'comments']);

        $this->assertFalse($mergeProp->appendsAtRoot());
        $this->assertFalse($mergeProp->prependsAtRoot());
        $this->assertSame(['data', 'users', 'items', 'posts'], $mergeProp->appendsAtPaths());
        $this->assertSame(['categories', 'companies', 'tags', 'comments'], $mergeProp->prependsAtPaths());
        $this->assertSame(['users.id', 'items.uid', 'companies.id', 'tags.name'], $mergeProp->matchesOn());
    }

    #[Test]
    public function can_use_single_string_as_key_to_match_on(): void
    {
        $mergeProp = new MergeProp(['key' => 'value'])->matchOn('key');

        $this->assertSame(['key'], $mergeProp->matchesOn());
    }

    #[Test]
    public function can_use_an_array_of_strings_as_keys_to_match_on(): void
    {
        $mergeProp = new MergeProp(['key' => 'value'])->matchOn(['key', 'anotherKey']);

        $this->assertSame(['key', 'anotherKey'], $mergeProp->matchesOn());
    }
}
