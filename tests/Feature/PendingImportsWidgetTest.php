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
        $batch->forceFill(['created_at' => now()->subMinutes(5)])->save(); // stuck: >3 min, nothing processing

        Livewire::test(PendingImportsWarning::class)
            ->assertSee('nothing processing')
            ->assertSee('queue:work');
    }

    public function test_silent_for_a_just_queued_import(): void
    {
        // Freshly dispatched (<3 min) — a running worker would grab it any second, so no alarm yet.
        TracerImporter::createBatch(1, 'loans', 2026);

        Livewire::test(PendingImportsWarning::class)
            ->assertDontSee('nothing processing');
    }

    public function test_silent_while_an_import_is_actively_processing(): void
    {
        // One old pending batch, but another is downloading → a worker is clearly running, so no alarm.
        TracerImporter::createBatch(1, 'loans', 2026)->forceFill(['created_at' => now()->subMinutes(10)])->save();
        TracerImporter::createBatch(1, 'contributions', 2026)->forceFill(['status' => 'parsing'])->save();

        Livewire::test(PendingImportsWarning::class)
            ->assertDontSee('nothing processing');
    }

    public function test_silent_when_imports_have_completed(): void
    {
        $batch = TracerImporter::createBatch(1, 'contributions', 2026);
        $batch->forceFill(['status' => 'completed', 'created_at' => now()->subMinutes(5)])->save();

        Livewire::test(PendingImportsWarning::class)
            ->assertDontSee('nothing processing');
    }
}
