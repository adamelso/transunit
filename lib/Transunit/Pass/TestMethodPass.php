<?php

namespace Transunit\Pass;

use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\NodeFinder;
use Transunit\Pass;

class TestMethodPass implements Pass
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

        $this->renameSetup($node);
        $this->addPrefix($node);
        $this->prophesizeGlobalCollaborators($node);
        $this->prophesizeLocalCollaborators($node);
    }

    /**
     * public function test_it_should_do_something(): void { ... }
     */
    private function addPrefix(Node\Stmt\ClassMethod $node): void
    {
        $methodName = $node->name->toString();

        if (0 !== strpos($methodName, 'it_')) {
            return;
        }

        $node->name = new Node\Identifier('test_' . $methodName);
        $node->flags = Modifiers::PUBLIC;
        $node->returnType = new Node\Name('void');
    }

    /**
     * protected function setUp(): void { ... }
     */
    private function renameSetup(Node\Stmt\ClassMethod $node): void
    {
        if ($node->name->toString() !== 'let') {
            return;
        }

        $node->name = new Node\Identifier('setUp');
        $node->flags = Modifiers::PROTECTED;
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

            foreach ($node->stmts as $currentStmt) {
                $newStmts[] = $currentStmt;
            }
        }

        $node->stmts = $newStmts;
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

        $localCollaborators = [];

        foreach ($node->params as $param) {
            $variableName = $param->var->name;
            $localCollaborators[$variableName] = $param->type->toString();

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
                    ),
                )
            ));
        }

        $node->params = [];

        $nodeFinder = new NodeFinder();

        // find all variables where the variable name matches the key within $localCollaborators,
        // and modify it to call ->reveal() on the variable:

        $args = $nodeFinder->findInstanceOf($node->stmts, Node\Arg::class);

        $args = array_filter($args, function ($a) use ($localCollaborators) {
            if (!$a->value instanceof Node\Expr\Variable) {
                return false;
            }

            if (!array_key_exists($a->value->name, $localCollaborators)) {
                return false;
            }

            return true;
        });

        if (empty($args)) {
            return;
        }

        // call ->reveal() on the collaborator instance.
        foreach ($args as $arg) {
            $collaboratorVariable = $arg->value;
            $arg->value = new Node\Expr\MethodCall(
                $collaboratorVariable,
                'reveal',
                []
            );
        }
    }
}
