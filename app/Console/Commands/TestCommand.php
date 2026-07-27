<?php

namespace App\Console\Commands;

use App\Jobs\SubscriptionExpireMessageJob;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:test-command')]
#[Description('Command description')]
class TestCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $customers = Customer::get();
        foreach ($customers as $customer) {
            info('line 23');
            dispatch(new SubscriptionExpireMessageJob($customer))->onQueue('redis');
        }
    }
}
