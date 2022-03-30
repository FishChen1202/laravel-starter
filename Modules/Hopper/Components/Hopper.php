<?php

namespace Modules\Hopper\Components;

use Redis;
use Modules\Hopper\Entities\Campaign;
use \Modules\Hopper\Contracts\Hopper as baseHopper;

class Hopper implements baseHopper
{
    public function getHopperKey(int $campaignId): string
    {
        return "neo:outbound-call-hopper:campaign-{$campaignId}";
    }

    public function refillLeads(Campaign $campaign, array $leadsWithScore): void
    {
        $this->clearAll($campaign);
        $this->addLeads($campaign, $leadsWithScore);
    }

    public function addLeads(Campaign $campaign, array $leadsWithScore): void
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

    public function getLeads(Campaign $campaign, int $leadsCountForCall): array
    {
        # TODO:

        return [];
    }

    public function getLeadsCountInHopper(Campaign $campaign): int
    {
        # TODO:

        return 1;
    }

    public function clearAll(Campaign $campaign): void
    {
        $hopperKey = $this->getHopperKey($campaign->id);
        $this->reset($hopperKey);
    }

}
