Mỗi runner (một tài khoản AI) là một tệp <nhãn>.yml trong thư mục này, do `python3 scripts/gwctl.py new-runner <thư_mục_bí_mật> <hãng> <nhãn>` sinh ra.
Tệp không chứa bí mật nhưng khác nhau theo từng máy nên kho này bỏ qua chúng (.gitignore).
`scripts/aigw` tự ghép mọi tệp *.yml ở đây vào docker-compose.yml.
