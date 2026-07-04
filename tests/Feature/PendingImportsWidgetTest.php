<?php

namespace Tests\Feature;

use App\Filament\Resources\FinanceImportBatches\Widgets\PendingImportsWarning;
use App\Models\Organization;
use App\Models\User;
use App\Services\Finance\TracerImporter;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PendingImportsWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $owner = User::where('email', 'owner@monumentindependent.com')->first();
        $this->actingAs($owner);
        Organization::useOrganization(1);
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);
    }

    public function test_warns_when_an_import_has_been_pending_too_long(): void
    {
        $batch = TracerImporter::createBatch(1, 'expenditures', 2026, ['counties' => ['EL PASO']]);
        $batch->forceFill(['created_at' => now()->subMinutes(2)])->save(); // stuck: no worker picked it up

        Livewire::test(PendingImportsWarning::class)
            ->assertSee('queued but not running')
            ->assertSee('queue:work');
    }

    public function test_silent_for_a_just_queued_import(): void
    {
        // Freshly dispatched (<30s) — a running worker would grab it any second, so no alarm yet.
        TracerImporter::createBatch(1, 'loans', 2026);

        Livewire::test(PendingImportsWarning::class)
            ->assertDontSee('queued but not running');
    }

    public function test_silent_when_imports_have_completed(): void
    {
        $batch = TracerImporter::createBatch(1, 'contributions', 2026);
        $batch->forceFill(['status' => 'completed', 'created_at' => now()->subMinutes(5)])->save();

        Livewire::test(PendingImportsWarning::class)
            ->assertDontSee('queued but not running');
    }
}
