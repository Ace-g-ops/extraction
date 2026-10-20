<?php

return [
    'substack_feed' => env('SUBSTACK_FEED_URL'),    
    'sync_after'    => env('SUBSTACK_SYNC_AFTER'),   

    // WordPress.com (OAuth2)
    'wpcom_client_id'     => env('WPCOM_CLIENT_ID'),
    'wpcom_client_secret' => env('WPCOM_CLIENT_SECRET'),
    'wpcom_redirect_uri'  => env('WPCOM_REDIRECT_URI', 'http://localhost/callback'),
    'wpcom_site'          => env('WPCOM_SITE'),          // yourblog.wordpress.com (or custom domain / numeric site ID)
    'wpcom_token'         => env('WPCOM_ACCESS_TOKEN'),  // filled in after running wpcom:authorize
    'wp_status'           => env('WP_POST_STATUS', 'draft'),
];