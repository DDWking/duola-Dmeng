"""Install only Godot's Web templates from the official full template archive."""

from __future__ import annotations

import io
import os
import re
import zipfile
from pathlib import Path

import requests


VERSION = "4.7.1.stable"
ARCHIVE_URL = (
    "https://github.com/godotengine/godot-builds/releases/download/4.7.1-stable/"
    "Godot_v4.7.1-stable_export_templates.tpz"
)
TARGET_NAMES = {"web_nothreads_debug.zip", "web_nothreads_release.zip"}


class HttpRangeReader(io.RawIOBase):
    """Seekable HTTP reader with an 8 MiB cache for Python's zip reader."""

    def __init__(self, url: str) -> None:
        self.url = url
        self.position = 0
        self.cache_start = 0
        self.cache = b""
        response = requests.get(
            url, headers={"Range": "bytes=0-0"}, stream=True, timeout=60
        )
        response.raise_for_status()
        content_range = response.headers.get("Content-Range", "")
        match = re.search(r"/(\d+)$", content_range)
        if response.status_code != 206 or not match:
            response.close()
            raise RuntimeError("官方服务器未提供分段下载。")
        self.length = int(match.group(1))
        response.close()

    def readable(self) -> bool:
        return True

    def seekable(self) -> bool:
        return True

    def tell(self) -> int:
        return self.position

    def seek(self, offset: int, whence: int = os.SEEK_SET) -> int:
        if whence == os.SEEK_SET:
            self.position = offset
        elif whence == os.SEEK_CUR:
            self.position += offset
        elif whence == os.SEEK_END:
            self.position = self.length + offset
        else:
            raise ValueError("不支持的 seek 模式")
        return self.position

    def read(self, size: int = -1) -> bytes:
        if self.position >= self.length:
            return b""
        if size < 0:
            size = self.length - self.position
        size = min(size, self.length - self.position)
        cache_end = self.cache_start + len(self.cache)
        if self.cache_start <= self.position and self.position + size <= cache_end:
            start = self.position - self.cache_start
            self.position += size
            return self.cache[start : start + size]

        fetch_size = max(size, 8 * 1024 * 1024)
        fetch_end = min(self.length - 1, self.position + fetch_size - 1)
        response = requests.get(
            self.url,
            headers={"Range": f"bytes={self.position}-{fetch_end}"},
            timeout=120,
        )
        response.raise_for_status()
        if response.status_code != 206:
            raise RuntimeError("下载过程中服务器停止提供分段内容。")
        self.cache_start = self.position
        self.cache = response.content
        result = self.cache[:size]
        self.position += len(result)
        return result


def main() -> None:
    appdata = os.environ.get("APPDATA")
    if not appdata:
        raise SystemExit("无法定位 Godot 用户模板目录。")
    target_dir = Path(appdata) / "Godot" / "export_templates" / VERSION
    target_dir.mkdir(parents=True, exist_ok=True)

    remote = HttpRangeReader(ARCHIVE_URL)
    with zipfile.ZipFile(remote) as archive:
        members = {
            Path(info.filename).name: info
            for info in archive.infolist()
            if Path(info.filename).name in TARGET_NAMES
        }
        missing = TARGET_NAMES - members.keys()
        if missing:
            raise SystemExit("官方模板包缺少：" + "、".join(sorted(missing)))
        for name in sorted(TARGET_NAMES):
            destination = target_dir / name
            print(f"正在提取 {name}……")
            destination.write_bytes(archive.read(members[name]))
            print(f"  {destination} ({destination.stat().st_size / 1024 / 1024:.1f} MiB)")

    print("Godot Web 导出模板安装完成。")


if __name__ == "__main__":
    main()
