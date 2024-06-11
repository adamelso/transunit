<?php

namespace Transunit\Pass;

use PhpParser\Node;
use PhpParser\NodeFinder;
use Transunit\Pass;

/**
 * ```
 * - protected function setUp(AgentRepository $agentRepository, EventDispatcher $eventDispatcher): void
 * + protected function setUp(): void
 *   {
 * +     $this->agentRepository = $this->prophesize(AgentRepository::class);
 * +     $this->eventDispatcher = $this->prophesize(EventDispatcher::class);
 *
 *       // ...
 *   }
 * ```
 */
class ProphesizeGlobalCollaboratorsPass implements Pass
{
    public function find(NodeFinder $nodeFinder, $ast): array
    {
        return $nodeFinder->find($ast, function (Node $node) {
            if (
                $node instanceof Node\Stmt\ClassMethod
                && in_array($node->name->toString(), ['setUp', 'let'], true)
            ) {
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

        $this->prophesizeGlobalCollaborators($node);
    }

    /**
     * protected function setUp(): void {
     *   $this->classCollaborator = $this->prophesize(ClassCollaborator::class);
     * }
     */
    private function prophesizeGlobalCollaborators(Node\Stmt\ClassMethod $node): void
    {
        if (!in_array($node->name->toString(), ['let', 'setUp'], true)) {
            return;
        }

        $newStmts = [];

        while (count($node->params) > 0) {
            $param = array_pop($node->params);

            $newStmts[] = new Node\Stmt\Expression(
                new Node\Expr\Assign(
                // assign class property
                    new Node\Expr\PropertyFetch(
                        new Node\Expr\Variable('this'),
                        $param->var->name
                    ),
                    new Node\Expr\MethodCall(
                        new Node\Expr\Variable('this'),
                        'prophesize',
                        [
                            new Node\Arg(new Node\Expr\ClassConstFetch(
                                new Node\Name($param->type->toString()),
                                'class'
                            )),
                        ]
                    )
                )
            );
        }

        foreach ($node->stmts as $currentStmt) {
            $newStmts[] = $currentStmt;
        }

        $node->stmts = $newStmts;
    }
}
