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
namespace ps_metrics_module_v4_0_6\PhpCsFixer\Console\Report\FixReport;

use ps_metrics_module_v4_0_6\PhpCsFixer\Differ\DiffConsoleFormatter;
/**
 * @author Boris Gorbylev <ekho@ekho.name>
 *
 * @internal
 */
final class TextReporter implements ReporterInterface
{
    /**
     * {@inheritdoc}
     */
    public function getFormat() : string
    {
        return 'txt';
    }
    /**
     * {@inheritdoc}
     */
    public function generate(ReportSummary $reportSummary) : string
    {
        $output = '';
        $i = 0;
        foreach ($reportSummary->getChanged() as $file => $fixResult) {
            ++$i;
            $output .= \sprintf('%4d) %s', $i, $file);
            if ($reportSummary->shouldAddAppliedFixers()) {
                $output .= $this->getAppliedFixers($reportSummary->isDecoratedOutput(), $fixResult);
            }
            $output .= $this->getDiff($reportSummary->isDecoratedOutput(), $fixResult);
            $output .= \PHP_EOL;
        }
        return $output . $this->getFooter($reportSummary->getTime(), $reportSummary->getMemory(), $reportSummary->isDryRun());
    }
    private function getAppliedFixers(bool $isDecoratedOutput, array $fixResult) : string
    {
        return \sprintf($isDecoratedOutput ? ' (<comment>%s</comment>)' : ' (%s)', \implode(', ', $fixResult['appliedFixers']));
    }
    private function getDiff(bool $isDecoratedOutput, array $fixResult) : string
    {
        if (empty($fixResult['diff'])) {
            return '';
        }
        $diffFormatter = new DiffConsoleFormatter($isDecoratedOutput, \sprintf('<comment>      ---------- begin diff ----------</comment>%s%%s%s<comment>      ----------- end diff -----------</comment>', \PHP_EOL, \PHP_EOL));
        return \PHP_EOL . $diffFormatter->format($fixResult['diff']) . \PHP_EOL;
    }
    private function getFooter(int $time, int $memory, bool $isDryRun) : string
    {
        if (0 === $time || 0 === $memory) {
            return '';
        }
        return \PHP_EOL . \sprintf('%s all files in %.3f seconds, %.3f MB memory used' . \PHP_EOL, $isDryRun ? 'Checked' : 'Fixed', $time / 1000, $memory / 1024 / 1024);
    }
}
