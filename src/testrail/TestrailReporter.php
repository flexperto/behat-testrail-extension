<?php

namespace flexperto\BehatTestrailReporter\testrail;

use Behat\Behat\EventDispatcher\Event\AfterFeatureTested;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\ScenarioScope;
use Behat\Gherkin\Node\ExampleNode;
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
            'tester.outline_tested.before' => 'onBeforeOutlineTested',
            'tester.outline_tested.after' => 'onAfterOutlineTested',
            'tester.step_tested.after' => 'onAfterStepTested',
        );
    }

    public function onAfterFeatureTested(AfterFeatureTested $event) {
        var_dump($event);
    }

    public function isScenarioApplicable(ScenarioScope $scenarioScope) : bool
    {
        if ($scenarioScope->getScenario()->hasTags()) {
            foreach ($scenarioScope->getScenario()->getTags() as $tag) {
                if (preg_match("/{$this->testIdPrefix}\\d+/s", $tag) !== 0) {
                    return true;
                }
            }
        }
        return false;
    }

    public function scenarioStarted(ScenarioScope $scenarioScope)
    {
        $scenarioTestCaseId = $this->extractScenarioTestCaseId($scenarioScope);
        if (array_key_exists($scenarioTestCaseId, $this->pendingResultsAccumulator)) {
            $this->pendingResultsAccumulator[$scenarioTestCaseId]->caseRestarted();
        } else {
            $newTestCase = new TestCase($scenarioTestCaseId, $this->customFields);
            $newTestCase->caseStarted();
            $this->pendingResultsAccumulator[$scenarioTestCaseId] = $newTestCase;
        }
    }

    public function scenarioFinished(AfterScenarioScope $afterScenarioScope, string $additionalComments = null)
    {
        $scenarioTestCaseId = $this->extractScenarioTestCaseId($afterScenarioScope);
        if (!array_key_exists($scenarioTestCaseId, $this->pendingResultsAccumulator)) {
            throw new TestrailException("could not find case with id {$scenarioTestCaseId} to fulfil results");
        }
        $result = BehatToTestrailResultMapper::getTestrailStatus($afterScenarioScope->getTestResult()->getResultCode());
        $testComment = $this->createTestComment($afterScenarioScope, $additionalComments);

        $this->pendingResultsAccumulator[$scenarioTestCaseId]->caseFinished($result, $testComment);
    }

    public function featureFinished()
    {
        if (sizeof($this->pendingResultsAccumulator) > 0) {
            $this->testrailApiClient->pushResultsBatch($this->pendingResultsAccumulator);
        }
        $this->pendingResultsAccumulator = [];
    }

    private function extractScenarioTestCaseId(ScenarioScope $scenarioScope) : int
    {
        foreach ($scenarioScope->getScenario()->getTags() as $tag) {
            if (preg_match("/{$this->testIdPrefix}(\\d+)/s", $tag, $matches) !== 0) {
                $testCaseId = $matches[1];
                if (is_numeric($testCaseId)) {
                    return (int)$testCaseId;
                }
            }
        }
        throw new TestrailException("could not fetch scenario id from " . implode($scenarioScope->getScenario()->getTags(), ", "));
    }

    private function createTestComment(AfterScenarioScope $afterScenarioScope, string $additionalComments = null) : TestComment
    {
        $scenario = $afterScenarioScope->getScenario();
        $exampleName = null;
        if ($scenario instanceof ExampleNode) {
            $exampleName = $scenario->getTitle();
        }
        $result = $afterScenarioScope->getTestResult()->isPassed() ? "Success" : "Was not successful or did not run";
        $comments = $additionalComments ?: 'No additional comments available';
        return new TestComment($exampleName, $result, $comments);
    }
}
