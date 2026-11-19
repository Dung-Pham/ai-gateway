<?php

namespace App\Filament\Resources\ClientAppResource\RelationManagers;

use App\Filament\Support\Audit;
use App\Models\AppKey;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class KeysRelationManager extends RelationManager
{
    protected static string $relationship = 'keys';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'Khóa API';
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label('Tên khóa')->weight('medium'),
                TextColumn::make('key_prefix')->label('Tiền tố')->fontFamily('mono')->formatStateUsing(fn ($state) => $state . '…')->copyable(),
                TextColumn::make('state')->label('Tình trạng')->badge()
                    ->state(fn (AppKey $r) => $r->revoked_at ? 'Đã thu hồi' : ($r->expires_at ? 'Sắp hết hạn' : 'Đang dùng'))
                    ->color(fn ($state) => match ($state) { 'Đã thu hồi' => 'gray', 'Sắp hết hạn' => 'warning', default => 'success' }),
                TextColumn::make('expires_at')->label('Hết hạn')->dateTime('d/m/Y')->placeholder('Không giới hạn')->fontFamily('mono'),
                TextColumn::make('last_used_at')->label('Dùng lần cuối')->since()->placeholder('Chưa dùng'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('issue')->label('Tạo khóa mới')->icon('heroicon-m-plus')
                    ->form([\Filament\Forms\Components\TextInput::make('name')->label('Tên khóa')->default('Khóa mới')->required()->maxLength(64)])
                    ->action(function (array $data) {
                        [, $plain] = AppKey::issue($this->getOwnerRecord(), $data['name']);
                        Audit::log('key.issue', $this->getOwnerRecord()->name, ['tên' => $data['name']]);
                        Notification::make()->success()->persistent()->title('Đã tạo khóa mới')
                            ->body(new HtmlString('Khóa chỉ hiện <strong>một lần</strong>:<br><code class="gw-mono break-all select-all">' . e($plain) . '</code>'))->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('revoke')->label('Thu hồi')->icon('heroicon-m-no-symbol')->color('danger')
                    ->visible(fn (AppKey $r) => $r->revoked_at === null)
                    ->requiresConfirmation()->modalHeading('Thu hồi khóa này?')
                    ->modalDescription('Ứng dụng đang dùng khóa này sẽ bị từ chối ngay lập tức. Không hoàn tác được: nếu cần, hãy tạo khóa mới.')
                    ->action(function (AppKey $r) {
                        $r->update(['revoked_at' => now()]);
                        Audit::log('key.revoke', $this->getOwnerRecord()->name, ['tên' => $r->name]);
                        Notification::make()->title('Đã thu hồi khóa')->success()->send();
                    }),
            ]);
    }
}
