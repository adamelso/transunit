<?php

namespace Transunit\Pass;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use Transunit\Pass;
use Transunit\Visitor\ParentConnectingVisitor;
use Transunit\Visitor\RevealCollaboratorVisitor;

class LocalRevealPass implements Pass
{
    public function find(NodeFinder $nodeFinder, $ast): array
    {
        return $nodeFinder->find($ast, function (Node $node) {
            if ($node instanceof Node\Stmt\ClassMethod && !in_array($node->name->toString(), ['setUp', 'let'],true)) {
                return $node;
            }

            return null;
        });
    }

    public function rewrite(Node $node): void
    {
        if (!$node instanceof Node\Stmt\ClassMethod) {
            return;
        }

        $this->reveal($node);
    }

    private function reveal(Node\Stmt\ClassMethod $node): void
    {
        if (in_array($node->name->toString(), ['let', 'setUp'], true)) {
            return;
        }

        $collabs = [];
        foreach ($node->params as $param) {
            $variableName = $param->var->name;
            $collabs[] = $variableName;
        }

        if (empty($collabs)) {
            return;
        }

        $subNodeTraverser = new NodeTraverser(
            new ParentConnectingVisitor(),
            new RevealCollaboratorVisitor($collabs)
        );

        $node->stmts = $subNodeTraverser->traverse($node->stmts);
    }
}
