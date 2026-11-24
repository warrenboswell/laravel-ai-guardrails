<?php

declare(strict_types=1);

namespace WarrenBoswell\LaravelAiGuardrails\Guards;

class PromptBoundaryGuard
{
    /**
     * Strip spoofed opening or closing boundary tags from untrusted text and wrap it safely.
     */
    public function wrap(string $input, string $tag = 'sandbox_input'): string
    {
        $cleanTag = $this->normalizeTag($tag);
        $sanitized = $this->strip($input, $cleanTag);

        return "<$cleanTag>\n$sanitized\n</$cleanTag>";
    }

    /**
     * Remove any case-insensitive occurrences of the boundary tag from untrusted text.
     */
    public function strip(string $input, string $tag = 'sandbox_input'): string
    {
        $cleanTag = $this->normalizeTag($tag);
        $pattern = '/<\s*\/?\s*'.preg_quote($cleanTag, '/').'(?=[\s>\/])[^>]*>/i';
        $cleaned = preg_replace($pattern, '', $input);

        return trim($cleaned ?? $input);
    }

    private function normalizeTag(string $tag): string
    {
        $cleanTag = preg_replace('/[^a-zA-Z0-9_-]/', '', $tag) ?? '';
        $cleanTag = trim($cleanTag, '-');

        return $cleanTag !== '' ? $cleanTag : 'sandbox_input';
    }
}
