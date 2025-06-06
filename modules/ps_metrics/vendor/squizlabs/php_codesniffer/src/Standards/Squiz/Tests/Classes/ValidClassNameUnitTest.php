<?php

/**
 * Unit test class for the ValidClassName sniff.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/PHPCSStandards/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace ps_metrics_module_v4_0_6\PHP_CodeSniffer\Standards\Squiz\Tests\Classes;

use ps_metrics_module_v4_0_6\PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest;
/**
 * Unit test class for the ValidClassName sniff.
 *
 * @covers \PHP_CodeSniffer\Standards\Squiz\Sniffs\Classes\ValidClassNameSniff
 */
final class ValidClassNameUnitTest extends AbstractSniffUnitTest
{
    /**
     * Returns the lines where errors should occur.
     *
     * The key of the array should represent the line number and the value
     * should represent the number of errors that should occur on that line.
     *
     * @return array<int, int>
     */
    public function getErrorList()
    {
        return [9 => 1, 10 => 1, 14 => 1, 15 => 1, 20 => 1, 30 => 1, 32 => 1, 57 => 1, 58 => 1, 62 => 1, 63 => 1, 68 => 1, 78 => 1, 80 => 1, 97 => 1, 98 => 1, 102 => 1, 103 => 1, 108 => 1, 118 => 1, 120 => 1, 145 => 1, 146 => 1, 150 => 1, 151 => 1, 156 => 1];
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
