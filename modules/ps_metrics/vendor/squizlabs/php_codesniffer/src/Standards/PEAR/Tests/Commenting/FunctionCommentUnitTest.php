<?php

/**
 * Unit test class for the FunctionComment sniff.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/PHPCSStandards/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace ps_metrics_module_v4_0_6\PHP_CodeSniffer\Standards\PEAR\Tests\Commenting;

use ps_metrics_module_v4_0_6\PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest;
/**
 * Unit test class for the FunctionComment sniff.
 *
 * @covers \PHP_CodeSniffer\Standards\PEAR\Sniffs\Commenting\FunctionCommentSniff
 */
final class FunctionCommentUnitTest extends AbstractSniffUnitTest
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
        return [5 => 1, 10 => 1, 12 => 1, 13 => 1, 14 => 1, 15 => 1, 28 => 1, 76 => 1, 87 => 1, 103 => 1, 109 => 1, 112 => 1, 122 => 1, 123 => 2, 124 => 2, 125 => 1, 126 => 1, 137 => 1, 138 => 1, 139 => 1, 152 => 1, 155 => 1, 165 => 1, 172 => 1, 183 => 1, 190 => 2, 206 => 1, 234 => 1, 272 => 1, 313 => 1, 317 => 1, 327 => 1, 329 => 1, 332 => 1, 344 => 1, 343 => 1, 345 => 1, 346 => 1, 360 => 1, 361 => 1, 363 => 1, 364 => 1, 406 => 1, 417 => 1, 455 => 1, 464 => 1, 473 => 1, 485 => 1, 501 => 1];
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
