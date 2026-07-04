<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Services\Finance\FinanceNetworkBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Auto-builds donation networks across all qualifying committees off the queue (it can touch
 * hundreds of committees and create many entities/edges).
 */
class BuildCommitteeNetworks implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(
        public int $organizationId,
        public float $minCommitteeTotal,
        public float $minDonorTotal,
        public int $maxDonorsPerCommittee,
    ) {}

    public function handle(FinanceNetworkBuilder $builder): void
    {
        Organization::useOrganization($this->organizationId);

        $builder->buildAllCommittees(
            $this->minCommitteeTotal,
            $this->minDonorTotal,
            $this->maxDonorsPerCommittee,
        );
    }
}
