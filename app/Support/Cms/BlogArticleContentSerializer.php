<?php

namespace App\Support\Cms;

class BlogArticleContentSerializer
{
    public function deserialize(string $body): array
    {
        $body = trim(str_replace("\r\n", "\n", $body));

        if ($body === '') {
            return [$this->blankSection()];
        }

        preg_match_all('/^##\s+(.+)$/m', $body, $matches, PREG_OFFSET_CAPTURE);

        if (($matches[0] ?? []) === []) {
            return [[
                'heading' => '',
                'body' => $body,
            ]];
        }

        $sections = [];
        $headings = $matches[1];
        $markers = $matches[0];

        foreach ($markers as $index => $marker) {
            $start = $marker[1] + strlen($marker[0]);
            $end = $markers[$index + 1][1] ?? strlen($body);
            $sectionBody = trim(substr($body, $start, $end - $start));

            $sections[] = [
                'heading' => trim($headings[$index][0]),
                'body' => $sectionBody,
            ];
        }

        return $sections !== [] ? $sections : [$this->blankSection()];
    }

    public function serialize(array $sections): string
    {
        $normalized = collect($sections)
            ->map(function (mixed $section): ?array {
                if (! is_array($section)) {
                    return null;
                }

                $heading = trim((string) ($section['heading'] ?? ''));
                $body = trim((string) ($section['body'] ?? ''));

                if ($heading === '' && $body === '') {
                    return null;
                }

                return [
                    'heading' => $heading,
                    'body' => $body,
                ];
            })
            ->filter()
            ->values();

        if ($normalized->isEmpty()) {
            return '';
        }

        return $normalized
            ->map(function (array $section): string {
                if ($section['heading'] === '') {
                    return $section['body'];
                }

                return "## {$section['heading']}\n\n{$section['body']}";
            })
            ->implode("\n\n");
    }

    public function blankSection(): array
    {
        return [
            'heading' => '',
            'body' => '',
        ];
    }
}
