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
namespace ps_metrics_module_v4_0_6\PhpCsFixer\Cache;

/**
 * @author Andreas Möller <am@localheinz.com>
 *
 * @internal
 */
interface CacheInterface
{
    public function getSignature() : SignatureInterface;
    public function has(string $file) : bool;
    public function get(string $file) : ?int;
    public function set(string $file, int $hash) : void;
    public function clear(string $file) : void;
    public function toJson() : string;
}
