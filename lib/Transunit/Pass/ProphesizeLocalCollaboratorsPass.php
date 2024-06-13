<?php

namespace Transunit\Pass;

use PhpParser\Node;
use PhpParser\NodeFinder;
use Transunit\Pass;

/**
 * ```
 * - public function test_it_handles_events(Event $event): void
 * + public function test_it_handles_events(): void
 *   {
 * +     $event = $this->prophesize(Event::class);
 *
 * -     $this->_testSubject->onKernelRequest($event);
 * +     $this->_testSubject->onKernelRequest($event->reveal());
 *   }
 * ```
 */
class ProphesizeLocalCollaboratorsPass implements Pass
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

        $this->prophesizeLocalCollaborators($node);
    }

    /**
     * public function test_it_should_do_something(): void {
     *   $methodCollaborator = $this->prophesize(MethodCollaborator::class);
     *   ...
     * }
     */
    private function prophesizeLocalCollaborators(Node\Stmt\ClassMethod $node): void
    {
        if (in_array($node->name->toString(), ['let', 'setUp'], true)) {
            return;
        }

        foreach ($node->params as $param) {
            $variableName = $param->var->name;

            array_unshift($node->stmts, new Node\Stmt\Expression(
                new Node\Expr\Assign(
                    new Node\Expr\Variable($variableName),
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
            ));
        }

        $node->params = [];
    }
}
