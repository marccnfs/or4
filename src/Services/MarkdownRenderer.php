<?php

declare(strict_types=1);

namespace App\Services;

class MarkdownRenderer
{
    public function render(string $markdown): string
    {
        $markdown = trim(str_replace(["\r\n", "\r"], "\n", $markdown));
        if ($markdown === '') {
            return '';
        }

        $blocks = preg_split("/\n{2,}/", $markdown) ?: [];
        $htmlBlocks = [];

        foreach ($blocks as $block) {
            $lines = array_values(array_filter(explode("\n", trim($block)), static fn (string $line): bool => trim($line) !== ''));
            if ($lines === []) {
                continue;
            }

            if (preg_match('/^(#{1,3})\s+(.+)$/u', $lines[0], $matches) === 1 && count($lines) === 1) {
                $level = strlen($matches[1]) + 1;
                $htmlBlocks[] = sprintf('<h%d>%s</h%d>', $level, $this->renderInline($matches[2]), $level);
                continue;
            }

            if ($this->isList($lines)) {
                $items = array_map(fn (string $line): string => sprintf('<li>%s</li>', $this->renderInline(preg_replace('/^[-*]\s+/u', '', trim($line)) ?? $line)), $lines);
                $htmlBlocks[] = '<ul>' . implode('', $items) . '</ul>';
                continue;
            }

            $htmlBlocks[] = '<p>' . $this->renderInline(implode("\n", $lines)) . '</p>';
        }

        return implode("\n", $htmlBlocks);
    }

    /**
     * @param string[] $lines
     */
    private function isList(array $lines): bool
    {
        foreach ($lines as $line) {
            if (preg_match('/^[-*]\s+/u', trim($line)) !== 1) {
                return false;
            }
        }

        return true;
    }

    private function renderInline(string $text): string
    {
        $html = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $html) ?? $html;
        $html = preg_replace('/\*(.+?)\*/u', '<em>$1</em>', $html) ?? $html;
        $html = preg_replace_callback('/\[([^\]]+)]\((https?:\/\/[^\s)]+)\)/u', static function (array $matches): string {
            return sprintf('<a href="%s" target="_blank" rel="noopener">%s</a>', $matches[2], $matches[1]);
        }, $html) ?? $html;

        return nl2br($html, false);
    }
}
