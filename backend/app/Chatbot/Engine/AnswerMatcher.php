<?php

namespace App\Chatbot\Engine;

use App\Chatbot\Graph\Node;

/**
 * Maps a user's answer to one of the question's options. Channels differ: in
 * Telegram the user presses a button (payload = option id), in SMS they type
 * "2", in WhatsApp they often type the label or a word like "yes". All of it
 * must land on the same option, so the matcher tries, in order:
 *   button payload → option number → exact label → synonym/label word match.
 */
class AnswerMatcher
{
    public function match(Node $question, string $text, ?string $payload = null): ?string
    {
        $options = $question->options();

        if ($payload !== null) {
            foreach ($options as $option) {
                if ($option['id'] === $payload) {
                    return $option['id'];
                }
            }
        }

        $answer = $this->normalize($text);
        if ($answer === '') {
            return null;
        }

        if (ctype_digit($answer) && isset($options[(int) $answer - 1])) {
            return $options[(int) $answer - 1]['id'];
        }

        foreach ($options as $option) {
            if ($this->normalize($option['label']) === $answer) {
                return $option['id'];
            }
        }

        $words = preg_split('/\s+/u', $answer);
        foreach ($options as $option) {
            $candidates = array_map(fn ($s) => $this->normalize($s), [$option['label'], ...($option['synonyms'] ?? [])]);
            foreach ($candidates as $candidate) {
                if ($candidate !== '' && (in_array($candidate, $words, true) || str_contains($answer, $candidate) && mb_strlen($candidate) > 3)) {
                    return $option['id'];
                }
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', '', $value);

        return trim(preg_replace('/\s+/u', ' ', $value));
    }
}
