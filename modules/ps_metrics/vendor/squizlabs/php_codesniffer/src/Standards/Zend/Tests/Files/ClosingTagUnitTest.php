<?php

/**
 * Unit test class for the ClosingTag sniff.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/PHPCSStandards/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace ps_metrics_module_v4_0_6\PHP_CodeSniffer\Standards\Zend\Tests\Files;

use ps_metrics_module_v4_0_6\PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest;
/**
 * Unit test class for the ClosingTag sniff.
 *
 * @covers \PHP_CodeSniffer\Standards\Zend\Sniffs\Files\ClosingTagSniff
 */
final class ClosingTagUnitTest extends AbstractSniffUnitTest
{
    /**
     * Returns the lines where errors should occur.
     *
     * The key of the array should represent the line number and the value
     * should represent the number of errors that should occur on that line.
     *
     * @param string $testFile The name of the file being tested.
     *
     * @return array<int, int>
     */
    public function getErrorList($testFile = '')
    {
        switch ($testFile) {
            case 'ClosingTagUnitTest.1.inc':
                return [11 => 1];
            case 'ClosingTagUnitTest.3.inc':
            case 'ClosingTagUnitTest.4.inc':
            case 'ClosingTagUnitTest.5.inc':
            case 'ClosingTagUnitTest.7.inc':
                return [1 => 1];
            case 'ClosingTagUnitTest.6.inc':
                return [3 => 1];
            default:
                return [];
        }
    }
    //end getErrorList()
    /**
     * Returns the lines where warnings should occur.
     *
     * The key of the array should represent the line number and the value
     * should represent the number of warnings that should occur on that line.
     *
     * @return array<int, int>
     */
    public function getWarningList()
    {
        return [];
    }
    //end getWarningList()
}
//end class
