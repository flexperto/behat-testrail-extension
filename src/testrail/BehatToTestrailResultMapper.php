<?php

namespace flexperto\BehatTestrailReporter\testrail;

use Behat\Testwork\Tester\Result\TestResult as BehatTestResult;
use testrail\TestCase as TestrailTestCase;

class BehatToTestrailResultMapper
{

    private static $resultMapping = [
        BehatTestResult::PASSED  => TestrailTestCase::STATUS_PASSED,
        BehatTestResult::FAILED  => TestrailTestCase::STATUS_FAILED,
        BehatTestResult::SKIPPED => TestrailTestCase::STATUS_RETEST,
        BehatTestResult::PENDING => TestrailTestCase::STATUS_RETEST,
    ];

    public static function getTestrailStatus(int $behatResult) : int
    {
        return self::$resultMapping[$behatResult];
    }
}
