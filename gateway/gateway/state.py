"""Trạng thái ĐỘNG trong bộ nhớ (một tiến trình): chỗ chạy song song, cooldown, tài khoản hết phiên/ngắt mạch, hạn mức theo phút.

Gateway KHÔNG ghi bảng accounts (do giao diện điều khiển sở hữu); trạng thái này công bố qua /internal/state.
Hạn chế đã biết: chạy nhiều tiến trình gateway thì mỗi tiến trình có bộ đếm riêng; muốn mở rộng phải chuyển sang Redis.
"""
import threading
import time
from collections import defaultdict, deque


class State:
    def __init__(self):
        self.lock = threading.Lock()
        self.inflight = defaultdict(int)          # nhãn tài khoản -> số lượt đang chạy
        self.cooldown_until = {}                  # nhãn -> thời điểm epoch
        self.flags = {}                           # nhãn -> "auth_expired" | "tripped"
        self.last_error = {}
        self.quota = {}
        self.checked_at = {}                      # nhãn -> epoch lần "Kiểm tra ngay" gần nhất
        self.app_inflight = defaultdict(int)
        self.rpm = defaultdict(deque)
        self.rr = defaultdict(int)
        self.counters = defaultdict(int)

    def acquire_slot(self, label, max_conc) -> bool:
        with self.lock:
            if self.inflight[label] >= max_conc:
                return False
            self.inflight[label] += 1
            return True

    def release_slot(self, label):
        with self.lock:
            self.inflight[label] = max(0, self.inflight[label] - 1)

    def cooling(self, label, now) -> bool:
        return self.cooldown_until.get(label, 0) > now

    def set_cooldown(self, label, until):
        self.cooldown_until[label] = until

    def check_rpm(self, app_id, limit, now) -> bool:
        with self.lock:
            q = self.rpm[app_id]
            while q and q[0] <= now - 60:
                q.popleft()
            if limit and len(q) >= limit:
                return False
            q.append(now)
            return True

    def inc(self, name, n=1):
        with self.lock:
            self.counters[name] += n
