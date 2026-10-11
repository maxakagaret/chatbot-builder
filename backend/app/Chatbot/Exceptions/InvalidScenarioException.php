<?php

namespace App\Chatbot\Exceptions;

use App\Chatbot\Graph\Issue;
use RuntimeException;

class InvalidScenarioException extends RuntimeException
{
    /** @param list<Issue> $issues */
    public function __construct(public readonly array $issues)
    {
        parent::__construct('The scenario has errors and cannot be published.');
    }
}
