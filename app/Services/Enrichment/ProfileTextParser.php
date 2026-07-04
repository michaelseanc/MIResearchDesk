<?php

namespace App\Services\Enrichment;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Best-effort structuring of PASTED public profile text (a LinkedIn person profile, a company
 * "About"/overview page, a staff bio, etc.) into dossier fields. Deliberately heuristic and lossy —
 * it produces a DRAFT the reporter reviews and confirms before anything is saved. No network access,
 * no scraping: the human obtained and pasted the text.
 */
class ProfileTextParser
{
    private const NOISE = [
        'contact info', 'see contact info', 'connect', 'message', 'follow', 'following',
        'more', 'connections', 'followers', 'mutual connections', 'open to work', 'visit website',
    ];

    private const SECTIONS = [
        'experience', 'education', 'activity', 'skills', 'licenses', 'about', 'highlights', 'featured',
        'overview', 'specialties', 'products', 'services', 'life', 'jobs', 'people', 'posts', 'home',
    ];

    /** Key/value labels on company pages — used to terminate the Overview/About block. */
    private const LABELS = [
        'website', 'industry', 'company size', 'headquarters', 'founded', 'phone', 'type', 'locations', 'employees',
    ];

    /** @return array{full_name:?string, professional_role:?string, employer:?string, geography:?string, summary:?string} */
    public function parse(string $text): array
    {
        $lines = $this->lines($text);

        $result = ['full_name' => null, 'professional_role' => null, 'employer' => null, 'geography' => null, 'summary' => null];
        if ($lines->isEmpty()) {
            return $result;
        }

        $result['full_name'] = $lines->first();
        $headline = $lines->slice(1)->first(fn ($l) => ! $this->isSectionHeader($l) && ! $this->looksLikeLocation($l));

        if ($headline) {
            if (Str::contains($headline, ' at ')) {
                [$role, $employer] = explode(' at ', $headline, 2);
                $result['professional_role'] = trim($role);
                $result['employer'] = trim($employer);
            } else {
                $result['professional_role'] = $headline;
            }
        }

        if (! $result['employer']) {
            $company = $lines->first(fn ($l) => Str::contains($l, '·')
                && Str::contains(strtolower($l), ['full-time', 'part-time', 'self-employed', 'contract']));
            if ($company) {
                $result['employer'] = trim(Str::before($company, '·'));
            }
        }

        $result['geography'] = $lines->first(fn ($l) => $this->looksLikeLocation($l));
        $result['summary'] = $this->extractSection($lines, ['about']);

        return $result;
    }

    /** @return array{name:?string, website:?string, industry:?string, geography:?string, founded:?string, size:?string, summary:?string} */
    public function parseOrganization(string $text): array
    {
        $lines = $this->lines($text);

        $result = ['name' => null, 'website' => null, 'industry' => null, 'geography' => null, 'founded' => null, 'size' => null, 'summary' => null];
        if ($lines->isEmpty()) {
            return $result;
        }

        $result['name'] = $lines->first();

        // LinkedIn company pages present labeled key/value pairs (label on one line, value on the next).
        $labelToKey = [
            'website' => 'website',
            'industry' => 'industry',
            'headquarters' => 'geography',
            'founded' => 'founded',
            'company size' => 'size',
        ];
        $arr = $lines->values()->all();
        foreach ($arr as $i => $line) {
            $key = strtolower(trim($line));
            if (isset($labelToKey[$key]) && isset($arr[$i + 1])) {
                $result[$labelToKey[$key]] = $arr[$i + 1];
            }
        }

        // Website fallback: first URL/domain-looking token anywhere.
        if (! $result['website']) {
            $line = $lines->first(fn ($l) => (bool) preg_match('#https?://|www\.|[\w-]+\.(?:com|org|net|gov|edu|io)\b#i', $l));
            if ($line && preg_match('#(https?://\S+|www\.\S+|[\w-]+\.(?:com|org|net|gov|edu|io)\S*)#i', $line, $m)) {
                $result['website'] = $m[1];
            }
        }

        if (! $result['geography']) {
            $result['geography'] = $lines->first(fn ($l) => $this->looksLikeLocation($l));
        }

        $result['summary'] = $this->extractSection($lines, ['about', 'overview']);

        return $result;
    }

    /** @return Collection<int, string> */
    private function lines(string $text): Collection
    {
        return collect(preg_split('/\r\n|\r|\n/', $text))
            ->map(fn ($l) => trim((string) $l))
            ->filter(fn ($l) => $l !== '' && ! $this->isNoise($l))
            ->values();
    }

    private function isNoise(string $line): bool
    {
        $l = strtolower($line);
        if (in_array($l, self::NOISE, true)) {
            return true;
        }

        return (bool) preg_match('/^\d[\d,\.]*\s*(connections?|followers?|employees?)$/i', $line)
            || (bool) preg_match('/^\d+(st|nd|rd|th)$/i', $line);
    }

    private function isSectionHeader(string $line): bool
    {
        return in_array(strtolower(trim($line)), self::SECTIONS, true);
    }

    private function looksLikeLocation(string $line): bool
    {
        return (bool) preg_match('/,\s*[A-Z]{2}\b/', $line)
            || (bool) preg_match('/\b(United States|Area|Greater|Metropolitan|County)\b/i', $line);
    }

    /** @param Collection<int, string> $lines @param array<string> $headers */
    private function extractSection(Collection $lines, array $headers): ?string
    {
        $start = $lines->search(fn ($l) => in_array(strtolower(trim($l)), $headers, true));
        if ($start === false) {
            return null;
        }

        $collected = [];
        foreach ($lines->slice($start + 1) as $l) {
            if ($this->isSectionHeader($l) || in_array(strtolower(trim($l)), self::LABELS, true)) {
                break;
            }
            $collected[] = $l;
        }

        $summary = trim(implode("\n", $collected));

        return $summary === '' ? null : $summary;
    }
}
