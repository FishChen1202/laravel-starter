<?php

namespace Modules\Hopper\Tests\Unit\Components;

use Illuminate\Support\Carbon;
use Closure;
use Mockery;
use Redis;
use ReflectionClass;
use Tests\TestCase;
use Modules\Hopper\Entities\Campaign;
use Modules\Hopper\Components\Hopper;

class HopperTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->campaign = Campaign::factory()->create();

        $this->hopper = app(Hopper::class);
    }

    /**
     * Test addLeads()
     * @test
     *
     * @return void
     */
    public function addLeads(): void
    {
        $this->hopper->clearAll($this->campaign);
        $leadsForAdd = [
            [
                'lead_id' => 1,
                'score' => 5,
            ],
            [
                'lead_id' => 2,
                'score' => 3,
            ],
            [
                'lead_id' => 3,
                'score' => 6,
            ]
        ];

        $this->hopper->addLeads($this->campaign, $leadsForAdd);

        $leadIds = Redis::zrange($this->hopper->getHopperKey($this->campaign->id), 0, -1);

        $exceptedLeadOrder = [2, 1, 3];

        $this->assertEquals(
            $exceptedLeadOrder,
            $leadIds,
        );
    }

    /**
     * Test getLeadsCountInHopper()
     * @test
     *
     * @return void
     */
    public function getLeadsCountInHopper(): void
    {
        $this->hopper->clearAll($this->campaign);
        Redis::zadd($this->hopper->getHopperKey($this->campaign->id), 1, 2, 3, 4, 5, 7);

        $leadCount = $this->hopper->getLeadsCountInHopper($this->campaign);

        $this->assertEquals(
            3,
            $leadCount,
        );
    }

    /**
     * Test refillLeads()
     * @test
     *
     * @return void
     */
    public function refillLeads(): void
    {
        $this->hopper->clearAll($this->campaign);
        $fakeleadId = 9999999;
        $fakeLeadScore = 12345;
        Redis::zadd($this->hopper->getHopperKey($this->campaign->id), $fakeLeadScore, $fakeleadId);
        $leadsForRefill = [
            ['lead_id' => 1, 'score' => 23456],
        ];

        $this->hopper->refillLeads($this->campaign, $leadsForRefill);
        $leadIds = Redis::zrange($this->hopper->getHopperKey($this->campaign->id), 0, -1);
        $exceptedLeadOrder = [1];

        $this->assertEquals(
            $exceptedLeadOrder,
            $leadIds,
        );
    }

    /**
     * Test getCallableLeads()
     * @test
     * @dataProvider data_provider_for_test_get_callable_leads
     *
     * @return void
     */
    public function getCallableLeads(Closure $prepareData, Closure $asserts): void
    {
        $leadCount = Closure::bind($prepareData, $this)();
        $leadIds = $this->hopper->getCallableLeads($this->campaign, $leadCount);
        Closure::bind($asserts, $this)($leadIds);
    }

    public function data_provider_for_test_get_callable_leads(): array
    {
        return [
            $this->case_for_test_get_one_lead_from_nonempty_hopper(),
            $this->case_for_test_get_one_lead_from_empty_hopper(),
            $this->case_for_test_get_zero_lead_from_nonempty_hopper(),
        ];
    }

    public function case_for_test_get_one_lead_from_nonempty_hopper(): array
    {
        $leadsInHopper = [
            ['lead_id' => 1, 'score' => 12345],
            ['lead_id' => 2, 'score' => 23456],
        ];

        $prepareData = function () use ($leadsInHopper) {
            $this->hopper->clearAll($this->campaign);
            $this->hopper->refillLeads($this->campaign, $leadsInHopper);
            $wantedLeadCount = 1;

            return $wantedLeadCount;
        };

        $asserts = function (array $leadIds) use ($leadsInHopper) {
            $this->assertEquals(1, count($leadIds));
            $this->assertEquals($leadsInHopper[0]['lead_id'], $leadIds[0]);
            $this->assertEquals(1, $this->hopper->getLeadsCountInHopper($this->campaign));
        };

        return [$prepareData, $asserts];
    }

    public function case_for_test_get_one_lead_from_empty_hopper(): array
    {
        $prepareData = function () {
            $this->hopper->clearAll($this->campaign);
            $wantedLeadCount = 0;

            return $wantedLeadCount;
        };

        $asserts = function (array $leadIds) {
            $this->assertEquals(0, count($leadIds));
            $this->assertEquals(0, $this->hopper->getLeadsCountInHopper($this->campaign));
        };

        return [$prepareData, $asserts];
    }

    public function case_for_test_get_zero_lead_from_nonempty_hopper(): array
    {
        $leadsInHopper = [
            ['lead_id' => 3, 'score' => 12345],
            ['lead_id' => 2, 'score' => 23456],
        ];

        $prepareData = function () use ($leadsInHopper) {
            $this->hopper->clearAll($this->campaign);
            $this->hopper->refillLeads($this->campaign, $leadsInHopper);
            $wantedLeadCount = 0;

            return $wantedLeadCount;
        };

        $asserts = function (array $leadIds) {
            $this->assertEquals(0, count($leadIds));
            $this->assertEquals(2, $this->hopper->getLeadsCountInHopper($this->campaign));
        };

        return [$prepareData, $asserts];
    }
}
