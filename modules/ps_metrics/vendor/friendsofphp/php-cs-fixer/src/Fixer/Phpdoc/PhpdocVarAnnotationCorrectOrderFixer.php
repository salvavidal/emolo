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
namespace ps_metrics_module_v4_0_6\PhpCsFixer\Fixer\Phpdoc;

use ps_metrics_module_v4_0_6\PhpCsFixer\AbstractFixer;
use ps_metrics_module_v4_0_6\PhpCsFixer\FixerDefinition\CodeSample;
use ps_metrics_module_v4_0_6\PhpCsFixer\FixerDefinition\FixerDefinition;
use ps_metrics_module_v4_0_6\PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use ps_metrics_module_v4_0_6\PhpCsFixer\Preg;
use ps_metrics_module_v4_0_6\PhpCsFixer\Tokenizer\Token;
use ps_metrics_module_v4_0_6\PhpCsFixer\Tokenizer\Tokens;
/**
 * @author Kuba Werłos <werlos@gmail.com>
 */
final class PhpdocVarAnnotationCorrectOrderFixer extends AbstractFixer
{
    public function getDefinition() : FixerDefinitionInterface
    {
        return new FixerDefinition('`@var` and `@type` annotations must have type and name in the correct order.', [new CodeSample('<?php
/** @var $foo int */
$foo = 2 + 2;
')]);
    }
    /**
     * {@inheritdoc}
     *
     * Must run before PhpdocAlignFixer.
     * Must run after AlignMultilineCommentFixer, CommentToPhpdocFixer, PhpdocIndentFixer, PhpdocScalarFixer, PhpdocToCommentFixer, PhpdocTypesFixer.
     */
    public function getPriority() : int
    {
        return 0;
    }
    public function isCandidate(Tokens $tokens) : bool
    {
        return $tokens->isTokenKindFound(\T_DOC_COMMENT);
    }
    protected function applyFix(\SplFileInfo $file, Tokens $tokens) : void
    {
        foreach ($tokens as $index => $token) {
            if (!$token->isGivenKind(\T_DOC_COMMENT)) {
                continue;
            }
            if (\false === \stripos($token->getContent(), '@var') && \false === \stripos($token->getContent(), '@type')) {
                continue;
            }
            $newContent = Preg::replace('/(@(?:type|var)\\s*)(\\$\\S+)(\\h+)([^\\$](?:[^<\\s]|<[^>]*>)*)(\\s|\\*)/i', '$1$4$3$2$5', $token->getContent());
            if ($newContent === $token->getContent()) {
                continue;
            }
            $tokens[$index] = new Token([$token->getId(), $newContent]);
        }
    }
}
