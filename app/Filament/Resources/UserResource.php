<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Profile Information')
                    ->description('Basic account details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->maxLength(255),
                    ])->columns(2),

                Section::make('Connection Type')
                    ->description('Choose WhatsApp connection method')
                    ->schema([
                        Forms\Components\Select::make('connection_type')
                            ->label('WhatsApp Connection')
                            ->options([
                                'cloud_api' => '☁️ Cloud API (Official - Meta)',
                                'web_automation' => '📱 QR Scan (Personal Phone - whatsapp-web.js)',
                            ])
                            ->default('cloud_api')
                            ->helperText('Cloud API requires Meta Business Account. QR Scan uses your personal phone.')
                            ->required(),
                        Forms\Components\Placeholder::make('whatsapp_connected_at')
                            ->label('Connected Since')
                            ->content(fn ($record) => $record?->whatsapp_connected_at ? $record->whatsapp_connected_at->diffForHumans() : 'Not connected'),
                    ])->columns(2),

                Section::make('WhatsApp Configuration')
                    ->description('Company WhatsApp Identifiers')
                    ->schema([
                        TextInput::make('whatsapp_phone_number_id')
                            ->label('Phone Number ID')
                            ->placeholder('e.g. 111559547829356')
                            ->unique(ignoreRecord: true)
                            ->required(),
                        TextInput::make('whatsapp_business_account_id')
                            ->label('Business Account ID')
                            ->placeholder('e.g. 1083959021459144'),
                        TextInput::make('whatsapp_number')
                            ->label('Public WhatsApp Number')
                            ->tel(),
                    ])->columns(3),

                Section::make('SaaS & Data Logic')
                    ->description('Message quota and target destinations')
                    ->schema([
                        TextInput::make('balance')
                            ->label('Account Balance (LKR)')
                            ->numeric()
                            ->step('0.0001')
                            ->default(500)
                            ->required(),
                        Select::make('target_mode')
                            ->options([
                                'EXCEL' => 'Google Sheets',
                                'API' => 'External REST API',
                            ])
                            ->default('EXCEL')
                            ->required(),
                        TextInput::make('target_value')
                            ->label('Destination Target')
                            ->placeholder('Sheet Name or API URL')
                            ->maxLength(255),
                    ])->columns(3),

                Section::make('Smart Auto-Reply Settings (AI Bot)')
                    ->description('Conversational AI Settings & Integrations')
                    ->schema([
                        Forms\Components\Toggle::make('is_autoreply_enabled')
                            ->label('Enable Smart Auto-Reply')
                            ->default(false),
                        TextInput::make('target_api_key')
                            ->label('WhatsApp Access Token (Meta API)')
                            ->placeholder('e.g. EAAPuQ0pZB...')
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('autoreply_message')
                            ->label('First Auto-Reply Greeting')
                            ->placeholder('e.g. Thanks for contacting us. How can I help you setup your order?')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('connection_type')
                    ->label('Connection')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'cloud_api' => 'success',
                        'web_automation' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cloud_api' => '☁️ Cloud API',
                        'web_automation' => '📱 QR Scan',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('whatsapp_phone_number_id')
                    ->label('WhatsApp ID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('balance')
                    ->label('Balance (LKR)')
                    ->prefix('Rs. ')
                    ->color('success')
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label('Platform Active')
                    ->sortable(),
                ToggleColumn::make('is_autoreply_enabled')
                    ->label('AI Active')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Account Status')
                    ->placeholder('All Users')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('addBalance')
                    ->label('Top Up Balance')
                    ->icon('heroicon-m-currency-dollar')
                    ->color('success')
                    ->form([
                        TextInput::make('amount')
                            ->label('Amount to Add/Deduct (LKR)')
                            ->helperText('Use a negative number to deduct.')
                            ->numeric()
                            ->required(),
                    ])
                    ->action(function (array $data, User $record): void {
                        $record->increment('balance', $data['amount']);
                    })
                    ->modalHeading('Update LKR Balance')
                    ->requiresConfirmation(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
