<x-filament-panels::page>
    <x-filament::section icon="heroicon-m-exclamation-triangle" icon-color="danger" heading="Khi nào dùng">
        <p class="text-sm text-gray-700 dark:text-gray-300">
            Dùng khi nghi ngờ khóa bị lộ, một ứng dụng chạy tràn hoặc cần dừng mọi thứ để điều tra.
            Thao tác này tạm ngưng <strong>mọi ứng dụng đang hoạt động</strong> cùng lúc. Nó không thu hồi khóa và không xóa dữ liệu.
            Để dừng riêng một ứng dụng, một khóa hoặc một tài khoản AI, hãy vào trang của chính đối tượng đó.
        </p>
        <div class="mt-5 flex flex-wrap gap-3">
            {{ $this->stopAction }}
            {{ $this->restoreAction }}
        </div>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-panels::page>
