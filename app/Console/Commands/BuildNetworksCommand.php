<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\Finance\FinanceNetworkBuilder;
use Illuminate\Console\Command;

class BuildNetworksCommand extends Command
{
    protected $signature = 'finance:build-networks
        {--org=1 : organization id}
        {--min-committee=10000 : only committees whose total clears this}
        {--min-donor=1000 : only donors who gave at least this to a committee}
        {--cap=50 : max donors promoted per committee}';

    protected $description = 'Auto-build donation networks (committee → significant donors) across all committees.';

    public function handle(FinanceNetworkBuilder $builder): int
    {
        Organization::useOrganization((int) $this->option('org'));

        $this->info('Building committee networks…');
        $r = $builder->buildAllCommittees(
            (float) $this->option('min-committee'),
            (float) $this->option('min-donor'),
            (int) $this->option('cap'),
        );

        $this->info("✓ {$r['committees']} committees, {$r['donors_promoted']} donors promoted, {$r['connections']} donation connections.");

        return self::SUCCESS;
    }
}
