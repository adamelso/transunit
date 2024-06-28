<?php

namespace Transunit;

use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Stmt\Expression;

class RootMethodCallExtractor
{
    public function locate(Expression $stmt): MethodCall
    {
        if (! $stmt->expr instanceof MethodCall) {
            throw new \DomainException('Cannot locate root variable without a method call chain.');
        }

        $current = $stmt->expr;
        $next = $stmt->expr->var;

        while ($next instanceof MethodCall) {
            $current = $next;
            $next = $next->var;
        }

        return $current;
    }
}
