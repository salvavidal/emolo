<?php

declare (strict_types=1);
namespace ps_metrics_module_v4_0_6\PhpParser;

use ps_metrics_module_v4_0_6\PhpParser\NodeVisitor\FindingVisitor;
use ps_metrics_module_v4_0_6\PhpParser\NodeVisitor\FirstFindingVisitor;
class NodeFinder
{
    /**
     * Find all nodes satisfying a filter callback.
     *
     * @param Node|Node[] $nodes  Single node or array of nodes to search in
     * @param callable    $filter Filter callback: function(Node $node) : bool
     *
     * @return Node[] Found nodes satisfying the filter callback
     */
    public function find($nodes, callable $filter) : array
    {
        if (!\is_array($nodes)) {
            $nodes = [$nodes];
        }
        $visitor = new FindingVisitor($filter);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($nodes);
        return $visitor->getFoundNodes();
    }
    /**
     * Find all nodes that are instances of a certain class.
     *
     * @param Node|Node[] $nodes Single node or array of nodes to search in
     * @param string      $class Class name
     *
     * @return Node[] Found nodes (all instances of $class)
     */
    public function findInstanceOf($nodes, string $class) : array
    {
        return $this->find($nodes, function ($node) use($class) {
            return $node instanceof $class;
        });
    }
    /**
     * Find first node satisfying a filter callback.
     *
     * @param Node|Node[] $nodes  Single node or array of nodes to search in
     * @param callable    $filter Filter callback: function(Node $node) : bool
     *
     * @return null|Node Found node (or null if none found)
     */
    public function findFirst($nodes, callable $filter)
    {
        if (!\is_array($nodes)) {
            $nodes = [$nodes];
        }
        $visitor = new FirstFindingVisitor($filter);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($nodes);
        return $visitor->getFoundNode();
    }
    /**
     * Find first node that is an instance of a certain class.
     *
     * @param Node|Node[] $nodes  Single node or array of nodes to search in
     * @param string      $class Class name
     *
     * @return null|Node Found node, which is an instance of $class (or null if none found)
     */
    public function findFirstInstanceOf($nodes, string $class)
    {
        return $this->findFirst($nodes, function ($node) use($class) {
            return $node instanceof $class;
        });
    }
}
