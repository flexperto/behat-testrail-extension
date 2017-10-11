<?php

namespace testrail;

class TestComment
{
    const SINGLE_SCENARIO = "single Scenario";

    private $exampleName;

    private $result;

    private $details;

    public function __construct(string $exampleName = null, string $result, string $details)
    {
        $this->exampleName = $exampleName ?: self::SINGLE_SCENARIO;
        $this->result = $result;
        $this->details = $details;
    }

    public function getExampleName() : string
    {
        return $this->exampleName;
    }

    public function getResult() : string
    {
        return $this->result;
    }

    public function getDetails() : string
    {
        return $this->details;
    }

    public function toString() : string
    {
        $phpEol = PHP_EOL;
        return "Example name: {$this->getExampleName()}{$phpEol}Result: {$this->getResult()}{$phpEol}Details: {$this->getDetails()}{$phpEol}";
    }
}
