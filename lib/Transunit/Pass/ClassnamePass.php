<?php

namespace Transunit\Pass;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\Node\Stmt\Class_;
use Transunit\Pass;

class ClassnamePass implements Pass
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
        $this->changeExtendedClass($node);
        $this->useProphecyTrait($node);
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

    private function changeExtendedClass(Class_ $node): void
    {
        if ($node->extends->toString() === 'ObjectBehavior') {
            $node->extends = new Node\Name('TestCase');
        }
    }

    private function useProphecyTrait(Class_ $node):  void
    {
        $node->stmts = array_merge(
            [new Node\Stmt\TraitUse([new Node\Name('ProphecyTrait')])],
            $node->stmts
        );
    }
}
