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
 * ```
 */
class DeclareGlobalCollaboratorPass implements Pass
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
        $useProphecyTrait = array_shift($node->stmts);

        foreach ($classMethodNodes as $classMethodNode) {
            if (!in_array($classMethodNode->name->toString(), ['let', 'setUp'], true)) {
                continue;
            }

            foreach ($classMethodNode->params as $param) {
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
            }
        }

        array_unshift($node->stmts, $useProphecyTrait);
    }
}
