<?php

namespace Transunit\Pass;

use PhpParser\Node;
use PhpParser\NodeFinder;
use Transunit\Pass;
use Transunit\RootMethodCallExtractor;

/**
 * ```
 *       function it_contracts_out_agents(AgentRepository $agentRepository, EventDispatcher $eventDispatcher, Agent $agent47, ContractEvent $event)
 *       {
 * -         $agentRepository->find(47)->willReturn($agent47);
 * +         $this->agentRepository->find(47)->willReturn($agent47);
 *
 * -         $eventDispatcher->dispatch($event)->shouldBeCalled();
 * +         $this->eventDispatcher->dispatch($event)->shouldBeCalled();
 *
 *           $this->contractOut(47)->shouldReturn($agent47);
 *       }
 * ```
 */
class CallGlobalCollaboratorPass implements Pass
{
    public function find(NodeFinder $nodeFinder, $ast): array
    {
        return [$nodeFinder->findFirstInstanceOf($ast, Node\Stmt\Class_::class)];
    }

    public function rewrite(Node $node): void
    {
        if (!$node instanceof Node\Stmt\Class_) {
            return;
        }

        $this->callGlobalCollaborators($node);
    }

    /**
     * get the args of a class method named 'let' or 'setUp' and declare them as class properties
     */
    private function callGlobalCollaborators(Node\Stmt\Class_ $node): void
    {
        $classMethodNodes = $node->getMethods();
        $globalCollaborators = [];

        foreach ($node->getProperties() as $property) {
            if ('_testSubject' === $property->props[0]->name->toString()) {
                continue;
            }

            $globalCollaborators[$property->props[0]->name->toString()] = true;
        }

        foreach ($classMethodNodes as $classMethodNode) {
            if (in_array($classMethodNode->name->toString(), ['let', 'setUp'], true)) {
                continue;
            }

            $currentParams = $classMethodNode->getParams();
            $newParams = [];

            foreach ($currentParams as $param) {
                if (!array_key_exists($param->var->name, $globalCollaborators)) {
                    $newParams[] = $param;
                    continue;
                }

                // iterate through each expression and find any variables that match
                // $param->var->name and replace them with $this->{param->var->name}
                $stmts = $classMethodNode->getStmts();

                $newStmts = [];

                foreach ($stmts as $i => $stmt) {
                    $newStmts[] = $stmt;

                    if (! $stmt instanceof Node\Stmt\Expression) {
                        continue;
                    }

                    if (! $stmt->expr instanceof Node\Expr\MethodCall) {
                        continue;
                    }

                    /** @see \Prophecy\Prophecy\MethodProphecy */
                    if (! in_array($stmt->expr->name->toString(), [
                        'shouldBeCalled',
                        'shouldNotBeCalled',
                        'shouldBeCalledTimes',
                        'willReturn',
                    ], true)) {
                        continue;
                    }

                    $rootMethodCall = (new RootMethodCallExtractor())->locate($stmt);

                    // At this point the method call is expected to be one of:
                    // - $collaborator->stubbedMethod()->willReturn()
                    // - $collaborator->mockedCall()->shouldBeCalled()
                    // - $collaborator->mockedCall()->shouldBeCalled()->willReturn()

                    if ($rootMethodCall->var->name !== $param->var->name) {
                        continue;
                    }

                    // $this->collaborator->stubbedMethod()->willReturn()

                    $rootMethodCall->var = new Node\Expr\PropertyFetch(
                        new Node\Expr\Variable('this'),
                        $param->var->name
                    );

                    $newStmts[] = $stmt;
                }

                $classMethodNode->stmts = $newStmts;
            }

            $classMethodNode->params = $newParams;
        }
    }
}
