<?php

namespace Transunit\Visitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class ParentConnectingVisitor extends NodeVisitorAbstract
{
    public function enterNode(Node $node): void
    {
        foreach ($node->getSubNodeNames() as $name) {
            $subNode = $node->{$name};

            if (is_array($subNode)) {
                foreach ($subNode as $childNode) {
                    if ($childNode instanceof Node) {
                        $childNode->setAttribute('parent', $node);
                    }
                }
            } elseif ($subNode instanceof Node) {
                $subNode->setAttribute('parent', $node);
            }
        }
    }
}
