<?php

namespace Transunit\Visitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Finds local collaborators within a test method / spec example that should have their
 * prophecy revealed.
 *
 * For example, in PhpSpec you may have the following:
 *
 *      function it_listens_to_request_events(Event $event)
 *      {
 *          $this->onKernelRequest($event);
 *      }
 *
 * when translated to PHPUnit by the ProphesizeLocalCollaboratorsPass would be:
 *
 *     $event = $this->prophesize(Event::class);
 *     $this->_testSubject->onKernelRequest($event);
 *
 * The visitor will modify this statement to call reveal() on the 'event' collaborator:
 *
 *      $this->_testSubject->onKernelRequest($event->reveal());
 *
 * @see ProphesizeLocalCollaboratorsPass
 */
class RevealCollaboratorVisitor extends NodeVisitorAbstract
{
    /**
     * @var string[] This is the variable name of the local collaborator that should be revealed.
     */
    private $collaborators;

    public function __construct(array $collaborators)
    {
        $this->collaborators = $collaborators;
    }

    public function leaveNode(Node $node)
    {
        if (! $node instanceof Node\Expr\Variable) {
            return $node;
        }

        if (! in_array($node->name, $this->collaborators, true)) {
            return $node;
        }

        /** @see ParentConnectingVisitor which sets this attribute prior to this visitor being invoked by the traverser. */
        if ($node->getAttribute('parent') instanceof Node\Expr\MethodCall) {
            return $node;
        }

        return new Node\Expr\MethodCall(
            $node,
            'reveal'
        );
    }
}
