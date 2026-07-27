<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SubscriptionExpireMessageJob implements ShouldQueue
{
    use Queueable;

    private $customer;
    /**
     * Create a new job instance.
     */
    public function __construct($customer)
    {
        $this->customer=  $customer;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        info('inside job class');
        $this->customer->update([
            "updated_at"=>now()
        ]);
    }
}
