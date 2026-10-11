<?php

namespace App\Enums;

/**
 * Step types of a scenario graph. "Waiting" steps stop the engine until the
 * user answers; every other step is executed immediately and the engine moves
 * on along its outgoing edge.
 */
enum NodeType: string
{
    case Start = 'start';         // entry point, exactly one per graph
    case Message = 'message';     // send text, continue
    case Question = 'question';   // send text + options, wait for a choice
    case Input = 'input';         // ask for free text, validate, store in a variable
    case Condition = 'condition'; // branch on contact data / variables
    case Action = 'action';       // side effect (tag, booking, hand-off, ...)
    case End = 'end';             // optional closing text, ends the session

    public function isWaiting(): bool
    {
        return in_array($this, [self::Question, self::Input], true);
    }

    /**
     * Static output handles of the node type. Question nodes additionally get
     * one dynamic handle per option: "option:{id}".
     *
     * @return list<string>
     */
    public function staticHandles(): array
    {
        return match ($this) {
            self::Start, self::Message, self::Input, self::Action => ['next'],
            self::Condition => ['true', 'false'],
            self::Question, self::End => [],
        };
    }
}
