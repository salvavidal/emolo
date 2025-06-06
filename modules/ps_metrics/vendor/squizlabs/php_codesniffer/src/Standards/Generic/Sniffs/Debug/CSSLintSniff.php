<?php

/**
 * Runs csslint on the file.
 *
 * @author    Roman Levishchenko <index.0h@gmail.com>
 * @copyright 2013-2014 Roman Levishchenko
 * @license   https://github.com/PHPCSStandards/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 *
 * @deprecated 3.9.0
 */
namespace ps_metrics_module_v4_0_6\PHP_CodeSniffer\Standards\Generic\Sniffs\Debug;

use ps_metrics_module_v4_0_6\PHP_CodeSniffer\Config;
use ps_metrics_module_v4_0_6\PHP_CodeSniffer\Files\File;
use ps_metrics_module_v4_0_6\PHP_CodeSniffer\Sniffs\Sniff;
use ps_metrics_module_v4_0_6\PHP_CodeSniffer\Util\Common;
class CSSLintSniff implements Sniff
{
    /**
     * A list of tokenizers this sniff supports.
     *
     * @var array
     */
    public $supportedTokenizers = ['CSS'];
    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return array<int|string>
     */
    public function register()
    {
        return [\T_OPEN_TAG];
    }
    //end register()
    /**
     * Processes the tokens that this sniff is interested in.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file where the token was found.
     * @param int                         $stackPtr  The position in the stack where
     *                                               the token was found.
     *
     * @return int
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $csslintPath = Config::getExecutablePath('csslint');
        if ($csslintPath === null) {
            return $phpcsFile->numTokens;
        }
        $fileName = $phpcsFile->getFilename();
        $cmd = Common::escapeshellcmd($csslintPath) . ' ' . \escapeshellarg($fileName) . ' 2>&1';
        \exec($cmd, $output, $retval);
        if (\is_array($output) === \false) {
            return $phpcsFile->numTokens;
        }
        $count = \count($output);
        for ($i = 0; $i < $count; $i++) {
            $matches = [];
            $numMatches = \preg_match('/(error|warning) at line (\\d+)/', $output[$i], $matches);
            if ($numMatches === 0) {
                continue;
            }
            $line = (int) $matches[2];
            $message = 'csslint says: ' . $output[$i + 1];
            // First line is message with error line and error code.
            // Second is error message.
            // Third is wrong line in file.
            // Fourth is empty line.
            $i += 4;
            $phpcsFile->addWarningOnLine($message, $line, 'ExternalTool');
        }
        //end for
        // Ignore the rest of the file.
        return $phpcsFile->numTokens;
    }
    //end process()
}
//end class
