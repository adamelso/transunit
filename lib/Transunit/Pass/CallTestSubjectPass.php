<?php

namespace Transunit\Pass;

use PhpParser\Node;
use PhpParser\NodeFinder;
use Transunit\Pass;

/**
 * ```
 *       function it_contracts_out_agents(AgentRepository $agentRepository, EventDispatcher $eventDispatcher, Agent $agent47, ContractEvent $event)
 *       {
 *           $this->agentRepository->find(47)->willReturn($agent47);
 *           $this->eventDispatcher->dispatch($event)->shouldBeCalled();
 *
 * -         $this->contractOut(47)->shouldReturn($agent47);
 * +         $this->_testSubject->contractOut(47)->shouldReturn($agent47);
 *       }
 * ```
 */
class CallTestSubjectPass implements Pass
{
    public function find(NodeFinder $nodeFinder, $ast): array
    {
        return $nodeFinder->findInstanceOf($ast, Node\Stmt\ClassMethod::class);
    }

    public function rewrite(Node $node): void
    {
        if (!$node instanceof Node\Stmt\ClassMethod) {
            return;
        }

        if (in_array($node->name->toString(), ['setUp', 'let'])) {
            return;
        }

        foreach ($node->stmts as $stmt) {
            if (!$stmt instanceof Node\Stmt\Expression) {
                continue;
            }

            $resolvedCall = $stmt->expr;

            if ($resolvedCall instanceof Node\Expr\Assign) {
                $resolvedCall = $resolvedCall->expr;
            }

            if (!$resolvedCall instanceof Node\Expr\MethodCall) {
                continue;
            }

            if ($resolvedCall->var instanceof Node\Expr\MethodCall) {
                $resolvedCall = $resolvedCall->var;
            }

            $this->callMethodOnTestSubject($resolvedCall);
        }
    }

    /**
     * $this->_testSubject->doSomething();
     */
    private function callMethodOnTestSubject(Node\Expr\MethodCall $stmt): void
    {
        if (!$stmt->var instanceof Node\Expr\Variable) {
            return;
        }

        if ('this' !== $stmt->var->name) {
            return;
        }

        if ($stmt->name->toString() === 'prophesize') {
            return;
        }

        $stmt->var = new Node\Expr\PropertyFetch(
            new Node\Expr\Variable('this'),
            '_testSubject'
        );
    }
}
