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
namespace ps_metrics_module_v4_0_6\PhpCsFixer\Documentation;

use ps_metrics_module_v4_0_6\PhpCsFixer\Console\Command\HelpCommand;
use ps_metrics_module_v4_0_6\PhpCsFixer\Differ\FullDiffer;
use ps_metrics_module_v4_0_6\PhpCsFixer\Fixer\ConfigurableFixerInterface;
use ps_metrics_module_v4_0_6\PhpCsFixer\Fixer\DeprecatedFixerInterface;
use ps_metrics_module_v4_0_6\PhpCsFixer\Fixer\FixerInterface;
use ps_metrics_module_v4_0_6\PhpCsFixer\FixerConfiguration\AliasedFixerOption;
use ps_metrics_module_v4_0_6\PhpCsFixer\FixerConfiguration\AllowedValueSubset;
use ps_metrics_module_v4_0_6\PhpCsFixer\FixerConfiguration\DeprecatedFixerOptionInterface;
use ps_metrics_module_v4_0_6\PhpCsFixer\FixerDefinition\CodeSampleInterface;
use ps_metrics_module_v4_0_6\PhpCsFixer\FixerDefinition\FileSpecificCodeSampleInterface;
use ps_metrics_module_v4_0_6\PhpCsFixer\FixerDefinition\VersionSpecificCodeSampleInterface;
use ps_metrics_module_v4_0_6\PhpCsFixer\Preg;
use ps_metrics_module_v4_0_6\PhpCsFixer\RuleSet\RuleSet;
use ps_metrics_module_v4_0_6\PhpCsFixer\RuleSet\RuleSets;
use ps_metrics_module_v4_0_6\PhpCsFixer\StdinFileInfo;
use ps_metrics_module_v4_0_6\PhpCsFixer\Tokenizer\Tokens;
use ps_metrics_module_v4_0_6\PhpCsFixer\Utils;
/**
 * @internal
 */
