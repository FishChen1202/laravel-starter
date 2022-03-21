<?php
namespace Modules\Hopper\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Account\Entities\Account;
use Modules\Hopper\Entities\Campaign;

class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'account_id' => Account::factory()->create()->id,
        ];
    }
}
