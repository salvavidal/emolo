<?php

declare (strict_types=1);
namespace ps_metrics_module_v4_0_6\PhpParser\Node\Stmt;

use ps_metrics_module_v4_0_6\PhpParser\Node;
class TraitUse extends Node\Stmt
{
    /** @var Node\Name[] Traits */
    public $traits;
    /** @var TraitUseAdaptation[] Adaptations */
    public $adaptations;
    /**
     * Constructs a trait use node.
     *
     * @param Node\Name[]          $traits      Traits
     * @param TraitUseAdaptation[] $adaptations Adaptations
     * @param array                $attributes  Additional attributes
     */
    public function __construct(array $traits, array $adaptations = [], array $attributes = [])
    {
        $this->attributes = $attributes;
        $this->traits = $traits;
        $this->adaptations = $adaptations;
    }
    public function getSubNodeNames() : array
    {
        return ['traits', 'adaptations'];
    }
    public function getType() : string
    {
        return 'Stmt_TraitUse';
    }
}
