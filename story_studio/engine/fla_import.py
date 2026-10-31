"""Extract Adobe Animate .fla (XFL zip) files, including malformed ones whose central directory is broken:
local file headers (PK\\x03\\x04) are scanned and each entry is inflated on its own."""
import os
import struct
import zipfile
import zlib

SIG = b"PK\x03\x04"


def _safe(name):
    name = name.replace("\\", "/").lstrip("/")
    parts = [p for p in name.split("/") if p not in ("", ".", "..")]
    return os.path.join(*parts) if parts else None


def _scan(data):
    """Yield (name, bytes) for every local file entry found in raw zip bytes."""
    pos = 0
    n = len(data)
    while True:
        i = data.find(SIG, pos)
        if i < 0 or i + 30 > n:
            return
        (_, _ver, flag, method, _t, _d, _crc, csize, usize, nlen, xlen) = struct.unpack("<IHHHHHIIIHH", data[i:i + 30])
        name_b = data[i + 30:i + 30 + nlen]
        start = i + 30 + nlen + xlen
        try:
            name = name_b.decode("utf8") if flag & 0x800 else name_b.decode("cp437")
        except UnicodeDecodeError:
            name = name_b.decode("latin1")
        body = None
        if method == 8:
            d = zlib.decompressobj(-15)
            try:
                # sizes may be zero when a data descriptor follows: inflate until the stream ends
                body = d.decompress(data[start:start + csize] if csize and not flag & 0x08 else data[start:])
                end = start + (csize if csize and not flag & 0x08 else len(data[start:]) - len(d.unused_data))
            except zlib.error:
                body, end = None, start
        elif method == 0:
            size = csize or usize
            body = data[start:start + size]
            end = start + size
        else:
            end = start
        if body is not None and not name.endswith("/"):
            yield name, body
        pos = max(end, i + 4)


def extract(fla, out_dir, log=print):
    """Extract `fla` into `out_dir` (an XFL folder with DOMDocument.xml, LIBRARY/, bin/). Returns the file count."""
    os.makedirs(out_dir, exist_ok=True)
    count = 0
    try:
        with zipfile.ZipFile(fla) as z:
            for info in z.infolist():
                rel = _safe(info.filename)
                if not rel or info.is_dir():
                    continue
                dst = os.path.join(out_dir, rel)
                os.makedirs(os.path.dirname(dst), exist_ok=True)
                with open(dst, "wb") as f:
                    f.write(z.read(info))
                count += 1
        if os.path.exists(os.path.join(out_dir, "DOMDocument.xml")):
            return count
    except Exception as e:  # malformed central directory etc.
        log(f"  [fla] zipfile failed ({str(e)[:60]}), scanning local headers")
    count = 0
    with open(fla, "rb") as f:
        data = f.read()
    for name, body in _scan(data):
        rel = _safe(name)
        if not rel:
            continue
        dst = os.path.join(out_dir, rel)
        os.makedirs(os.path.dirname(dst) or out_dir, exist_ok=True)
        with open(dst, "wb") as f:
            f.write(body)
        count += 1
    return count


if __name__ == "__main__":
    import sys
    print(extract(sys.argv[1], sys.argv[2]), "files")
