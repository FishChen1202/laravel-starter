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

        $this->testKey = 'test-key';
        $this->testRefillKey = 'test-refill-key';
        $this->campaign = Campaign::factory()->create();

        $this->hopper = Mockery::mock(app(Hopper::class))->makePartial();
    }

    /**
     * Test getHopperKey()
     * @test
     *
     * @return void
     */
    public function get_hopper_key(): void
    {
        $actualKey = $this->hopper->getHopperKey($this->campaign->id);
        $expectedKey = "neo:outbound-call-hopper:campaign-{$this->campaign->id}";

        $this->assertEquals($expectedKey, $actualKey);
    }

    /**
     * Test getLastRefillTimestampKey()
     * @test
     *
     * @return void
     */
    public function getLastRefillTimestampKey(): void
    {
        $actualKey = $this->hopper->getLastRefillTimestampKey($this->campaign->id);
        $expectedKey = "neo:outbound-call-hopper-timestamp:campaign-{$this->campaign->id}";

        $this->assertEquals($expectedKey, $actualKey);
    }

    /**
     * Test getRaisedWithinThreeMinutesLeadKey()
     * @test
     *
     * @return void
     */
    public function get_raised_within_three_minutes_lead_key(): void
    {
        $leadId = 1;
        $actualKey = $this->hopper->getRaisedWithinThreeMinutesLeadKey($this->campaign->id, $leadId);
        $expectedKey = "neo:outbound-call-hopper-raised-within-three-minutes:campaign-{$this->campaign->id}-lead-{$leadId}";

        $this->assertEquals($expectedKey, $actualKey);
    }

    /**
     * Test addLeads()
     * @test
     *
     * @return void
     */
    public function addLeads(): void
    {
        $leadsForAdd = [
            [
                'lead_id' => 1,
                'score' => 1,
            ],
            [
                'lead_id' => 2,
                'score' => 2,
            ],
            [
                'lead_id' => 3,
                'score' => 3,
            ]
        ];
        $this->hopper
            ->shouldReceive('getHopperKey')
            ->once()
            ->with($this->campaign->id)
            ->andReturn($this->testKey)
            ->shouldReceive('checkLeadIsRaisedWithinThreeMinutes')
            ->times(3)
            ->andReturn(false);

        $closure = (new ReflectionClass(Hopper::class))
            ->getMethod('addLeads')
            ->getClosure($this->hopper);
        $closure($this->campaign, $leadsForAdd);

        $leadIds = Redis::zrange($this->testKey, 0, -1);
        $this->assertEquals(
            collect($leadsForAdd)->map(fn($l) => $l['lead_id'])->toArray(),
            $leadIds,
        );
    }

    /**
     * Test markLeadsRaisedWithinThreeMinutes()
     * @test
     *
     * @return void
     */
    public function markLeadsRaisedWithinThreeMinutes(): void
    {
        $leadIds = [666666];
        $testKey = 'testMarkLeadsRaisedWithinThreeMinutes';
        $this->hopper
            ->shouldReceive('getRaisedWithinThreeMinutesLeadKey')
            ->once()
            ->andReturn($testKey);

        $closure = (new ReflectionClass(Hopper::class))
            ->getMethod('markLeadsRaisedWithinThreeMinutes')
            ->getClosure($this->hopper);
        $closure($this->campaign, $leadIds);

        $this->assertNotNull(Redis::get($testKey));
    }

    /**
     * Test checkLeadIsRaisedWithinThreeMinutes()
     * @test
     *
     * @return void
     */
    public function checkLeadIsRaisedWithinThreeMinutes(): void
    {
        $leadId = 7749;
        Redis::set("neo:outbound-call-hopper-raised-within-three-minutes:campaign-{$this->campaign->id}-lead-{$leadId}", $leadId);

        $this->assertNotNull($this->hopper->checkLeadIsRaisedWithinThreeMinutes($this->campaign, $leadId));
    }

    /**
     * Test refillLeads()
     * @test
     *
     * @return void
     */
    public function refillLeads(): void
    {
        $fakeleadId = 9999999;
        $fakeLeadScore = 12345;
        $leadsForRefill = [
            ['lead_id' => 1, 'score' => 23456],
        ];
        Redis::zadd($this->testKey, $fakeLeadScore, $fakeleadId);

        $this->hopper
            ->shouldReceive('clearAll')
            ->once()
            ->with($this->campaign)
            ->shouldReceive('getLastRefillTimestampKey')
            ->once()
            ->with($this->campaign->id)
            ->andReturn($this->testRefillKey)
            ->shouldReceive('addLeads')
            ->once()
            ->withArgs(function ($campaign, $leads) use ($leadsForRefill) {
                return $leads === $leadsForRefill
                    && $campaign === $this->campaign;
            });

        $closure = (new ReflectionClass(Hopper::class))
            ->getMethod('refillLeads')
            ->getClosure($this->hopper);

        $closure($this->campaign, $leadsForRefill);

        $this->assertNotNull(Redis::get($this->testRefillKey));
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

    /**
     * Test handle()
     *
     *
     * @test
     * @dataProvider data_provider_for_isNeedRefilled
     *
     * @param Closure $prepareData
     * @param Closure $asserts
     * @return void
     */
    public function isNeedRefilled(Closure $prepareData, Closure $asserts): void
    {
        $timestamp = Closure::bind($prepareData, $this)();
        $closure = (new ReflectionClass(Hopper::class))
            ->getMethod('isNeedRefilled')
            ->getClosure($this->hopper);

        $result = $closure($this->campaign, $timestamp);

        Closure::bind($asserts, $this)($result);
    }

    public function data_provider_for_isNeedRefilled(): array
    {
        return [
            $this->checked_at_55_min(),
            $this->checked_at_55_min_but_no_last_refresh_timestamp(),
            $this->checked_at_59_min(),
        ];
    }

    public function checked_at_55_min(): array
    {
        $prepareData = function () {
            $fakeNow = Carbon::create(2022, 01, 18, 17, 40, 0);
            Carbon::setTestNow($fakeNow);
            $this->hopper->shouldReceive('getLastRefillTimestamp')->once()->andReturn($fakeNow);

            return now()->addMinutes(15);
        };

        $asserts = function (bool $result) {
            $this->assertNotTrue($result);
        };

        return [$prepareData, $asserts];
    }

    public function checked_at_55_min_but_no_last_refresh_timestamp(): array
    {
        $prepareData = function () {
            Carbon::setTestNow(Carbon::create(2022, 01, 18, 17, 55, 0));
            $this->hopper->shouldReceive('getLastRefillTimestamp')->once()->andReturn(null);
            return now();
        };

        $asserts = function (bool $result) {
            $this->assertTrue($result);
        };

        return [$prepareData, $asserts];
    }

    public function checked_at_59_min(): array
    {
        $prepareData = function () {
            $fakeNow = Carbon::create(2022, 01, 18, 17, 50, 0);
            Carbon::setTestNow($fakeNow);
            $this->hopper->shouldReceive('getLastRefillTimestamp')->once()->andReturn($fakeNow);

            return now()->addMinutes(9);
        };

        $asserts = function (bool $result) {
            $this->assertTrue($result);
        };

        return [$prepareData, $asserts];
    }
}
