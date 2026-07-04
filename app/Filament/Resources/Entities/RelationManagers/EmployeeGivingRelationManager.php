<?php

namespace App\Filament\Resources\Entities\RelationManagers;

use App\Filament\Resources\Entities\EntityResource;
use App\Models\FinanceTransaction;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Colorado TRACER — contributions made by people who list THIS organization as their employer. These
 * are the individuals' own donations (not the organization's), surfaced here as an investigative
 * "money from this company's people" view. Read-only and deliberately NOT attributed to the org, so
 * donation totals and the relationship graph stay accurate.
 */
class EmployeeGivingRelationManager extends RelationManager
{
    protected static string $relationship = 'employeeContributions';

    protected static ?string $title = 'Contributions by employees';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-briefcase';

    /** Only meaningful for organizations — a person doesn't employ donors. */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->isOrganizationLike();
    }

    /** Match TRACER's free-text Employer field to this org's business name(s), variant-tolerant. */
    protected function getTableQuery(): Builder|Relation|null
    {
        $groups = $this->getOwnerRecord()->nameTokenGroups();

        $query = FinanceTransaction::query()
            ->where('data_type', 'contributions')
            ->whereNotNull('employer')->where('employer', '!=', '');

        if ($groups === []) {
            return $query->whereRaw('1 = 0'); // no matchable name → show nothing
        }

        // OR across name variants; within each name every significant token must appear.
        return $query->where(function (Builder $outer) use ($groups): void {
            foreach ($groups as $tokens) {
                $outer->orWhere(function (Builder $inner) use ($tokens): void {
                    foreach ($tokens as $token) {
                        $inner->where('employer', 'like', "%{$token}%");
                    }
                });
            }
        });
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('contributor_name')
            ->columns([
                TextColumn::make('transaction_date')->label('Date')->date()->sortable(),
                TextColumn::make('contributor_name')->label('Employee / donor')->searchable()->weight('medium')->wrap(),
                TextColumn::make('amount')->money('USD')->sortable()->alignEnd()
                    ->summarize(Sum::make()->money('USD')->label('Total from employees')),
                TextColumn::make('committee_name')->label('To (committee)')->searchable()->wrap(),
                TextColumn::make('candidate_name')->label('Candidate')->toggleable()->placeholder('—'),
                TextColumn::make('employer')->label('Employer (as filed in TRACER)')->searchable()->wrap()
                    ->tooltip('TRACER spelling varies; this is the raw filed value.'),
            ])
            ->recordActions([
                Action::make('openDonor')
                    ->label('Open donor')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')->iconButton()
                    ->visible(fn (FinanceTransaction $record): bool => $record->contributor_entity_id !== null)
                    ->url(fn (FinanceTransaction $record): ?string => $record->contributor_entity_id
                        ? EntityResource::getUrl('edit', ['record' => $record->contributor_entity_id])
                        : null)
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->emptyStateHeading('No employee contributions found')
            ->emptyStateDescription('No TRACER contributions list this organization in the employer field.');
    }
}
