<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Redis;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        /** @phpstan-ignore-next-line  */
        Redis::flushDB();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        /** @phpstan-ignore-next-line  */
        Redis::flushDB();
    }
}
