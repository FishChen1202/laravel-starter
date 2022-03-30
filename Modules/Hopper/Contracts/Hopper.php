<?php

namespace Modules\Hopper\Contracts;

use Modules\Hopper\Entities\Campaign;

interface Hopper
{

    public function addLeads(Campaign $campaign, array $leads): void;

    public function getLeads(Campaign $campaign, int $leadsCountForCall): array;

    public function getLeadsCountInHopper(Campaign $campaign): int;

    public function refillLeads(Campaign $campaign, array $leads): void;
}
