<?php

declare (strict_types=1);
namespace ps_metrics_module_v4_0_6\Doctrine\Deprecations\PHPUnit;

use ps_metrics_module_v4_0_6\Doctrine\Deprecations\Deprecation;
use function sprintf;
trait VerifyDeprecations
{
    /** @var array<string,int> */
    private $doctrineDeprecationsExpectations = [];
    /** @var array<string,int> */
    private $doctrineNoDeprecationsExpectations = [];
    public function expectDeprecationWithIdentifier(string $identifier) : void
    {
        $this->doctrineDeprecationsExpectations[$identifier] = Deprecation::getTriggeredDeprecations()[$identifier] ?? 0;
    }
    public function expectNoDeprecationWithIdentifier(string $identifier) : void
    {
        $this->doctrineNoDeprecationsExpectations[$identifier] = Deprecation::getTriggeredDeprecations()[$identifier] ?? 0;
    }
    /**
     * @before
     */
    public function enableDeprecationTracking() : void
    {
        Deprecation::enableTrackingDeprecations();
    }
    /**
     * @after
     */
    public function verifyDeprecationsAreTriggered() : void
    {
        foreach ($this->doctrineDeprecationsExpectations as $identifier => $expectation) {
            $actualCount = Deprecation::getTriggeredDeprecations()[$identifier] ?? 0;
            $this->assertTrue($actualCount > $expectation, sprintf("Expected deprecation with identifier '%s' was not triggered by code executed in test.", $identifier));
        }
        foreach ($this->doctrineNoDeprecationsExpectations as $identifier => $expectation) {
            $actualCount = Deprecation::getTriggeredDeprecations()[$identifier] ?? 0;
            $this->assertTrue($actualCount === $expectation, sprintf("Expected deprecation with identifier '%s' was triggered by code executed in test, but expected not to.", $identifier));
        }
    }
}
