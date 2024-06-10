<?php

namespace Transunit\Pass;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeFinder;
use Transunit\Pass;

/**
 * ```
 * + use Prophecy\Prophecy\ObjectProphecy;
 * + use Prophecy\PhpUnit\ProphecyTrait;
 * + use PHPUnit\Framework\TestCase;
 * ```
 */
class ImportMockingLibraryPass implements Pass
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

        $this->importMockingLibraryClasses($node);
    }

    private function importMockingLibraryClasses(Namespace_ $node): void
    {
        array_unshift($node->stmts, new Node\Stmt\Use_([
            new Node\Stmt\UseUse(new Name('Prophecy\Prophecy\ObjectProphecy')),
        ]));

        array_unshift($node->stmts, new Node\Stmt\Use_([
            new Node\Stmt\UseUse(new Name('Prophecy\PhpUnit\ProphecyTrait')),
        ]));

        array_unshift($node->stmts, new Node\Stmt\Use_([
            new Node\Stmt\UseUse(new Name('PHPUnit\Framework\TestCase')),
        ]));
    }
}
