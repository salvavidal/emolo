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
namespace ps_metrics_module_v4_0_6\PhpCsFixer\Fixer\FunctionNotation;

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
final class SingleLineThrowFixer extends AbstractFixer
{
    private const REMOVE_WHITESPACE_AFTER_TOKENS = ['['];
    private const REMOVE_WHITESPACE_AROUND_TOKENS = ['(', [\T_DOUBLE_COLON]];
    private const REMOVE_WHITESPACE_BEFORE_TOKENS = [')', ']', ',', ';'];
    /**
     * {@inheritdoc}
     */
    public function getDefinition() : FixerDefinitionInterface
    {
        return new FixerDefinition('Throwing exception must be done in single line.', [new CodeSample("<?php\nthrow new Exception(\n    'Error.',\n    500\n);\n")]);
    }
    /**
     * {@inheritdoc}
     */
    public function isCandidate(Tokens $tokens) : bool
    {
        return $tokens->isTokenKindFound(\T_THROW);
    }
    /**
     * {@inheritdoc}
     *
     * Must run before BracesFixer, ConcatSpaceFixer.
     */
    public function getPriority() : int
    {
        return 36;
    }
    /**
     * {@inheritdoc}
     */
    protected function applyFix(\SplFileInfo $file, Tokens $tokens) : void
    {
        for ($index = 0, $count = $tokens->count(); $index < $count; ++$index) {
            if (!$tokens[$index]->isGivenKind(\T_THROW)) {
                continue;
            }
            $endCandidateIndex = $tokens->getNextMeaningfulToken($index);
            while (!$tokens[$endCandidateIndex]->equalsAny([')', ']', ',', ';'])) {
                $blockType = Tokens::detectBlockType($tokens[$endCandidateIndex]);
                if (null !== $blockType) {
                    if (Tokens::BLOCK_TYPE_CURLY_BRACE === $blockType['type']) {
                        break;
                    }
                    $endCandidateIndex = $tokens->findBlockEnd($blockType['type'], $endCandidateIndex);
                }
                $endCandidateIndex = $tokens->getNextMeaningfulToken($endCandidateIndex);
            }
            $this->trimNewLines($tokens, $index, $tokens->getPrevMeaningfulToken($endCandidateIndex));
        }
    }
    private function trimNewLines(Tokens $tokens, int $startIndex, int $endIndex) : void
    {
        for ($index = $startIndex; $index < $endIndex; ++$index) {
            $content = $tokens[$index]->getContent();
            if ($tokens[$index]->isGivenKind(\T_COMMENT)) {
                if (\str_starts_with($content, '//')) {
                    $content = '/*' . \substr($content, 2) . ' */';
                    $tokens->clearAt($index + 1);
                } elseif (\str_starts_with($content, '#')) {
                    $content = '/*' . \substr($content, 1) . ' */';
                    $tokens->clearAt($index + 1);
                } elseif (0 !== Preg::match('/\\R/', $content)) {
                    $content = Preg::replace('/\\R/', ' ', $content);
                }
                $tokens[$index] = new Token([\T_COMMENT, $content]);
                continue;
            }
            if (!$tokens[$index]->isGivenKind(\T_WHITESPACE)) {
                continue;
            }
            if (0 === Preg::match('/\\R/', $content)) {
                continue;
            }
            $prevIndex = $tokens->getNonEmptySibling($index, -1);
            if ($this->isPreviousTokenToClear($tokens[$prevIndex])) {
                $tokens->clearAt($index);
                continue;
            }
            $nextIndex = $tokens->getNonEmptySibling($index, 1);
            if ($this->isNextTokenToClear($tokens[$nextIndex]) && !$tokens[$prevIndex]->isGivenKind(\T_FUNCTION)) {
                $tokens->clearAt($index);
                continue;
            }
            $tokens[$index] = new Token([\T_WHITESPACE, ' ']);
        }
    }
    private function isPreviousTokenToClear(Token $token) : bool
    {
        static $tokens = null;
        if (null === $tokens) {
            $tokens = \array_merge(self::REMOVE_WHITESPACE_AFTER_TOKENS, self::REMOVE_WHITESPACE_AROUND_TOKENS);
        }
        return $token->equalsAny($tokens) || $token->isObjectOperator();
    }
    private function isNextTokenToClear(Token $token) : bool
    {
        static $tokens = null;
        if (null === $tokens) {
            $tokens = \array_merge(self::REMOVE_WHITESPACE_AROUND_TOKENS, self::REMOVE_WHITESPACE_BEFORE_TOKENS);
        }
        return $token->equalsAny($tokens) || $token->isObjectOperator();
    }
}
