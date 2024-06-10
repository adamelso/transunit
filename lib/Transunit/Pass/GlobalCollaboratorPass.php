<?php

namespace Transunit\Pass;

use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\NodeFinder;
use Transunit\Pass;

/**
 * ```
 *   class TestSubjectTest extends TestCase
 *   {
 *       use ProphecyTrait;
 *
 * +     private ObjectProphecy|AgentRepository $agentRepository;
 * +     private ObjectProphecy|EventDispatcher $eventDispatcher;
 *
 *       function let(AgentRepository $agentRepository, EventDispatcher $eventDispatcher)
 *       {
 *           $this->beConstructedWith($agentRepository, $eventDispatcher);
 *       }
 *
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
class GlobalCollaboratorPass implements Pass
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

        $this->declareGlobalCollaborators($node);
    }

    /**
     * get the args of a class method named 'let' or 'setUp' and declare them as class properties
     */
    private function declareGlobalCollaborators(Node\Stmt\Class_ $node): void
    {
        $classMethodNodes = $node->getMethods();
        $globalCollaborators = [];

        $useProphecyTrait = array_shift($node->stmts);

        foreach ($classMethodNodes as $classMethodNode) {
            if (!in_array($classMethodNode->name->toString(), ['let', 'setUp'], true)) {
                continue;
            }

            foreach ($classMethodNode->params as $param) {
                $globalCollaborators[$param->var->name] = $param->type;

                array_unshift(
                    $node->stmts,
                    new Node\Stmt\Property(
                        Modifiers::PRIVATE,
                        [
                            new Node\Stmt\PropertyProperty($param->var->name),
                        ],
                        [],
                        // from PHP 8.0
                        new Node\UnionType(
                            [
                                new Node\Identifier('ObjectProphecy'),
                                new Node\Identifier($param->type),
                            ]
                        )
                    )
                );

                $context['collaborators'][$param->var->name] = $param->type;
            }
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

                    if (!$stmt instanceof Node\Stmt\Expression) {
                        continue;
                    }

                    if (!$stmt->expr instanceof Node\Expr\MethodCall) {
                        continue;
                    }

                    // ->willReturn()
                    // ->shouldBeCalled()

                    if (!$stmt->expr->var instanceof Node\Expr\MethodCall) {
                        continue;
                    }

                    // ->stubbedMethod()->willReturn()
                    // ->mockedCall()->shouldBeCalled()

                    if (!$stmt->expr->var->var instanceof Node\Expr\Variable) {
                        continue;
                    }

                    // $collaborator->stubbedMethod()->willReturn()
                    // $collaborator->mockedCall()->shouldBeCalled()

                    if ($stmt->expr->var->var->name !== $param->var->name) {
                        continue;
                    }

                    // $this->collaborator->stubbedMethod()->willReturn()

                    array_pop($newStmts);

                    $newStmts[] = new Node\Stmt\Expression(
                        // ->willReturn()
                        new Node\Expr\MethodCall(
                            // ->stubbedMethod()
                            new Node\Expr\MethodCall(
                                // ->collaborator
                                new Node\Expr\PropertyFetch(
                                    new Node\Expr\Variable('this'),
                                    $param->var->name
                                ),
                                $stmt->expr->var->name,
                                $stmt->expr->var->args
                            ),
                            $stmt->expr->name,
                            $stmt->expr->args
                        )
                    );
                }

                $classMethodNode->stmts = $newStmts;
            }

            $classMethodNode->params = $newParams;
        }

        array_unshift($node->stmts, $useProphecyTrait);
    }
}
