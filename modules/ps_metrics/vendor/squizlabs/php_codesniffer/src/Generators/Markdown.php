<?php

/**
 * A doc generator that outputs documentation in Markdown format.
 *
 * @author    Stefano Kowalke <blueduck@gmx.net>
 * @copyright 2014 Arroba IT
 * @license   https://github.com/PHPCSStandards/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace ps_metrics_module_v4_0_6\PHP_CodeSniffer\Generators;

use DOMDocument;
use DOMNode;
use ps_metrics_module_v4_0_6\PHP_CodeSniffer\Config;
class Markdown extends Generator
{
    /**
     * Generates the documentation for a standard.
     *
     * @return void
     * @see    processSniff()
     */
    public function generate()
    {
        \ob_start();
        $this->printHeader();
        foreach ($this->docFiles as $file) {
            $doc = new DOMDocument();
            $doc->load($file);
            $documentation = $doc->getElementsByTagName('documentation')->item(0);
            $this->processSniff($documentation);
        }
        $this->printFooter();
        $content = \ob_get_contents();
        \ob_end_clean();
        echo $content;
    }
    //end generate()
    /**
     * Print the markdown header.
     *
     * @return void
     */
    protected function printHeader()
    {
        $standard = $this->ruleset->name;
        echo "# {$standard} Coding Standard" . \PHP_EOL;
    }
    //end printHeader()
    /**
     * Print the markdown footer.
     *
     * @return void
     */
    protected function printFooter()
    {
        // Turn off errors so we don't get timezone warnings if people
        // don't have their timezone set.
        \error_reporting(0);
        echo 'Documentation generated on ' . \date('r');
        echo ' by [PHP_CodeSniffer ' . Config::VERSION . '](https://github.com/PHPCSStandards/PHP_CodeSniffer)' . \PHP_EOL;
    }
    //end printFooter()
    /**
     * Process the documentation for a single sniff.
     *
     * @param \DOMNode $doc The DOMNode object for the sniff.
     *                      It represents the "documentation" tag in the XML
     *                      standard file.
     *
     * @return void
     */
    protected function processSniff(DOMNode $doc)
    {
        $title = $this->getTitle($doc);
        echo \PHP_EOL . "## {$title}" . \PHP_EOL;
        foreach ($doc->childNodes as $node) {
            if ($node->nodeName === 'standard') {
                $this->printTextBlock($node);
            } else {
                if ($node->nodeName === 'code_comparison') {
                    $this->printCodeComparisonBlock($node);
                }
            }
        }
    }
    //end processSniff()
    /**
     * Print a text block found in a standard.
     *
     * @param \DOMNode $node The DOMNode object for the text block.
     *
     * @return void
     */
    protected function printTextBlock(DOMNode $node)
    {
        $content = \trim($node->nodeValue);
        $content = \htmlspecialchars($content);
        $content = \str_replace('&lt;em&gt;', '*', $content);
        $content = \str_replace('&lt;/em&gt;', '*', $content);
        echo $content . \PHP_EOL;
    }
    //end printTextBlock()
    /**
     * Print a code comparison block found in a standard.
     *
     * @param \DOMNode $node The DOMNode object for the code comparison block.
     *
     * @return void
     */
    protected function printCodeComparisonBlock(DOMNode $node)
    {
        $codeBlocks = $node->getElementsByTagName('code');
        $firstTitle = $codeBlocks->item(0)->getAttribute('title');
        $first = \trim($codeBlocks->item(0)->nodeValue);
        $first = \str_replace("\n", "\n    ", $first);
        $first = \str_replace('<em>', '', $first);
        $first = \str_replace('</em>', '', $first);
        $secondTitle = $codeBlocks->item(1)->getAttribute('title');
        $second = \trim($codeBlocks->item(1)->nodeValue);
        $second = \str_replace("\n", "\n    ", $second);
        $second = \str_replace('<em>', '', $second);
        $second = \str_replace('</em>', '', $second);
        echo '  <table>' . \PHP_EOL;
        echo '   <tr>' . \PHP_EOL;
        echo "    <th>{$firstTitle}</th>" . \PHP_EOL;
        echo "    <th>{$secondTitle}</th>" . \PHP_EOL;
        echo '   </tr>' . \PHP_EOL;
        echo '   <tr>' . \PHP_EOL;
        echo '<td>' . \PHP_EOL . \PHP_EOL;
        echo "    {$first}" . \PHP_EOL . \PHP_EOL;
        echo '</td>' . \PHP_EOL;
        echo '<td>' . \PHP_EOL . \PHP_EOL;
        echo "    {$second}" . \PHP_EOL . \PHP_EOL;
        echo '</td>' . \PHP_EOL;
        echo '   </tr>' . \PHP_EOL;
        echo '  </table>' . \PHP_EOL;
    }
    //end printCodeComparisonBlock()
}
//end class
