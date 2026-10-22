<?php

namespace App\Console\Commands;

use App\Models\SyncedPost;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncSubstack extends Command
{
    protected $signature = 'sync:substack';
    protected $description = 'Copy new Substack posts to WordPress.com';

    public function handle(): int
    {
        try {
            $feed = Http::accept('application/rss+xml, application/xml, text/xml;q=0.9, */*;q=0.8')
                ->withUserAgent('BlogSync/1.0')
                ->timeout(20)
                ->retry(3, 1000, throw: false)
                ->get(config('blogsync.substack_feed'));
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Substack feed unreachable', ['error' => $e->getMessage()]);
            $this->error('Feed unreachable: ' . $e->getMessage());
            return self::FAILURE;
        }

        if ($feed->failed()) {
            $contentType = (string) $feed->header('Content-Type');
            $bodyExcerpt = substr(trim(strip_tags($feed->body())), 0, 500);
            Log::error('Substack feed fetch failed', [
                'status' => $feed->status(),
                'content_type' => $contentType,
                'body_excerpt' => $bodyExcerpt,
            ]);
            $this->error("Feed fetch failed (HTTP {$feed->status()}; Content-Type: " . ($contentType ?: 'missing') . '). ' . $bodyExcerpt);
            return self::FAILURE;
        }
        $contentType = (string) $feed->header('Content-Type');
        if (! str_contains(strtolower($contentType), 'xml')) {
            Log::error('Feed is not XML', ['type' => $contentType]);
            $this->error('Got ' . ($contentType ?: 'no Content-Type') . ' instead of XML. Check SUBSTACK_FEED_URL.');
            return self::FAILURE;
        }

        $previousLibxmlErrorMode = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($feed->body(), 'SimpleXMLElement', LIBXML_NOCDATA);
        $xmlErrors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlErrorMode);
        if ($xml === false) {
            $details = $xmlErrors ? trim($xmlErrors[0]->message) : 'Unknown XML parse error';
            Log::error('Substack feed XML parse failed', ['error' => $details]);
            $this->error('Could not parse feed XML: ' . $details);
            return self::FAILURE;
        }

        $after = config('blogsync.sync_after') ? Carbon::parse(config('blogsync.sync_after')) : null;

        // Feed is newest-first; publish oldest-first.
        $items = array_reverse(iterator_to_array($xml->channel->item, false));

        foreach ($items as $item) {
            $guid = (string) $item->guid;
            $published = Carbon::parse((string) $item->pubDate);

            if ($after && $published->lt($after)) continue;
            if (SyncedPost::where('substack_guid', $guid)->exists()) continue;

            $record = SyncedPost::firstOrCreate([
                'substack_guid' => $guid,
                'substack_url'  => (string) $item->link,
                'title'         => (string) $item->title,
            ]);

            $this->push($record, $item, $published);
        }

        return self::SUCCESS;
    }

    private function push(SyncedPost $record, \SimpleXMLElement $item, Carbon $published): void
    {
        $html = (string) $item->children('content', true)->encoded;
        $html = $this->clean($html, (string) $item->link);
        $site = config('blogsync.wpcom_site');

        try {
            $res = Http::withToken(config('blogsync.wpcom_token'))
                ->asForm()
                ->timeout(30)
                ->retry(2, 2000, throw:false)
                ->post("https://public-api.wordpress.com/rest/v1.1/sites/{$site}/posts/new", [
                    'title'   => $record->title,
                    'content' => $html,
                    'status'  => config('blogsync.wp_status'),
                    'date'    => $published->toIso8601String(),
                ]);

            if ($res->failed()) {
                throw new \RuntimeException("WP.com {$res->status()}: " . $res->body());
            }

            $record->update([
                'wp_post_id'   => $res->json('ID'),   // note: capital ID in the WordPress.com API
                'content_hash' => hash('sha256', $html),
                'status'       => 'synced',
                'synced_at'    => now(),
                'error'        => null,
            ]);
            $this->info("Synced: {$record->title}");
        } catch (\Throwable $e) {
            $record->update(['status' => 'failed', 'error' => $e->getMessage()]);
            Log::error('Substack sync failed', ['guid' => $record->substack_guid, 'error' => $e->getMessage()]);
            $this->error("Failed: {$record->title}");
        }
    }

    private function clean(string $html, string $link): string
    {
        //  remove empty paragraphs (including ones holding only &nbsp; or <br>)
        $html = preg_replace('#<p[^>]*>(\s|&nbsp;|<br\s*/?>)*</p>#i', '', $html);

        // remove line breaks sitting between tags, so WordPress doesn't turn them into <br>/<p>
        $html = preg_replace('#>\s*[\r\n]+\s*<#', '><', $html);

        //  collapse runs of <br> into one
        $html = preg_replace('#(<br\s*/?>\s*){2,}#i', '<br>', $html);

        $html = trim($html);

        // strip subscribe/share widgets, upload images via /media/new.
        $html .= '<hr><p><em>Originally published on <a href="' . e($link) . '">Substack</a>.</em></p>';
        return $html;
    }
}