<?php

declare (strict_types=1);
/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace ps_metrics_module_v4_0_6\PhpCsFixer\Tokenizer\Analyzer\Analysis;

/**
 * @internal
 */
final class TypeAnalysis implements StartEndTokenAwareAnalysis
{
    /**
     * This list contains soft and hard reserved types that can be used or will be used by PHP at some point.
     *
     * More info:
     *
     * @see https://php.net/manual/en/functions.arguments.php#functions.arguments.type-declaration.types
     * @see https://php.net/manual/en/reserved.other-reserved-words.php
     * @see https://php.net/manual/en/language.pseudo-types.php
     *
     * @var array
     */
    private static $reservedTypes = ['array', 'bool', 'callable', 'float', 'int', 'iterable', 'mixed', 'never', 'numeric', 'object', 'resource', 'self', 'string', 'void'];
    /**
     * @var string
     */
    private $name;
    /**
     * @var int
     */
    private $startIndex;
    /**
     * @var int
     */
    private $endIndex;
    /**
     * @var bool
     */
    private $nullable;
    public function __construct(string $name, int $startIndex, int $endIndex)
    {
        $this->name = $name;
        $this->nullable = \false;
        if (\str_starts_with($name, '?')) {
            $this->name = \substr($name, 1);
            $this->nullable = \true;
        }
        $this->startIndex = $startIndex;
        $this->endIndex = $endIndex;
    }
    public function getName() : string
    {
        return $this->name;
    }
    public function getStartIndex() : int
    {
        return $this->startIndex;
    }
    public function getEndIndex() : int
    {
        return $this->endIndex;
    }
    public function isReservedType() : bool
    {
        return \in_array($this->name, self::$reservedTypes, \true);
    }
    public function isNullable() : bool
    {
        return $this->nullable;
    }
}
