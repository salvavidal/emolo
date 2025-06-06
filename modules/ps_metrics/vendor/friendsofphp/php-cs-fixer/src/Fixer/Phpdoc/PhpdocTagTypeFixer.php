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
use ps_metrics_module_v4_0_6\PhpCsFixer\Fixer\ConfigurableFixerInterface;
use ps_metrics_module_v4_0_6\PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use ps_metrics_module_v4_0_6\PhpCsFixer\FixerConfiguration\FixerConfigurationResolverInterface;
use ps_metrics_module_v4_0_6\PhpCsFixer\FixerConfiguration\FixerOptionBuilder;
use ps_metrics_module_v4_0_6\PhpCsFixer\FixerDefinition\CodeSample;
use ps_metrics_module_v4_0_6\PhpCsFixer\FixerDefinition\FixerDefinition;
use ps_metrics_module_v4_0_6\PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use ps_metrics_module_v4_0_6\PhpCsFixer\Preg;
use ps_metrics_module_v4_0_6\PhpCsFixer\Tokenizer\Token;
use ps_metrics_module_v4_0_6\PhpCsFixer\Tokenizer\Tokens;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
final class PhpdocTagTypeFixer extends AbstractFixer implements ConfigurableFixerInterface
{
    private const TAG_REGEX = '/^(?:
        (?<tag>
            (?:@(?<tag_name>.+?)(?:\\s.+)?)
        )
        |
        {(?<inlined_tag>
            (?:@(?<inlined_tag_name>.+?)(?:\\s.+)?)
        )}
    )$/x';
    /**
     * {@inheritdoc}
     */
    public function isCandidate(Tokens $tokens) : bool
    {
        return $tokens->isTokenKindFound(\T_DOC_COMMENT);
    }
    /**
     * {@inheritdoc}
     */
    public function getDefinition() : FixerDefinitionInterface
    {
        return new FixerDefinition('Forces PHPDoc tags to be either regular annotations or inline.', [new CodeSample("<?php\n/**\n * {@api}\n */\n"), new CodeSample("<?php\n/**\n * @inheritdoc\n */\n", ['tags' => ['inheritdoc' => 'inline']])]);
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
    /**
     * {@inheritdoc}
     */
    protected function applyFix(\SplFileInfo $file, Tokens $tokens) : void
    {
        if (0 === \count($this->configuration['tags'])) {
            return;
        }
        $regularExpression = \sprintf('/({?@(?:%s).*?(?:(?=\\s\\*\\/)|(?=\\n)}?))/i', \implode('|', \array_map(static function (string $tag) : string {
            return \preg_quote($tag, '/');
        }, \array_keys($this->configuration['tags']))));
        foreach ($tokens as $index => $token) {
            if (!$token->isGivenKind(\T_DOC_COMMENT)) {
                continue;
            }
            $parts = Preg::split($regularExpression, $token->getContent(), -1, \PREG_SPLIT_DELIM_CAPTURE);
            for ($i = 1, $max = \count($parts) - 1; $i < $max; $i += 2) {
                if (!Preg::match(self::TAG_REGEX, $parts[$i], $matches)) {
                    continue;
                }
                if ('' !== $matches['tag']) {
                    $tag = $matches['tag'];
                    $tagName = $matches['tag_name'];
                } else {
                    $tag = $matches['inlined_tag'];
                    $tagName = $matches['inlined_tag_name'];
                }
                $tagName = \strtolower($tagName);
                if (!isset($this->configuration['tags'][$tagName])) {
                    continue;
                }
                if ('inline' === $this->configuration['tags'][$tagName]) {
                    $parts[$i] = '{' . $tag . '}';
                    continue;
                }
                if (!$this->tagIsSurroundedByText($parts, $i)) {
                    $parts[$i] = $tag;
                }
            }
            $tokens[$index] = new Token([\T_DOC_COMMENT, \implode('', $parts)]);
        }
    }
    /**
     * {@inheritdoc}
     */
    protected function createConfigurationDefinition() : FixerConfigurationResolverInterface
    {
        return new FixerConfigurationResolver([(new FixerOptionBuilder('tags', 'The list of tags to fix'))->setAllowedTypes(['array'])->setAllowedValues([static function (array $value) : bool {
            foreach ($value as $type) {
                if (!\in_array($type, ['annotation', 'inline'], \true)) {
                    throw new InvalidOptionsException("Unknown tag type \"{$type}\".");
                }
            }
            return \true;
        }])->setDefault(['api' => 'annotation', 'author' => 'annotation', 'copyright' => 'annotation', 'deprecated' => 'annotation', 'example' => 'annotation', 'global' => 'annotation', 'inheritDoc' => 'annotation', 'internal' => 'annotation', 'license' => 'annotation', 'method' => 'annotation', 'package' => 'annotation', 'param' => 'annotation', 'property' => 'annotation', 'return' => 'annotation', 'see' => 'annotation', 'since' => 'annotation', 'throws' => 'annotation', 'todo' => 'annotation', 'uses' => 'annotation', 'var' => 'annotation', 'version' => 'annotation'])->setNormalizer(static function (Options $options, $value) : array {
            $normalized = [];
            foreach ($value as $tag => $type) {
                $normalized[\strtolower($tag)] = $type;
            }
            return $normalized;
        })->getOption()]);
    }
    private function tagIsSurroundedByText(array $parts, int $index) : bool
    {
        return Preg::match('/(^|\\R)\\h*[^@\\s]\\N*/', $this->cleanComment($parts[$index - 1])) || Preg::match('/^.*?\\R\\s*[^@\\s]/', $this->cleanComment($parts[$index + 1]));
    }
    private function cleanComment(string $comment) : string
    {
        $comment = Preg::replace('/^\\/\\*\\*|\\*\\/$/', '', $comment);
        return Preg::replace('/(\\R)(\\h*\\*)?\\h*/', '$1', $comment);
    }
}
