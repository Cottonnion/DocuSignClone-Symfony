<?php

namespace App\Security;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\IpUtils;

class IpRateLimiterService
{

    private const WINDOW_SECONDS        =   300;
    private const MAX_REQUESTS          =   3;
    private const BLACKLIST_THRESHOLD   =   50;
    private const BLACKLIST_DURATION    =   86400;

    private $ipCache;

    public function __construct(){
        $this->ipCache = new FilesystemAdapter('ip_rate_limiter', 0, sys_get_temp_dir());
    }

    public function isIpAllowed(string $ip): array
    {
        // Check if IP is blacklisted
        if ($this->isBlacklisted($ip)) {
            return [
                'allowed' => false,
                'message' => 'IP is blacklisted due to suspicious activity',
                'remaining_attempts' => 0,
                'retry_after' => $this->getBlacklistRemainingTime($ip)
            ];
        }

        $key = 'login_attempts_' . $this->hashIp($ip);
        $item = $this->ipCache->getItem($key);
        
        $attempts = $item->get() ?? [
            'count' => 0,
            'first_attempt' => time()
        ];

        // Reset if window expired
        if (time() - $attempts['first_attempt'] > self::WINDOW_SECONDS) {
            $attempts = [
                'count' => 0,
                'first_attempt' => time()
            ];
        }

        $attempts['count']++;

        // Check if should be blacklisted
        if ($attempts['count'] > self::BLACKLIST_THRESHOLD) {
            $this->blacklistIp($ip);
            return [
                'allowed' => false,
                'message' => 'IP blacklisted due to excessive attempts',
                'remaining_attempts' => 0,
                'retry_after' => self::BLACKLIST_DURATION
            ];
        }

        // Check if exceeded rate limit
        if ($attempts['count'] > self::MAX_REQUESTS) {
            $retryAfter = self::WINDOW_SECONDS - (time() - $attempts['first_attempt']);
            return [
                'allowed' => false,
                'message' => 'Rate limit exceeded',
                'remaining_attempts' => 0,
                'retry_after' => $retryAfter
            ];
        }

        // Save attempts
        $item->set($attempts);
        $item->expiresAfter(self::WINDOW_SECONDS);
        $this->ipCache->save($item);

        return [
            'allowed' => true,
            'remaining_attempts' => self::MAX_REQUESTS - $attempts['count'],
            'retry_after' => 0
        ];
    }

    private function isBlacklisted(string $ip): bool
    {
        $item = $this->ipCache->getItem('blacklist_' . $this->hashIp($ip));
        return $item->isHit();
    }

    private function blacklistIp(string $ip): void
    {
        $item = $this->ipCache->getItem('blacklist_' . $this->hashIp($ip));
        $item->set(time());
        $item->expiresAfter(self::BLACKLIST_DURATION);
        $this->ipCache->save($item);
    }

    private function getBlacklistRemainingTime(string $ip): int
    {
        $item = $this->ipCache->getItem('blacklist_' . $this->hashIp($ip));
        if (!$item->isHit()) {
            return 0;
        }
        return self::BLACKLIST_DURATION - (time() - $item->get());
    }

    private function hashIp(string $ip): string
    {
        return hash('sha256', $ip);
    }


}