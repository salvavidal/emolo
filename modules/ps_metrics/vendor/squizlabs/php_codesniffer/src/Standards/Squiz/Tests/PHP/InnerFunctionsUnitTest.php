<?php

/**
 * Unit test class for the InnerFunctions sniff.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/PHPCSStandards/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace ps_metrics_module_v4_0_6\PHP_CodeSniffer\Standards\Squiz\Tests\PHP;

use ps_metrics_module_v4_0_6\PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest;
/**
 * Unit test class for the InnerFunctions sniff.
 *
 * @covers \PHP_CodeSniffer\Standards\Squiz\Sniffs\PHP\InnerFunctionsSniff
 */
final class InnerFunctionsUnitTest extends AbstractSniffUnitTest
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
        return [5 => 1, 46 => 1, 55 => 1, 83 => 1];
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
