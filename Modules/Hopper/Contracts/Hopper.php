<?php

namespace Modules\Hopper\Contracts;

use Modules\Hopper\Entities\Campaign;

interface Hopper
{

    public function addLeads(Campaign $campaign, array $leadsWithScore): void;

    public function getLeads(Campaign $campaign, int $leadsCountForCall): array;

    public function getLeadsCountInHopper(Campaign $campaign): int;

    public function clearAll(Campaign $campaign): void;

    public function refillLeads(Campaign $campaign, array $leadsWithScore): void;
}
