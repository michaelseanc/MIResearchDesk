<?php

namespace App\Filament\Resources\Relationships\Pages;

use App\Filament\Resources\Relationships\RelationshipResource;
use App\Models\Relationship;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditRelationship extends EditRecord
{
    protected static string $resource = RelationshipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->markVerifiedAction(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * Promote a connection to "Verified" — allowed only once at least one piece of evidence is
     * attached. This is the single sanctioned path to Verified; the form never offers it directly.
     */
    protected function markVerifiedAction(): Action
    {
        return Action::make('markVerified')
            ->label('Mark as Verified')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (): bool => $this->record->verification_state !== Relationship::VERIFIED)
            ->action(function (): void {
                if ($this->record->evidence()->count() === 0) {
                    Notification::make()
                        ->title('Attach evidence first')
                        ->body('A connection can only be verified once at least one citation is attached in the Evidence panel below.')
                        ->warning()
                        ->send();

                    return;
                }

                $this->record->update([
                    'verification_state' => Relationship::VERIFIED,
                    'last_reviewed_at' => now(),
                    'last_reviewed_by' => auth()->id(),
                ]);

                $this->refreshFormData(['verification_state']);

                Notification::make()
                    ->title('Connection verified')
                    ->success()
                    ->send();
            });
    }
}
