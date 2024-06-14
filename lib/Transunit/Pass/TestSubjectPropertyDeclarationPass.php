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
 * +     private TestSubject $_testSubject;
 * ```
 */
class TestSubjectPropertyDeclarationPass implements Pass
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

        $this->declareTestSubjectAsClassProperty($node);
    }

    private function declareTestSubjectAsClassProperty(Node\Stmt\Class_ $node): void
    {
        $testClassname = $node->name->toString();
        $subjectClassname = substr($testClassname, 0, -4);

        $testSubjectProperty = new Node\Stmt\Property(
            Modifiers::PRIVATE,
            [new Node\Stmt\PropertyProperty('_testSubject')],
            [],
            new Node\Name($subjectClassname)
        );

        array_unshift($node->stmts, $testSubjectProperty);
    }
}
