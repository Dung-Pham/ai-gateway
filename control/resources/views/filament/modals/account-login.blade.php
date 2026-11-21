<div class="space-y-4 text-sm text-gray-700 dark:text-gray-300">
    <p>
        Đăng nhập tài khoản AI do <strong>người sở hữu tài khoản</strong> làm trong terminal trên máy AI.
        Trang web này <strong>không nhận mật khẩu, mã xác nhận hay token</strong>, và bạn cũng không nên dán chúng vào bất kỳ đâu ngoài terminal đang chờ.
    </p>
    {{-- Kiểu nội tuyến: không phụ thuộc vào việc lớp Tailwind mới đã nằm trong CSS đã build hay chưa. --}}
    <ol style="list-style: decimal; padding-left: 1.4rem; display: grid; gap: .5rem;">
        @foreach ($steps as $step)
            <li>{{ $step }}</li>
        @endforeach
    </ol>
    <div x-data="{ copied: false }" style="background:#0b1b33; color:#f1f5ff; border-radius:.6rem; padding:.85rem;">
        <code class="gw-mono" style="display:block; word-break:break-all; font-size:.85rem; color:#f1f5ff;">{{ $command }}</code>
        <button type="button"
                x-on:click="navigator.clipboard.writeText(@js($command)).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                style="margin-top:.75rem; min-height:44px; padding:0 .9rem; border-radius:.5rem; border:1px solid rgba(255,255,255,.45); background:rgba(255,255,255,.12); color:#fff; font-weight:600; cursor:pointer;">
            <span x-show="!copied">Sao chép lệnh</span>
            <span x-show="copied" x-cloak>Đã sao chép</span>
        </button>
    </div>
    @if ($account->login_expires_at)
        <p class="text-xs text-gray-600 dark:text-gray-400">
            Hạn hiện tại: <strong>{{ $account->login_expires_at->setTimezone(config('gateway.timezone'))->format('d/m/Y H:i') }}</strong>
            ({{ $account->login_source_label }}). Đăng nhập lại <strong>trước hạn</strong> để không bị gián đoạn.
        </p>
    @endif
</div>
