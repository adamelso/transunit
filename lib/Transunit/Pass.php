<?php

namespace Transunit;

use PhpParser\Node;
use PhpParser\NodeFinder;

interface Pass
{
    /** @param Node[] $ast */
    public function find(NodeFinder $nodeFinder, $ast): array;
    public function rewrite(Node $node): void;
}
