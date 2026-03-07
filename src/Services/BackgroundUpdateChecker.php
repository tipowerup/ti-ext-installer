<?php

declare(strict_types=1);

namespace Tipowerup\Installer\Services;

use Igniter\User\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;
use Tipowerup\Installer\Models\License;
use Tipowerup\Installer\Notifications\PowerUpUpdateNotification;

class BackgroundUpdateChecker
{
    private const string CACHE_KEY = 'tipowerup_bg_update_check';

    private const int CACHE_TTL_SECONDS = 21600; // 6 hours

    public function __construct(
        private readonly PowerUpApiClient $apiClient,
    ) {}

    public function handle(): JsonResponse
    {
        ignore_user_abort(true);

        if (Cache::has(self::CACHE_KEY)) {
            return response()->json(['status' => 'throttled']);
        }

        $apiKey = params('tipowerup_api_key', '');
        if ($apiKey === '' || $apiKey === '0') {
            return response()->json(['status' => 'skipped', 'reason' => 'no_api_key']);
        }

        $licenses = License::active()->get();
        if ($licenses->isEmpty()) {
            return response()->json(['status' => 'skipped', 'reason' => 'no_licenses']);
        }

        Cache::put(self::CACHE_KEY, true, self::CACHE_TTL_SECONDS);

        try {
            $installedPackages = $licenses->map(fn (License $license): array => [
                'package_code' => $license->package_code,
                'version' => $license->version,
            ])->toArray();

            $this->apiClient->setTimeout(5)->setMaxRetries(1);

            $result = $this->apiClient->checkUpdates($installedPackages);
            $updateCount = count($result['updates'] ?? []);

            if ($updateCount > 0) {
                (new PowerUpUpdateNotification($updateCount))->broadcast();
            } else {
                $this->clearExistingUpdateNotifications();
            }

            return response()->json([
                'status' => 'checked',
                'updates' => $updateCount,
            ]);
        } catch (Throwable $e) {
            Log::warning('PowerUp background update check failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'error']);
        }
    }

    private function clearExistingUpdateNotifications(): void
    {
        try {
            Notification::query()
                ->where('type', 'tipowerup-update-available')
                ->delete();
        } catch (Throwable $e) {
            Log::warning('Failed to clear PowerUp update notifications', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
