<?php

namespace App\Filament\Resources\Documents\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Document')
                ->columns(2)
                ->schema([
                    TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
                    Select::make('source_type')
                        ->options([
                            'public_record' => 'Public record',
                            'campaign_filing' => 'Campaign filing',
                            'meeting_packet' => 'Meeting packet',
                            'interview' => 'Interview',
                            'email' => 'Email',
                            'court_filing' => 'Court filing',
                            'web_capture' => 'Web capture',
                            'foia' => 'FOIA / records response',
                            'source_doc' => 'Source document',
                        ])->native(false)->searchable(),
                    TextInput::make('origin')->label('Origin (agency / person / source)')->maxLength(255),
                    TextInput::make('original_url')->label('Original URL')->url()->maxLength(1024)->columnSpanFull(),
                    DatePicker::make('document_date')->label('Date on document'),
                    DatePicker::make('capture_date')->label('Date obtained')->default(now()),
                ]),

            Section::make('File')
                ->schema([
                    FileUpload::make('file_path')
                        ->label('Upload file')
                        ->disk('local')                 // private disk — not web-accessible
                        ->directory('documents')
                        ->visibility('private')
                        ->downloadable()
                        ->openable()
                        ->maxSize(51200)                 // 50 MB
                        ->helperText('Stored privately — never exposed at a public URL.'),
                ]),

            Section::make('Classification')
                ->columns(2)
                ->schema([
                    Select::make('sensitivity')
                        ->options([
                            'public' => 'Public',
                            'internal' => 'Internal',
                            'confidential' => 'Confidential',
                            'sealed' => 'Sealed (restricted)',
                        ])->default('internal')->required()
                        ->helperText('Sealed documents are hidden from ordinary lists, search, and exports.'),
                    Select::make('retention_status')
                        ->options([
                            'active' => 'Active',
                            'archived' => 'Archived',
                            'superseded' => 'Superseded',
                            'destroyed' => 'Destroyed under policy',
                        ])->default('active')->required(),
                    Textarea::make('ocr_text')
                        ->label('Extracted / OCR text (optional)')
                        ->rows(3)->columnSpanFull()
                        ->helperText('Paste searchable text if available. Automated OCR can be added later.'),
                ]),
        ]);
    }
}
