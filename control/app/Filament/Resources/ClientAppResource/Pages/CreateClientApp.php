<?php

namespace App\Filament\Resources\ClientAppResource\Pages;

use App\Filament\Resources\ClientAppResource;
use App\Filament\Support\Audit;
use App\Models\AppKey;
use App\Models\Capability;
use App\Models\ClientApp;
use App\Models\Pool;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/** Thêm ứng dụng theo 3 bước. Mặc định chặt: không khả năng nào được cấp nếu không tích chọn. */
class CreateClientApp extends CreateRecord
{
    use HasWizard;

    protected static string $resource = ClientAppResource::class;

    protected static ?string $title = 'Thêm ứng dụng';

    private string $plainKey = '';

    protected function getSteps(): array
    {
        return [
            Step::make('Thông tin')->icon('heroicon-m-identification')->description('Ứng dụng nào sẽ dùng cổng này?')->schema([
                TextInput::make('name')->label('Tên ứng dụng')->required()->maxLength(96)->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug((string) $state))),
                TextInput::make('slug')->label('Mã định danh')->required()->maxLength(96)->unique(table: 'apps', column: 'slug')->helperText('Chữ thường, số và dấu gạch ngang. Dùng trong nhật ký và API.'),
                TextInput::make('owner')->label('Bộ phận phụ trách')->maxLength(96),
                Textarea::make('description')->label('Mô tả ngắn')->rows(2)->maxLength(500),
            ]),
            Step::make('Khả năng và hạn mức')->icon('heroicon-m-adjustments-horizontal')->description('Chỉ cấp đúng những gì ứng dụng cần.')->schema([
                CheckboxList::make('capabilities')->label('Khả năng được cấp')->required()
                    ->options(fn () => Capability::where('enabled', true)->pluck('name', 'id')->all())
                    ->descriptions(fn () => Capability::where('enabled', true)->get()->mapWithKeys(fn ($c) => [$c->id => $c->description . ($c->risk_level->value === 'high' ? ' (Rủi ro cao: cần cân nhắc.)' : '')])->all())
                    ->helperText('Mặc định không có gì. Có thể đổi sau.'),
                Select::make('pool_id')->label('Pool xử lý')->options(fn () => Pool::pluck('name', 'id')->all())->required()->native(false),
                Grid::make(['default' => 1, 'md' => 4])->schema([
                    TextInput::make('rpm')->label('Yêu cầu / phút')->numeric()->default(20)->minValue(1)->required(),
                    TextInput::make('max_concurrency')->label('Song song tối đa')->numeric()->default(2)->minValue(1)->required(),
                    TextInput::make('units_per_day')->label('Đơn vị / ngày')->numeric()->default(50000)->minValue(1)->required()->helperText('Đơn vị quy đổi nội bộ.'),
                    Select::make('mode')->label('Khi vượt hạn mức')->options(['hard' => 'Chặn (cứng)', 'soft' => 'Chỉ cảnh báo (mềm)'])->default('hard')->native(false)->required(),
                ]),
            ]),
            Step::make('Xem lại')->icon('heroicon-m-check-badge')->description('Kiểm tra trước khi tạo khóa.')->schema([
                Placeholder::make('review')->hiddenLabel()->content(function (Get $get): HtmlString {
                    $caps = Capability::whereIn('id', (array) $get('capabilities'))->pluck('name')->implode(', ') ?: 'Chưa chọn';
                    $pool = Pool::find($get('pool_id'))?->name ?? '—';
                    $rows = [
                        'Tên' => e($get('name')), 'Mã định danh' => e($get('slug')), 'Khả năng' => e($caps), 'Pool' => e($pool),
                        'Hạn mức' => e(number_format((int) $get('rpm')) . ' yêu cầu/phút · ' . (int) $get('max_concurrency') . ' song song · ' . number_format((int) $get('units_per_day'), 0, ',', '.') . ' đơn vị/ngày · ' . ($get('mode') === 'soft' ? 'mềm' : 'cứng')),
                    ];
                    $html = '<dl class="grid gap-x-6 gap-y-2 text-sm md:grid-cols-[10rem_1fr]">';
                    foreach ($rows as $k => $v) {
                        $html .= "<dt class='text-gray-600 dark:text-gray-400'>{$k}</dt><dd class='font-medium text-gray-950 dark:text-white'>{$v}</dd>";
                    }

                    return new HtmlString($html . '</dl><p class="mt-4 rounded-lg bg-primary-50 p-3 text-sm text-primary-900 dark:bg-primary-950 dark:text-primary-100">Sau khi tạo, khóa API chỉ hiện <strong>một lần</strong>. Hãy sao chép và lưu vào nơi an toàn của ứng dụng.</p>');
                }),
            ]),
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        $app = ClientApp::create(['name' => $data['name'], 'slug' => $data['slug'], 'owner' => $data['owner'] ?? null, 'description' => $data['description'] ?? null, 'status' => 'active']);
        foreach ((array) $data['capabilities'] as $capId) {
            $app->policies()->create(['capability_id' => $capId, 'pool_id' => $data['pool_id']]);
        }
        $app->limits()->create(['rpm' => $data['rpm'], 'max_concurrency' => $data['max_concurrency'], 'units_per_day' => $data['units_per_day'], 'units_per_month' => $data['units_per_day'] * 30, 'mode' => $data['mode']]);
        [, $this->plainKey] = AppKey::issue($app, 'Khóa chính');
        Audit::log('app.create', $app->name, ['khả năng' => count((array) $data['capabilities'])]);

        return $app;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()->success()->persistent()->title('Đã tạo ứng dụng và khóa API')
            ->body(new HtmlString('Khóa chỉ hiện <strong>một lần</strong>, hãy sao chép ngay:<br><code class="gw-mono break-all select-all">' . e($this->plainKey) . '</code>'));
    }
}
