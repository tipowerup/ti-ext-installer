<?php

declare(strict_types=1);

return [
    'api_url' => env('TIPOWERUP_API_URL', 'https://packages.tipowerup.com/api/v1'),
    'composer_repo_url' => env('TIPOWERUP_COMPOSER_REPO_URL', 'https://packages.tipowerup.com'),
    'allowed_download_hosts' => array_filter(array_merge(
        ['packages.tipowerup.com'],
        explode(',', env('TIPOWERUP_ALLOWED_DOWNLOAD_HOSTS', '')),
    )),
];
