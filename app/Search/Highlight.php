<?php

namespace App\Search;

/**
 * Turns a Solr highlight fragment into text segments the browser renders as text nodes.
 *
 * Solr wraps matched terms in <em>…</em> and does not escape the text around them, so a
 * fragment must never reach innerHTML. Split into [text, hit] pairs, every piece is
 * interpolated as text on the client and only the hits are wrapped in <mark>.
 */
final class Highlight
{
    /**
     * Split a fragment on its highlight markers.
     *
     * @return list<array{t: string, h: bool}>
     */
    public static function segments(string $fragment): array
    {
        $parts = preg_split('#(<em>.*?</em>)#s', $fragment, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $segments = [];

        foreach ($parts ?: [] as $part) {
            if (str_starts_with($part, '<em>') && str_ends_with($part, '</em>')) {
                $segments[] = ['t' => substr($part, 4, -5), 'h' => true];
            } else {
                $segments[] = ['t' => $part, 'h' => false];
            }
        }

        return $segments;
    }

    /**
     * The fragment as plain text, markers removed.
     */
    public static function plain(string $fragment): string
    {
        return str_replace(['<em>', '</em>'], '', $fragment);
    }
}
