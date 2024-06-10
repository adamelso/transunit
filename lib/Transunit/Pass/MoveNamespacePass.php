<?php

namespace Transunit\Pass;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeFinder;
use Transunit\Pass;

/**
 * ```
 * - namespace spec\Foo\Bar;
 * + namespace tests\unit\Foo\Bar;
 * ```
 */
class MoveNamespacePass implements Pass
{
    public function find(NodeFinder $nodeFinder, $ast): array
    {
        return [$nodeFinder->findFirstInstanceOf($ast, Namespace_::class)];
    }

    public function rewrite(Node $node): void
    {
        if (!$node instanceof Namespace_) {
            return;
        }

        $this->moveToNamespace($node);
    }

    private function moveToNamespace(Namespace_ $node): void
    {
        $ns = $node->name->getParts();

        if ($ns[0] === 'spec') {
            array_shift($ns);
            $ns = array_merge(['tests', 'unit'], $ns);
        }

        $node->name = new Name($ns);
    }
}
