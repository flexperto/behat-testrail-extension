<?php

namespace flexperto\BehatTestrailReporter\testrail;

class TestCase implements \JsonSerializable
{
    const STATUS_PASSED = 1;
    const STATUS_BLOCKED = 2;
    const STATUS_RETEST = 4;
    const STATUS_FAILED = 5;

    private $caseId;

    private $startedAt;

    private $totalTime;

    private $inProgress;

    private $result;

    /** @var  TestComment[] */
    private $comments;

    private $customFields;

    public function __construct(string $caseId, array $customFields)
    {
        $this->caseId = $caseId;
        $this->customFields = $customFields;
    }

    public function jsonSerialize() : array
    {
        return array_merge([
            "case_id"   => $this->getCaseId(),
            "status_id" => $this->getResult(),
            "comment"   => $this->getFormattedComments(),
            "elapsed"   => "{$this->getElapsedTime()}s"
        ], $this->customFields);
    }

    public function caseStarted()
    {
        $this->startedAt = microtime(true);
        $this->inProgress = true;
    }

    public function caseRestarted()
    {
        $this->caseStarted();
    }

    public function caseFinished(int $result, TestComment $comment)
    {
        $this->totalTime += microtime(true) - $this->startedAt;
        $this->decideOnCommonResult($result);
        $this->comments[] = $comment;
        $this->inProgress = false;
    }

    public function getCaseId() : int
    {
        return $this->caseId;
    }

    public function getResult() : int
    {
        return $this->result;
    }

    public function getElapsedTime() : int
    {
        return ceil($this->totalTime);
    }

    public function isInProgress() : bool
    {
        return $this->inProgress;
    }

    public function getRawComments() : string
    {
        return implode(array_map(
            function (TestComment $comment) {
                return $comment->toString();
            },
            $this->comments
        ), PHP_EOL . PHP_EOL);
    }

    public function getFormattedComments() : string
    {
        $phpEol = PHP_EOL;
        $reachText = "## Automatic comment ##";
        for ($commentNum = 1; $commentNum <= sizeof($this->comments); $commentNum++) {
            $comment = $this->comments[$commentNum - 1];
            $block = "{$phpEol}{$phpEol}{$commentNum}. ";
            if ($comment->getExampleName() === TestComment::SINGLE_SCENARIO) {
                $block .= "**{$comment->getExampleName()}**";
            } else {
                $block .= "**Example:**{$phpEol}    `{$comment->getExampleName()}`";
            }
            if (sizeof($this->comments) == 1) {
                $block .= $phpEol;
            }
            $block .= "{$phpEol}>    Result: {$comment->getResult()}";
            if (trim($comment->getDetails()) !== '') {
                $block .= "{$phpEol}>    Additional details:{$phpEol}>>    ";
                $block .= str_replace($phpEol, "{$phpEol}>>    ", $comment->getDetails());
            }
            $reachText .= $block;
        }
        return $reachText;
    }

    private function decideOnCommonResult(int $result)
    {
        switch ($result) {
            case self::STATUS_FAILED:
                $this->result = $result;
                break;
            case self::STATUS_RETEST:
                if ($this->result !== self::STATUS_FAILED) {
                    $this->result = $result;
                }
                break;
            case self::STATUS_BLOCKED:
                if ($this->result !== self::STATUS_FAILED && $this->result !== self::STATUS_RETEST) {
                    $this->result = $result;
                }
                break;
            case self::STATUS_PASSED:
                if ($this->result !== self::STATUS_FAILED && $this->result !== self::STATUS_RETEST && $this->result !== self::STATUS_BLOCKED) {
                    $this->result = $result;
                }
                break;
            default:
                break;
        }
    }
}
