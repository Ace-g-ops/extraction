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
            $feed = Http::timeout(20)->retry(3, 1000, throw: false)->get(config('blogsync.substack_feed'));
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Substack feed unreachable', ['error' => $e->getMessage()]);
            $this->error('Feed unreachable.');
            return self::FAILURE;
        }

        if ($feed->failed()) {
            Log::error('Substack feed fetch failed', ['status' => $feed->status()]);
            $this->error('Feed fetch failed.');
            return self::FAILURE;
        }
        if (! str_contains($feed->header('Content-Type'), 'xml')) {
            Log::error('Feed is not XML', ['type' => $feed->header('Content-Type')]);
            $this->error('Got ' . $feed->header('Content-Type') . ' instead of XML. Check SUBSTACK_FEED_URL.');
            return self::FAILURE;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($feed->body(), 'SimpleXMLElement', LIBXML_NOCDATA);

        $xml = simplexml_load_string($feed->body(), 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xml === false) {
            $this->error('Could not parse feed.');
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
        // TODO v2: strip subscribe/share widgets, upload images via /media/new.
        $html .= '<hr><p><em>Originally published on <a href="' . e($link) . '">Substack</a>.</em></p>';
        return $html;
    }
}