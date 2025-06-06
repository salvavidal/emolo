<?php

/**
 * Unit test class for the DisallowShortOpenTag sniff.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/PHPCSStandards/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */
namespace ps_metrics_module_v4_0_6\PHP_CodeSniffer\Standards\Generic\Tests\PHP;

use ps_metrics_module_v4_0_6\PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest;
/**
 * Unit test class for the DisallowShortOpenTag sniff.
 *
 * @covers \PHP_CodeSniffer\Standards\Generic\Sniffs\PHP\DisallowShortOpenTagSniff
 */
final class DisallowShortOpenTagUnitTest extends AbstractSniffUnitTest
{
    /**
     * Get a list of all test files to check.
     *
     * @param string $testFileBase The base path that the unit tests files will have.
     *
     * @return string[]
     */
    protected function getTestFiles($testFileBase)
    {
        $testFiles = [$testFileBase . '1.inc'];
        $option = (bool) \ini_get('short_open_tag');
        if ($option === \true) {
            $testFiles[] = $testFileBase . '2.inc';
        } else {
            $testFiles[] = $testFileBase . '3.inc';
        }
        return $testFiles;
    }
    //end getTestFiles()
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
            case 'DisallowShortOpenTagUnitTest.1.inc':
                return [5 => 1, 6 => 1, 7 => 1, 10 => 1];
            case 'DisallowShortOpenTagUnitTest.2.inc':
                return [2 => 1, 3 => 1, 4 => 1, 7 => 1];
            default:
                return [];
        }
        //end switch
    }
    //end getErrorList()
    /**
     * Returns the lines where warnings should occur.
     *
     * The key of the array should represent the line number and the value
     * should represent the number of warnings that should occur on that line.
     *
     * @param string $testFile The name of the file being tested.
     *
     * @return array<int, int>
     */
    public function getWarningList($testFile = '')
    {
        switch ($testFile) {
            case 'DisallowShortOpenTagUnitTest.1.inc':
                return [];
            case 'DisallowShortOpenTagUnitTest.3.inc':
                return [3 => 1, 6 => 1, 11 => 1];
            default:
                return [];
        }
        //end switch
    }
    //end getWarningList()
}
//end class
