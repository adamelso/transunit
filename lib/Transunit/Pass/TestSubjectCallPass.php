<?php

namespace Transunit\Pass;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use Transunit\Pass;
use Transunit\Visitor;

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
class TestSubjectCallPass implements Pass
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

        $subNodeTraverser = new NodeTraverser(
            new Visitor\TestSubjectCallVisitor()
        );

        $node->stmts = $subNodeTraverser->traverse($node->stmts);
    }
}
