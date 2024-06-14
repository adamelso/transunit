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
 *       $this->_testSubject->onKernelRequest($event);
 *   }
 * ```
 */
class ProphesizeLocalCollaboratorsPass implements Pass
{
    public function find(NodeFinder $nodeFinder, $ast): array
    {
        return [$nodeFinder->findFirstInstanceOf($ast, Node\Stmt\Namespace_::class)];
    }

    public function rewrite(Node $node): void
    {
        if (! $node instanceof Node\Stmt\Namespace_) {
            return;
        }

        $classMethods = (new NodeFinder())->findInstanceOf($node->stmts, Node\Stmt\ClassMethod::class);
        $importStatements = (new NodeFinder())->findInstanceOf($node->stmts, Node\Stmt\Use_::class);

        $importedClassnames = [];

        /** @var Node\UseItem $import */
        foreach ($importStatements as $importLine) {
            // Assumes PSR coding standards are followed with one import per statement.
            [$import] = $importLine->uses;

            $fqcn = $import->name->name;
            // @todo Check for aliases.
            [$importedName] = array_reverse(explode('\\', $fqcn));

            $importedClassnames[] = $importedName;
        }

        foreach ($classMethods as $methodNode) {
            if (!$methodNode instanceof Node\Stmt\ClassMethod) {
                continue;
            }

            if ('let' === $methodNode->name->toString()) {
                continue;
            }

            if ('setUp' === $methodNode->name->toString()) {
                continue;
            }

            $this->prophesizeLocalCollaborators($methodNode, $importedClassnames);
        }
    }

    /**
     * @param string[] $importedFcqns
     */
    private function prophesizeLocalCollaborators(Node\Stmt\ClassMethod $node, array $importedClassnames): void
    {
        foreach ($node->params as $param) {
            $variableName = $param->var->name;
            $classname = $param->type->toString();

            // If there is no use statement for the class
            // assume it is a fully qualified classname likely in the global namespace, such as \DateTime.
            $classnameNode = in_array($classname, $importedClassnames, true)
                ? new Node\Name($classname)
                : new Node\Name\FullyQualified($classname);

            array_unshift($node->stmts, new Node\Stmt\Expression(
                new Node\Expr\Assign(
                    new Node\Expr\Variable($variableName),
                    new Node\Expr\MethodCall(
                        new Node\Expr\Variable('this'),
                        'prophesize',
                        [
                            new Node\Arg(
                                new Node\Expr\ClassConstFetch(
                                    $classnameNode,
                                    'class'
                                )
                            ),
                        ]
                    )
                )
            ));
        }

        $node->params = [];
    }
}
