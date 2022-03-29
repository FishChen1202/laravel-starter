<?php

namespace Modules\Hopper\Components;

use Carbon\Carbon;
use Redis;
use Modules\Hopper\Entities\Campaign;

class Hopper
{
    public function getHopperKey(int $campaignId): string
    {
        return "neo:outbound-call-hopper:campaign-{$campaignId}";
    }

    public function getLastRefillTimestampKey(int $campaignId): string
    {
        return "neo:outbound-call-hopper-timestamp:campaign-{$campaignId}";
    }

    public function refillLeads(Campaign $campaign, array $leads): void
    {
        # TODO:
    }

    public function addLeads(Campaign $campaign, array $leads): void
    {
        # TODO:
    }

    private function setExpire(string $key, int $expireSeconds = 60 * 60 * 24): void
    {
        /** @phpstan-ignore-next-line */
        Redis::expire($key, $expireSeconds);
    }

    public function reset(string $key): void
    {
        /** @phpstan-ignore-next-line */
        Redis::del($key);
    }

    public function getCallableLeads(Campaign $campaign, int $leadsCountForCall): array
    {
        # TODO:

        return [];
    }

    public function getLeadsCountInHopper(Campaign $campaign): int
    {
        # TODO:

        return 1;
    }

    public function getLastRefillTimestamp(Campaign $campaign): Carbon|null
    {
        $timestampKey = $this->getLastRefillTimestampKey($campaign->id);
        /** @phpstan-ignore-next-line */
        $lastRefillTimestamp = Redis::get($timestampKey);
        if (!$lastRefillTimestamp) {
            return null;
        }
        return Carbon::parse($lastRefillTimestamp);
    }

    public function setLastRefillTimestamp(string $key): void
    {
        /** @phpstan-ignore-next-line */
        Redis::set($key, Carbon::now()->toDateTimeString());
    }

    public function clearAll(Campaign $campaign): void
    {
        $hopperKey = $this->getHopperKey($campaign->id);
        $timestampKey = $this->getLastRefillTimestampKey($campaign->id);
        $this->reset($hopperKey);
        $this->reset($timestampKey);
    }

    public function isNeedRefilled(Campaign $campaign, Carbon $time): bool
    {
        $lastRefillTimestamp = $this->getLastRefillTimestamp($campaign);

        if (!$lastRefillTimestamp) {
            return true;
        }

        $nowAfterOneMinute = $time->addMinute();
        $lastRefillTimestampAfterOneMinute = $lastRefillTimestamp->addMinute();

        return !($nowAfterOneMinute->startOfHour() == $lastRefillTimestampAfterOneMinute->startOfHour());
    }
}
