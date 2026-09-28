#!/bin/bash
# Build two self-contained zips: yt_swadhyay.zip and yt_explain.zip
# Each = project folder + yt_common + yt_studio engine (code/assets only; no config.json/tokens/output of yt_studio).
set -e
cd "$(dirname "$0")"
for P in yt_swadhyay yt_explain; do
  T=/tmp/pack_$P; rm -rf "$T"; mkdir -p "$T/$P"
  cp -r yt_common "$T/"
  mkdir -p "$T/yt_studio"
  cp -r yt_studio/ytstudio yt_studio/assets yt_studio/style yt_studio/requirements.txt yt_studio/config.example.json yt_studio/README.md "$T/yt_studio/"
  rsync -a --exclude output --exclude __pycache__ --exclude client_secret.json --exclude youtube_token.json --exclude '*.pyc' "$P/" "$T/$P/"
  cp "$P/config.json" "$T/$P/config.json"   # user's own API keys (user asked for keys pre-filled)
  # one sample output for reference
  S=$(ls -d $P/output/*/ 2>/dev/null | head -1)
  if [ -n "$S" ]; then mkdir -p "$T/$P/output"; rsync -a --include='*/' --include='video.mp4' --include='thumbnail.png' --include='script.json' --include='youtube_description.txt' --exclude='*' "$S" "$T/$P/output/$(basename $S)/"; fi
  cat > "$T/$P/INSTALL.bat" <<EOF
@echo off
cd /d %~dp0
python -m pip install -r ..\yt_studio\requirements.txt
echo Put client_secret.json (and youtube_token.json if you already have it) into ..\yt_studio\  then run AUTO_UPLOAD.bat
pause
EOF
  cat > "$T/$P/AUTO_UPLOAD.bat" <<EOF
@echo off
cd /d %~dp0
python auto.py %*
pause
EOF
  find "$T" -name __pycache__ -prune -exec rm -rf {} +
  rm -f /home/ubuntu/$P.zip
  (cd "$T" && zip -qr /home/ubuntu/$P.zip .)
  ls -la /home/ubuntu/$P.zip
done
