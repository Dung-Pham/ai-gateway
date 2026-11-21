<x-filament-panels::page>
    @php $label = $this->record->label; @endphp

    @unless ($this->supported())
        <x-filament::section heading="Hãng này đăng nhập bằng terminal">
            <p class="text-sm text-gray-700 dark:text-gray-300">
                Antigravity (agy) là chương trình dòng lệnh tương tác nên chưa đăng nhập được ngay trên giao diện.
                Quay lại trang tài khoản và bấm <strong>"Đăng nhập bằng terminal"</strong> để xem các bước.
            </p>
        </x-filament::section>
    @else
        <div @if (in_array($this->state, ['awaiting_device', 'verifying', 'awaiting_code'], true)) wire:poll.3s="poll" @endif class="space-y-4">

            @if ($this->state === 'idle')
                <x-filament::section heading="Đăng nhập {{ $label }}">
                    <div class="space-y-3 text-sm text-gray-700 dark:text-gray-300">
                        <p>Bạn sẽ đăng nhập tài khoản AI ngay tại đây, <strong>không cần SSH</strong>. Mật khẩu và mã 2FA nhập <strong>trên trang của hãng</strong>, không đi qua Cổng AI.</p>
                        <ul style="list-style: disc; padding-left: 1.4rem; display: grid; gap: .35rem;">
                            <li>Dùng tài khoản AI <strong>riêng cho Cổng AI</strong>, không dùng tài khoản lập trình hằng ngày của bạn.</li>
                            <li>Mỗi tài khoản chỉ nên đăng nhập ở <strong>một nơi</strong>: đăng nhập ở nơi khác có thể làm phiên này mất hiệu lực.</li>
                            @if ($this->provider() === 'claude')
                                <li>Sau khi đăng nhập trên trang của hãng, bạn sẽ nhận một <strong>mã</strong>: sao chép toàn bộ rồi dán lại vào đây.</li>
                            @else
                                <li>Trang của hãng sẽ yêu cầu nhập một <strong>mã thiết bị</strong> do trang này hiện. Không cần dán gì ngược lại.</li>
                            @endif
                        </ul>
                        @if ($this->message !== '')<p style="color:#b42318">{{ $this->message }}</p>@endif
                    </div>
                    <div class="mt-4">
                        <x-filament::button wire:click="start" wire:loading.attr="disabled" wire:target="start" size="lg" icon="heroicon-m-key">Bắt đầu đăng nhập</x-filament::button>
                        <span wire:loading wire:target="start" class="ml-3 text-sm text-gray-600 dark:text-gray-400">Đang khởi động trình đăng nhập…</span>
                    </div>
                </x-filament::section>
            @endif

            @if (in_array($this->state, ['awaiting_code', 'verifying'], true) && $this->kind === 'paste')
                <x-filament::section heading="Bước 1: mở trang đăng nhập của hãng">
                    @if ($this->url)
                        <p class="mb-3 text-sm text-gray-700 dark:text-gray-300">Mở trang bên dưới ở máy của bạn, đăng nhập (có 2FA), rồi sao chép <strong>toàn bộ mã</strong> hiện ra.</p>
                        <x-filament::button tag="a" :href="$this->url" target="_blank" rel="noopener noreferrer" icon="heroicon-m-arrow-top-right-on-square" size="lg">Mở trang đăng nhập</x-filament::button>
                    @endif
                </x-filament::section>
                <x-filament::section heading="Bước 2: dán mã">
                    <form wire:submit="submitCode" class="space-y-3" autocomplete="off">
                        <x-filament::input.wrapper>
                            <x-filament::input type="password" wire:model="code" autocomplete="off" spellcheck="false" placeholder="Dán mã xác nhận (chỉ dùng một lần)" aria-label="Mã xác nhận" :disabled="$this->state === 'verifying'" />
                        </x-filament::input.wrapper>
                        @if ($this->message !== '')<p role="alert" style="color:#b42318" class="text-sm">{{ $this->message }}</p>@endif
                        <p class="text-xs text-gray-600 dark:text-gray-400">Mã chỉ dùng một lần, được gửi thẳng cho chương trình đăng nhập và <strong>không được lưu hay ghi nhật ký</strong>.</p>
                        <div class="flex flex-wrap gap-2">
                            <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="submitCode" :disabled="$this->state === 'verifying'">
                                {{ $this->state === 'verifying' ? 'Đang kiểm tra…' : 'Xác nhận mã' }}
                            </x-filament::button>
                            <x-filament::button type="button" color="gray" wire:click="cancel">Hủy</x-filament::button>
                        </div>
                    </form>
                </x-filament::section>
            @endif

            @if (in_array($this->state, ['awaiting_device', 'verifying'], true) && $this->kind === 'device')
                <x-filament::section heading="Xác nhận trên trang của hãng">
                    <ol style="list-style: decimal; padding-left: 1.4rem; display: grid; gap: .6rem;" class="text-sm text-gray-700 dark:text-gray-300">
                        <li>Mở trang đăng nhập và đăng nhập tài khoản AI của bạn (có 2FA).
                            @if ($this->url)
                                <div class="mt-2"><x-filament::button tag="a" :href="$this->url" target="_blank" rel="noopener noreferrer" icon="heroicon-m-arrow-top-right-on-square" size="lg">Mở trang đăng nhập</x-filament::button></div>
                            @endif
                        </li>
                        <li>Nhập mã thiết bị này nếu trang hỏi (hoặc kiểm tra mã khớp):
                            <div x-data="{ copied: false }" class="mt-2" style="display:flex; align-items:center; gap:.75rem; flex-wrap:wrap;">
                                <code class="gw-mono" style="font-size:1.6rem; letter-spacing:.08em; background:#0b1b33; color:#f1f5ff; padding:.4rem .9rem; border-radius:.5rem;">{{ $this->deviceCode }}</code>
                                <button type="button" x-on:click="navigator.clipboard.writeText(@js($this->deviceCode)).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                        style="min-height:44px; padding:0 .9rem; border-radius:.5rem; border:1px solid #9ca3af; background:#fff; font-weight:600; cursor:pointer;">
                                    <span x-show="!copied">Sao chép mã</span><span x-show="copied" x-cloak>Đã sao chép</span>
                                </button>
                            </div>
                        </li>
                        <li>Xác nhận trên trang của hãng. Trang này <strong>tự động</strong> nhận kết quả (3 giây kiểm tra một lần).</li>
                    </ol>
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400" wire:loading.remove wire:target="poll">Đang chờ bạn xác nhận…</p>
                    <div class="mt-3"><x-filament::button color="gray" wire:click="cancel">Hủy</x-filament::button></div>
                </x-filament::section>
            @endif

            @if ($this->state === 'done')
                <x-filament::section heading="Đăng nhập thành công" icon="heroicon-m-check-circle" icon-color="success">
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $this->message }}</p>
                    @if ($this->record->login_expires_at)
                        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">Phiên này hết hạn ngày <strong>{{ $this->record->login_expires_at->setTimezone(config('gateway.timezone'))->format('d/m/Y') }}</strong> ({{ $this->record->login_source_label }}). Giao diện sẽ nhắc trước khi hết hạn.</p>
                    @endif
                    <div class="mt-4"><x-filament::button tag="a" :href="\App\Filament\Resources\AccountResource::getUrl('view', ['record' => $this->record])">Về trang tài khoản</x-filament::button></div>
                </x-filament::section>
            @endif

            @if ($this->state === 'error')
                <x-filament::section heading="Chưa đăng nhập được" icon="heroicon-m-exclamation-triangle" icon-color="danger">
                    <p role="alert" class="text-sm" style="color:#b42318">{{ $this->message }}</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-filament::button wire:click="start">Thử lại</x-filament::button>
                        <x-filament::button tag="a" color="gray" :href="\App\Filament\Resources\AccountResource::getUrl('view', ['record' => $this->record])">Về trang tài khoản</x-filament::button>
                    </div>
                </x-filament::section>
            @endif
        </div>
    @endunless
</x-filament-panels::page>
