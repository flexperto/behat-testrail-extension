<?php

namespace flexperto\BehatTestrailReporter\testrail;

use Behat\Behat\EventDispatcher\Event\AfterFeatureTested;
use Behat\Behat\EventDispatcher\Event\AfterOutlineTested;
use Behat\Behat\EventDispatcher\Event\AfterScenarioTested;
use Behat\Behat\EventDispatcher\Event\AfterStepTested;
use Behat\Behat\EventDispatcher\Event\BeforeOutlineTested;
use Behat\Behat\EventDispatcher\Event\BeforeScenarioTested;
use Behat\Behat\EventDispatcher\Event\ScenarioTested;
use Behat\Behat\Hook\Call\AfterScenario;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\ScenarioScope;
use Behat\Gherkin\Node\ExampleNode;
use Behat\Testwork\Output\Formatter;
use Behat\Testwork\Tester\Result\ExceptionResult;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TestrailReporter implements EventSubscriberInterface
{

    /** @var TestrailApiClient  */
    private $testrailApiClient;

    /** @var string scenario tags are validated against this prefix to decide whether scenario is applicable for result submission */
    private $testIdPrefix;

    /** @var array additional fields that must be submitted along with testrail result */
    private $customFields;

    /** @var  TestCase[] accumulates test results before they are being sent to Testrail */
    private $pendingResultsAccumulator;

    /** @var  string last error message caught from failing scenario step. Should be cleared explicitly on AfterScenario*/
    private $lastErrorMessage;

    public function __construct(string $baseUrl, string $username, string $apiKey, string $runId, string $testidPrefix, array $customFields)
    {
        $this->testrailApiClient = new TestrailApiClient($baseUrl, $username, $apiKey, $runId);
        $this->testIdPrefix = $testidPrefix;
        $this->customFields = $customFields;
        $this->pendingResultsAccumulator = [];
    }

    public static function getSubscribedEvents() {
        return array(
            'tester.feature_tested.after' => 'onAfterFeatureTested',
            'tester.scenario_tested.before' => 'onBeforeScenarioTested',
            'tester.scenario_tested.after' => 'onAfterScenarioTested',
            'tester.example_tested.before' => 'onBeforeScenarioTested',
            'tester.example_tested.after' => 'onAfterScenarioTested',
            'tester.step_tested.after' => 'onAfterStepTested',
        );
    }


    public function onBeforeScenarioTested(BeforeScenarioTested $event) {
        if ($this->isScenarioApplicable($event)) {
            $this->scenarioStarted($event);
        }
    }

    public function onAfterStepTested(AfterStepTested $event) {
        $result = $event->getTestResult();
        if (!$result->isPassed() && $result instanceof ExceptionResult) {
            $this->lastErrorMessage = $result->getException()->getMessage();
        }
    }

    public function onAfterScenarioTested(AfterScenarioTested $event) {
        if ($this->isScenarioApplicable($event)) {
            $this->scenarioFinished($event, $this->lastErrorMessage);
        }
        $this->lastErrorMessage = null;
    }

    public function onAfterFeatureTested(AfterFeatureTested $event) {
        $this->featureFinished();
    }

    private function isScenarioApplicable(ScenarioTested $scenarioTestedEvent) : bool
    {
        if ($scenarioTestedEvent->getScenario()->hasTags()) {
            foreach ($scenarioTestedEvent->getScenario()->getTags() as $tag) {
                if (preg_match("/{$this->testIdPrefix}\\d+/s", $tag) !== 0) {
                    return true;
                }
            }
        }
        return false;
    }

    private function scenarioStarted(ScenarioTested $scenarioTestedEvent)
    {
        $scenarioTestCaseId = $this->extractScenarioTestCaseId($scenarioTestedEvent);
        if (array_key_exists($scenarioTestCaseId, $this->pendingResultsAccumulator)) {
            $this->pendingResultsAccumulator[$scenarioTestCaseId]->caseRestarted();
        } else {
            $newTestCase = new TestCase($scenarioTestCaseId, $this->customFields);
            $newTestCase->caseStarted();
            $this->pendingResultsAccumulator[$scenarioTestCaseId] = $newTestCase;
        }
    }

    private function scenarioFinished(AfterScenarioTested $afterScenarioTestedEvent, string $additionalComments = null)
    {
        $scenarioTestCaseId = $this->extractScenarioTestCaseId($afterScenarioTestedEvent);
        if (!array_key_exists($scenarioTestCaseId, $this->pendingResultsAccumulator)) {
            throw new TestrailException("could not find case with id {$scenarioTestCaseId} to fulfil results");
        }
        $result = BehatToTestrailResultMapper::getTestrailStatus($afterScenarioTestedEvent->getTestResult()->getResultCode());
        $testComment = $this->createTestComment($afterScenarioTestedEvent, $additionalComments);

        $this->pendingResultsAccumulator[$scenarioTestCaseId]->caseFinished($result, $testComment);
    }

    private function featureFinished()
    {
        if (sizeof($this->pendingResultsAccumulator) > 0) {
            $this->testrailApiClient->pushResultsBatch($this->pendingResultsAccumulator);
        }
        $this->pendingResultsAccumulator = [];
    }

    private function extractScenarioTestCaseId(ScenarioTested $scenarioTestedEvent) : int
    {
        foreach ($scenarioTestedEvent->getScenario()->getTags() as $tag) {
            if (preg_match("/{$this->testIdPrefix}(\\d+)/s", $tag, $matches) !== 0) {
                $testCaseId = $matches[1];
                if (is_numeric($testCaseId)) {
                    return (int)$testCaseId;
                }
            }
        }
        throw new TestrailException("could not fetch scenario id from " . implode($scenarioTestedEvent->getScenario()->getTags(), ", "));
    }

    private function createTestComment(AfterScenarioTested $afterScenarioTestedEvent, string $additionalComments = null) : TestComment
    {
        $scenario = $afterScenarioTestedEvent->getScenario();
        $exampleName = null;
        if ($scenario instanceof ExampleNode) {
            $exampleName = $scenario->getTitle();
        }
        $result = $afterScenarioTestedEvent->getTestResult()->isPassed() ? "Success" : "Was not successful or did not run";
        $comments = $additionalComments ?: 'No additional comments available';
        return new TestComment($exampleName, $result, $comments);
    }
}
