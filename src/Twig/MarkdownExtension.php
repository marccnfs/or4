<?php

declare(strict_types=1);

namespace App\Twig;

use App\Services\MarkdownRenderer;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFilter;

class MarkdownExtension extends AbstractExtension
{
    public function __construct(private readonly MarkdownRenderer $markdownRenderer)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('markdown_to_html', [$this, 'renderMarkdown']),
        ];
    }

    public function renderMarkdown(string $markdown): Markup
    {
        return new Markup($this->markdownRenderer->render($markdown), 'UTF-8');
    }
}
