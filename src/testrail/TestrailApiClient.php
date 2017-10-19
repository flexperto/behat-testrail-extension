<?php

namespace flexperto\BehatTestrailReporter\testrail;

use Httpful\Mime;
use Httpful\Request;

class TestrailApiClient
{
    /** @var string testrail base url */
    private $baseUrl;

    /** @var string testrail username  */
    private $username;

    /** @var string test rail api key. Might as well be an account password, though it is considered as a bad practice */
    private $apiKey;

    /** @var string test rail run id that should be updated with test results */
    private $runId;

    public function __construct(string $baseUrl, string $username, string $apiKey, string $runId)
    {
        $this->baseUrl = $baseUrl;
        $this->username = $username;
        $this->apiKey = $apiKey;
        $this->runId = $runId;
    }

    public function pushResultsBatch($pendingResultsAccumulator)
    {
        $request = Request::init();
        $response = $request
            ->uri("{$this->baseUrl}/add_results_for_cases/{$this->runId}")
            ->basicAuth($this->username, $this->apiKey)
            ->body(json_encode([ "results" => array_values($pendingResultsAccumulator)], JSON_PRETTY_PRINT), Mime::JSON)
            ->method("POST")
            ->send();
        if ($response->code !== 200) {
            echo "silently saying that testrail request failed with code {$response->code} and body $response->raw_body\n";
        }
    }
}
