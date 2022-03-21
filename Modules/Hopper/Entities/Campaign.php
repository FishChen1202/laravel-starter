<?php

namespace Modules\Hopper\Entities;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Account\Entities\Account;
use Modules\Hopper\Database\Factories\CampaignFactory;

/**
 * Campaign
 *
 * @property Account $account
 * @property int $account_id
 **/
class Campaign extends Model
{
    use HasFactory;
    /**
     * @var array
     */
    protected $guarded = [];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    protected static function newFactory(): Factory
    {
        return CampaignFactory::new();
    }
}