final class FixerDocumentGenerator
{
    /**
     * @var DocumentationLocator
     */
    private $locator;
    /**
     * @var FullDiffer
     */
    private $differ;
    public function __construct(DocumentationLocator $locator)
    {
        $this->locator = $locator;
        $this->differ = new FullDiffer();
    }
    public function generateFixerDocumentation(FixerInterface $fixer) : string
    {
        $name = $fixer->getName();
        $title = "Rule ``{$name}``";
        $titleLine = \str_repeat('=', \strlen($title));
        $doc = "{$titleLine}\n{$title}\n{$titleLine}";
        if ($fixer instanceof DeprecatedFixerInterface) {
            $doc .= "\n\n.. warning:: This rule is deprecated and will be removed on next major version.";
            $alternatives = $fixer->getSuccessorsNames();
            if (0 !== \count($alternatives)) {
                $doc .= RstUtils::toRst(\sprintf("\n\nYou should use %s instead.", Utils::naturalLanguageJoinWithBackticks($alternatives)), 3);
            }
        }
        $definition = $fixer->getDefinition();
        $doc .= "\n\n" . RstUtils::toRst($definition->getSummary());
        $description = $definition->getDescription();
        if (null !== $description) {
            $description = RstUtils::toRst($description);
            $doc .= <<<RST


Description
-----------

{$description}
RST;
        }
        $riskyDescription = $definition->getRiskyDescription();
        if (null !== $riskyDescription) {
            $riskyDescription = RstUtils::toRst($riskyDescription, 3);
            $doc .= <<<RST


.. warning:: Using this rule is risky.

   {$riskyDescription}
RST;
        }
        if ($fixer instanceof ConfigurableFixerInterface) {
            $doc .= <<<'RST'


Configuration
-------------
RST;
            $configurationDefinition = $fixer->getConfigurationDefinition();
            foreach ($configurationDefinition->getOptions() as $option) {
                $optionInfo = "``{$option->getName()}``";
                $optionInfo .= "\n" . \str_repeat('~', \strlen($optionInfo));
                if ($option instanceof DeprecatedFixerOptionInterface) {
                    $deprecationMessage = RstUtils::toRst($option->getDeprecationMessage());
                    $optionInfo .= "\n\n.. warning:: This option is deprecated and will be removed on next major version. {$deprecationMessage}";
                }
                $optionInfo .= "\n\n" . RstUtils::toRst($option->getDescription());
                if ($option instanceof AliasedFixerOption) {
                    $optionInfo .= "\n\n.. note:: The previous name of this option was ``{$option->getAlias()}`` but it is now deprecated and will be removed on next major version.";
                }
                $allowed = HelpCommand::getDisplayableAllowedValues($option);
                if (null === $allowed) {
                    $allowedKind = 'Allowed types';
                    $allowed = \array_map(static function ($value) : string {
                        return '``' . $value . '``';
                    }, $option->getAllowedTypes());
                } else {
                    $allowedKind = 'Allowed values';
                    foreach ($allowed as &$value) {
                        if ($value instanceof AllowedValueSubset) {
                            $value = 'a subset of ``' . HelpCommand::toString($value->getAllowedValues()) . '``';
                        } else {
                            $value = '``' . HelpCommand::toString($value) . '``';
                        }
                    }
                }
                $allowed = \implode(', ', $allowed);
                $optionInfo .= "\n\n{$allowedKind}: {$allowed}";
                if ($option->hasDefault()) {
                    $default = HelpCommand::toString($option->getDefault());
                    $optionInfo .= "\n\nDefault value: ``{$default}``";
                } else {
                    $optionInfo .= "\n\nThis option is required.";
                }
                $doc .= "\n\n{$optionInfo}";
            }
        }
        $samples = $definition->getCodeSamples();
        if (0 !== \count($samples)) {
            $doc .= <<<'RST'


Examples
--------
RST;
            foreach ($samples as $index => $sample) {
                $title = \sprintf('Example #%d', $index + 1);
                $titleLine = \str_repeat('~', \strlen($title));
                $doc .= "\n\n{$title}\n{$titleLine}";
                if ($fixer instanceof ConfigurableFixerInterface) {
                    if (null === $sample->getConfiguration()) {
                        $doc .= "\n\n*Default* configuration.";
                    } else {
                        $doc .= \sprintf("\n\nWith configuration: ``%s``.", HelpCommand::toString($sample->getConfiguration()));
                    }
                }
                $doc .= "\n" . $this->generateSampleDiff($fixer, $sample, $index + 1, $name);
            }
        }
        $ruleSetConfigs = [];
        foreach (RuleSets::getSetDefinitionNames() as $set) {
            $ruleSet = new RuleSet([$set => \true]);
            if ($ruleSet->hasRule($name)) {
                $ruleSetConfigs[$set] = $ruleSet->getRuleConfiguration($name);
            }
        }
        if ([] !== $ruleSetConfigs) {
            $plural = 1 !== \count($ruleSetConfigs) ? 's' : '';
            $doc .= <<<RST


Rule sets
---------

The rule is part of the following rule set{$plural}:
RST;
            foreach ($ruleSetConfigs as $set => $config) {
                $ruleSetPath = $this->locator->getRuleSetsDocumentationFilePath($set);
                $ruleSetPath = \substr($ruleSetPath, \strrpos($ruleSetPath, '/'));
                $doc .= <<<RST


{$set}
  Using the `{$set} <./../../ruleSets{$ruleSetPath}>`_ rule set will enable the ``{$name}`` rule
RST;
                if (null !== $config) {
                    $doc .= " with the config below:\n\n  ``" . HelpCommand::toString($config) . '``';
                } elseif ($fixer instanceof ConfigurableFixerInterface) {
                    $doc .= ' with the default config.';
                } else {
                    $doc .= '.';
                }
            }
        }
        return "{$doc}\n";
    }
    /**
     * @param FixerInterface[] $fixers
     */
    public function generateFixersDocumentationIndex(array $fixers) : string
    {
        $overrideGroups = ['PhpUnit' => 'PHPUnit', 'PhpTag' => 'PHP Tag', 'Phpdoc' => 'PHPDoc'];
        \usort($fixers, static function (FixerInterface $a, FixerInterface $b) : int {
            return \strcmp(\get_class($a), \get_class($b));
        });
        $documentation = <<<'RST'
=======================
List of Available Rules
=======================
RST;
        $currentGroup = null;
        foreach ($fixers as $fixer) {
            $namespace = Preg::replace('/^.*\\\\(.+)\\\\.+Fixer$/', '$1', \get_class($fixer));
            $group = $overrideGroups[$namespace] ?? Preg::replace('/(?<=[[:lower:]])(?=[[:upper:]])/', ' ', $namespace);
            if ($group !== $currentGroup) {
                $underline = \str_repeat('-', \strlen($group));
                $documentation .= "\n\n{$group}\n{$underline}\n";
                $currentGroup = $group;
            }
            $path = './' . $this->locator->getFixerDocumentationFileRelativePath($fixer);
            $attributes = [];
            if ($fixer instanceof DeprecatedFixerInterface) {
                $attributes[] = 'deprecated';
            }
            if ($fixer->isRisky()) {
                $attributes[] = 'risky';
            }
            $attributes = 0 === \count($attributes) ? '' : ' *(' . \implode(', ', $attributes) . ')*';
            $summary = \str_replace('`', '``', $fixer->getDefinition()->getSummary());
            $documentation .= <<<RST

- `{$fixer->getName()} <{$path}>`_{$attributes}

  {$summary}
RST;
        }
        return "{$documentation}\n";
    }
    private function generateSampleDiff(FixerInterface $fixer, CodeSampleInterface $sample, int $sampleNumber, string $ruleName) : string
    {
        if ($sample instanceof VersionSpecificCodeSampleInterface && !$sample->isSuitableFor(\PHP_VERSION_ID)) {
            $existingFile = @\file_get_contents($this->locator->getFixerDocumentationFilePath($fixer));
            if (\false !== $existingFile) {
                Preg::match("/\\RExample #{$sampleNumber}\\R.+?(?<diff>\\R\\.\\. code-block:: diff\\R\\R.*?)\\R(?:\\R\\S|\$)/s", $existingFile, $matches);
                if (isset($matches['diff'])) {
                    return $matches['diff'];
                }
            }
            $error = <<<RST

.. error::
   Cannot generate diff for code sample #{$sampleNumber} of rule {$ruleName}:
   the sample is not suitable for current version of PHP (%s).
RST;
            return \sprintf($error, \PHP_VERSION);
        }
        $old = $sample->getCode();
        $tokens = Tokens::fromCode($old);
        $file = $sample instanceof FileSpecificCodeSampleInterface ? $sample->getSplFileInfo() : new StdinFileInfo();
        if ($fixer instanceof ConfigurableFixerInterface) {
            $fixer->configure($sample->getConfiguration() ?? []);
        }
        $fixer->fix($file, $tokens);
        $diff = $this->differ->diff($old, $tokens->generateCode());
        $diff = Preg::replace('/@@[ \\+\\-\\d,]+@@\\n/', '', $diff);
        $diff = Preg::replace('/\\r/', '^M', $diff);
        $diff = Preg::replace('/^ $/m', '', $diff);
        $diff = Preg::replace('/\\n$/', '', $diff);
        $diff = RstUtils::indent($diff, 3);
        return <<<RST

.. code-block:: diff

   {$diff}
RST;
    }
}
