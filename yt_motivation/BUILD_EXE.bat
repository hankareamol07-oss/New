@echo off
cd /d "%~dp0"
python -m pip install -q pyinstaller -r ..\yt_studio\requirements.txt
python -m PyInstaller --noconfirm --onefile --windowed --name YTMotivation --paths .. --paths ..\yt_studio --hidden-import edge_tts --hidden-import googleapiclient --hidden-import google_auth_oauthlib --collect-submodules ytstudio --collect-submodules yt_common --hidden-import yt_dlp gui.py
copy /y dist\YTMotivation.exe . >nul
echo.
echo YTMotivation.exe ready in this folder (keep it here, next to config.json, the .db and data\; ..\yt_studio must stay too).
pause
