<?php

namespace App\Services\Cultura\Parsers;

use App\Models\CulturalSource;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Str;

class OfficialHtmlOpportunityParser implements CulturalSourceParser
{
    public function supports(CulturalSource $source): bool
    {
        return $source->source_type === 'official_web' && $source->ingestion_mode === 'html';
    }

    public function parse(CulturalSource $source, string $body): array
    {
        if (trim($body) === '') {
            return [];
        }

        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML($body, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return [];
        }

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//a[@href]');
        $items = [];

        if ($nodes === false) {
            return [];
        }

        foreach ($nodes as $node) {
            $title = Str::squish((string) $node->textContent);
            $href = trim((string) $node->getAttribute('href'));

            if (! $this->looksLikeOpportunity($title, $href)) {
                continue;
            }

            $url = $this->absoluteUrl($source->url, $href);
            if ($url === null) {
                continue;
            }

            $key = hash('sha256', Str::lower($title).'|'.Str::lower($url));
            $items[$key] = [
                'title' => $title,
                'source_url' => $url,
                'organization' => $source->owner,
                'opportunity_type' => $this->detectType($title),
                'status' => 'review',
                'metadata' => [
                    'parser' => self::class,
                    'discovery_method' => 'official_html_anchor',
                ],
            ];
        }

        return array_values($items);
    }

    private function looksLikeOpportunity(string $title, string $href): bool
    {
        if (mb_strlen($title) < 8 || mb_strlen($title) > 300) {
            return false;
        }

        $haystack = Str::lower($title.' '.$href);
        foreach (['edital', 'chamamento', 'premio', 'prêmio', 'fomento', 'proac', 'pnab', 'cultura viva', 'credenciamento'] as $needle) {
            if (Str::contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function detectType(string $title): ?string
    {
        $value = Str::lower($title);
        foreach (['edital', 'chamamento', 'credenciamento', 'premio', 'prêmio'] as $type) {
            if (Str::contains($value, $type)) {
                return $type === 'prêmio' ? 'premio' : $type;
            }
        }

        return null;
    }

    private function absoluteUrl(string $base, string $href): ?string
    {
        if ($href === '' || Str::startsWith($href, ['#', 'javascript:', 'mailto:', 'tel:'])) {
            return null;
        }

        if (filter_var($href, FILTER_VALIDATE_URL)) {
            return $href;
        }

        $parts = parse_url($base);
        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        if (Str::startsWith($href, '//')) {
            return $parts['scheme'].':'.$href;
        }

        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        if (Str::startsWith($href, '/')) {
            return $origin.$href;
        }

        $path = $parts['path'] ?? '/';
        $directory = rtrim(str_replace('\\', '/', dirname($path)), '/');

        return $origin.($directory === '' ? '' : $directory).'/'.$href;
    }
}
