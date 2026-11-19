from .errors import ApiError


def flatten(messages) -> tuple[str, str]:
    """Gộp messages kiểu OpenAI thành (system, user). Nhiều lượt thì ghép thành bản ghi hội thoại."""
    if not isinstance(messages, list) or not messages:
        raise ApiError(400, "invalid_request", "Thiếu messages.")
    system, turns = [], []
    for m in messages:
        if not isinstance(m, dict):
            raise ApiError(400, "invalid_request", "messages không hợp lệ.")
        role, content = m.get("role"), m.get("content")
        if isinstance(content, list):
            parts = []
            for p in content:
                if isinstance(p, dict) and p.get("type") == "text":
                    parts.append(str(p.get("text", "")))
                else:
                    raise ApiError(501, "capability_not_supported", "Chỉ hỗ trợ nội dung văn bản (ảnh chưa được hỗ trợ).")
            content = "\n".join(parts)
        if not isinstance(content, str):
            raise ApiError(400, "invalid_request", "Nội dung message phải là văn bản.")
        if role in ("system", "developer"):
            system.append(content)
        elif role in ("user", "assistant"):
            turns.append((role, content))
        else:
            raise ApiError(400, "invalid_request", "Vai trò message không hợp lệ.")
    if not turns or turns[-1][0] != "user":
        raise ApiError(400, "invalid_request", "Message cuối phải là của user.")
    if len(turns) == 1:
        user = turns[0][1]
    else:
        user = "\n\n".join(("Người dùng: " if r == "user" else "Trợ lý: ") + c for r, c in turns[:-1]) + "\n\nNgười dùng: " + turns[-1][1]
    if not user.strip():
        raise ApiError(400, "invalid_request", "Nội dung rỗng.")
    return "\n\n".join(system), user
