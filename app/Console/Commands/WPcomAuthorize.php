<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class WpcomAuthorize extends Command
{
    protected $signature = 'wpcom:authorize';
    protected $description = 'One-time OAuth2 flow to get a WordPress.com access token';

    public function handle(): int
    {
        // builds a query strings of the config values
        $query = http_build_query([
            'client_id'     => config('blogsync.wpcom_client_id'),
            'redirect_uri'  => config('blogsync.wpcom_redirect_uri'),
            'response_type' => 'code',
            'blog'          => config('blogsync.wpcom_site'),
        ]);

        $this->line('1. Open this URL in your browser and click Approve:');
        $this->newLine();
        $this->line("https://public-api.wordpress.com/oauth2/authorize?{$query}");
        $this->newLine();
        $this->line('2. You will be redirected to your redirect URI (the page may not load, that is fine).');
        $this->line('   Copy the value of the "code" parameter from the address bar.');
        $this->newLine();

        $code = trim($this->ask('Paste the code here'));

        $res = Http::asForm()->post('https://public-api.wordpress.com/oauth2/token', [
            'client_id'     => config('blogsync.wpcom_client_id'),
            'client_secret' => config('blogsync.wpcom_client_secret'),
            'redirect_uri'  => config('blogsync.wpcom_redirect_uri'),
            'grant_type'    => 'authorization_code',
            'code'          => $code,
        ]);

        if ($res->failed()) {
            $this->error('Token exchange failed: ' . $res->body());
            return self::FAILURE;
        }

        $this->info('Success. Add this to your .env:');
        $this->line('WPCOM_ACCESS_TOKEN=' . $res->json('access_token'));
        $this->line('(blog_id: ' . $res->json('blog_id') . ', blog_url: ' . $res->json('blog_url') . ')');

        return self::SUCCESS;
    }
}