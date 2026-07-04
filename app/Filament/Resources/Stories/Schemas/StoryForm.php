<?php

namespace App\Filament\Resources\Stories\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Story / issue')
                ->columns(2)
                ->schema([
                    TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
                    Select::make('type')
                        ->options([
                            'story' => 'Story',
                            'investigation' => 'Investigation',
                            'ongoing_issue' => 'Ongoing issue',
                            'beat' => 'Beat',
                            'project' => 'Project',
                        ])->default('story')->required()->native(false),
                    Select::make('status')
                        ->options([
                            'lead' => 'Lead',
                            'reporting' => 'Reporting',
                            'records_pending' => 'Records pending',
                            'draft' => 'Draft',
                            'edit' => 'Edit',
                            'legal_review' => 'Legal review',
                            'published' => 'Published',
                            'follow_up' => 'Follow-up',
                            'archived' => 'Archived',
                        ])->default('lead')->required()->native(false),
                    Select::make('priority')
                        ->options(['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'])
                        ->default('normal')->required()->native(false),
                ]),

            Section::make('Reporting frame')
                ->schema([
                    Textarea::make('central_question')->label('Central reporting question')->rows(2),
                    Textarea::make('why_it_matters')->label('Why it matters')->rows(2),
                    Textarea::make('known_facts')->label('Known facts (confirmed baseline)')->rows(3),
                    Textarea::make('open_questions')->label('Open questions')->rows(3),
                    Textarea::make('counterarguments')->label('Counterarguments / opposing views')->rows(2),
                    Textarea::make('next_action')->label('Next action')->rows(2),
                ]),
        ]);
    }
}
