<?php

/**
 * Ensures that a property or label colon has a single space after it and no space before it.
 *
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/PHPCSStandards/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 *
 * @deprecated 3.9.0
 */
namespace ps_metrics_module_v4_0_6\PHP_CodeSniffer\Standards\Squiz\Sniffs\WhiteSpace;

use ps_metrics_module_v4_0_6\PHP_CodeSniffer\Files\File;
use ps_metrics_module_v4_0_6\PHP_CodeSniffer\Sniffs\Sniff;
class PropertyLabelSpacingSniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supportedTokenizers = ['JS'];
    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array<int|string>
     */
    public function register()
    {
        return [T_PROPERTY, T_LABEL];
    }
    //end register()
    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token
     *                                               in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $colon = $phpcsFile->findNext(T_COLON, $stackPtr + 1);
        if ($colon !== $stackPtr + 1) {
            $error = 'There must be no space before the colon in a property/label declaration';
            $fix = $phpcsFile->addFixableError($error, $stackPtr, 'Before');
            if ($fix === \true) {
                $phpcsFile->fixer->replaceToken($stackPtr + 1, '');
            }
        }
        if ($tokens[$colon + 1]['code'] !== \T_WHITESPACE || $tokens[$colon + 1]['content'] !== ' ') {
            $error = 'There must be a single space after the colon in a property/label declaration';
            $fix = $phpcsFile->addFixableError($error, $stackPtr, 'After');
            if ($fix === \true) {
                if ($tokens[$colon + 1]['code'] === \T_WHITESPACE) {
                    $phpcsFile->fixer->replaceToken($colon + 1, ' ');
                } else {
                    $phpcsFile->fixer->addContent($colon, ' ');
                }
            }
        }
    }
    //end process()
}
//end class
