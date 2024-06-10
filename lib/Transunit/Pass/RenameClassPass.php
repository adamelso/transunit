<?php

namespace Transunit\Pass;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use Transunit\Pass;

/**
 * ```
 * - class TestSubjectSpec extends ObjectBehaviour
 * + class TestSubjectTest extends ObjectBehaviour
 * {
 * ```
 */
class RenameClassPass implements Pass
{
    public function find(NodeFinder $nodeFinder, $ast): array
    {
        return [$nodeFinder->findFirstInstanceOf($ast, Class_::class)];
    }

    public function rewrite(Node $node): void
    {
        if (!$node instanceof Class_) {
            return;
        }

        $this->renameClass($node);
    }

    private function renameClass(Class_ $node): void
    {
        $sourceClassname = $node->name->toString();

        if (substr($sourceClassname, -4) !== 'Spec') {
            return;
        }

        $targetClassname = substr_replace($sourceClassname, '', -4).'Test';

        $node->name = new Node\Identifier($targetClassname);
    }
}
