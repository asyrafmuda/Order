<?php

namespace App\LicenseServer\Console;

use App\LicenseServer\Models\License;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('license:create {customer : Customer or shop name} {--email= : Customer email} {--max=1 : Domains it may run on at the same time}')]
#[Description('Create a purchase code on the license server')]
class CreateLicenseCommand extends Command
{
    public function handle(): int
    {
        $license = License::query()->create([
            'code' => License::newCode(),
            'product' => config('licensing.product'),
            'customer_name' => (string) $this->argument('customer'),
            'customer_email' => $this->option('email') ?: null,
            'max_activations' => max(1, (int) $this->option('max')),
        ]);

        $this->components->info("Purchase code for {$license->customer_name}:");
        $this->line("  <options=bold>{$license->code}</>");

        return self::SUCCESS;
    }
}
