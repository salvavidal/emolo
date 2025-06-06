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
use ps_metrics_module_v4_0_6\PhpCsFixer\DocBlock\DocBlock;
use ps_metrics_module_v4_0_6\PhpCsFixer\Fixer\ConfigurableFixerInterface;
use ps_metrics_module_v4_0_6\PhpCsFixer\Fixer\WhitespacesAwareFixerInterface;
use ps_metrics_module_v4_0_6\PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use ps_metrics_module_v4_0_6\PhpCsFixer\FixerConfiguration\FixerConfigurationResolverInterface;
use ps_metrics_module_v4_0_6\PhpCsFixer\FixerConfiguration\FixerOptionBuilder;
use ps_metrics_module_v4_0_6\PhpCsFixer\FixerDefinition\CodeSample;
use ps_metrics_module_v4_0_6\PhpCsFixer\FixerDefinition\FixerDefinition;
use ps_metrics_module_v4_0_6\PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use ps_metrics_module_v4_0_6\PhpCsFixer\Tokenizer\Analyzer\WhitespacesAnalyzer;
use ps_metrics_module_v4_0_6\PhpCsFixer\Tokenizer\CT;
use ps_metrics_module_v4_0_6\PhpCsFixer\Tokenizer\Token;
use ps_metrics_module_v4_0_6\PhpCsFixer\Tokenizer\Tokens;
use ps_metrics_module_v4_0_6\PhpCsFixer\Tokenizer\TokensAnalyzer;
/**
 * @author Gert de Pagter <BackEndTea@gmail.com>
 */
final class PhpdocLineSpanFixer extends AbstractFixer implements WhitespacesAwareFixerInterface, ConfigurableFixerInterface
{
    /**
     * {@inheritdoc}
     */
    public function getDefinition() : FixerDefinitionInterface
    {
        return new FixerDefinition('Changes doc blocks from single to multi line, or reversed. Works for class constants, properties and methods only.', [new CodeSample("<?php\n\nclass Foo{\n    /** @var bool */\n    public \$var;\n}\n"), new CodeSample("<?php\n\nclass Foo{\n    /**\n    * @var bool\n    */\n    public \$var;\n}\n", ['property' => 'single'])]);
    }
    /**
     * {@inheritdoc}
     *
     * Must run before NoSuperfluousPhpdocTagsFixer, PhpdocAlignFixer.
     * Must run after AlignMultilineCommentFixer, CommentToPhpdocFixer, GeneralPhpdocAnnotationRemoveFixer, PhpdocIndentFixer, PhpdocScalarFixer, PhpdocToCommentFixer, PhpdocTypesFixer.
     */
    public function getPriority() : int
    {
        return 7;
    }
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
    protected function createConfigurationDefinition() : FixerConfigurationResolverInterface
    {
        return new FixerConfigurationResolver([(new FixerOptionBuilder('const', 'Whether const blocks should be single or multi line'))->setAllowedValues(['single', 'multi', null])->setDefault('multi')->getOption(), (new FixerOptionBuilder('property', 'Whether property doc blocks should be single or multi line'))->setAllowedValues(['single', 'multi', null])->setDefault('multi')->getOption(), (new FixerOptionBuilder('method', 'Whether method doc blocks should be single or multi line'))->setAllowedValues(['single', 'multi', null])->setDefault('multi')->getOption()]);
    }
    protected function applyFix(\SplFileInfo $file, Tokens $tokens) : void
    {
        $analyzer = new TokensAnalyzer($tokens);
        foreach ($analyzer->getClassyElements() as $index => $element) {
            if (!$this->hasDocBlock($tokens, $index)) {
                continue;
            }
            $type = $element['type'];
            if (!isset($this->configuration[$type])) {
                continue;
            }
            $docIndex = $this->getDocBlockIndex($tokens, $index);
            $doc = new DocBlock($tokens[$docIndex]->getContent());
            if ('multi' === $this->configuration[$type]) {
                $doc->makeMultiLine(WhitespacesAnalyzer::detectIndent($tokens, $docIndex), $this->whitespacesConfig->getLineEnding());
            } elseif ('single' === $this->configuration[$type]) {
                $doc->makeSingleLine();
            }
            $tokens->offsetSet($docIndex, new Token([\T_DOC_COMMENT, $doc->getContent()]));
        }
    }
    private function hasDocBlock(Tokens $tokens, int $index) : bool
    {
        $docBlockIndex = $this->getDocBlockIndex($tokens, $index);
        return $tokens[$docBlockIndex]->isGivenKind(\T_DOC_COMMENT);
    }
    private function getDocBlockIndex(Tokens $tokens, int $index) : int
    {
        $propertyPartKinds = [\T_PUBLIC, \T_PROTECTED, \T_PRIVATE, \T_FINAL, \T_ABSTRACT, \T_COMMENT, \T_VAR, \T_STATIC, \T_STRING, \T_NS_SEPARATOR, CT::T_ARRAY_TYPEHINT, CT::T_NULLABLE_TYPE];
        if (\defined('T_READONLY')) {
            // @TODO: drop condition when PHP 8.1+ is required
            $propertyPartKinds[] = \T_READONLY;
        }
        do {
            $index = $tokens->getPrevNonWhitespace($index);
        } while ($tokens[$index]->isGivenKind($propertyPartKinds));
        return $index;
    }
}
