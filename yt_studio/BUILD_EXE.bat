@echo off
cd /d "%~dp0"
python -m pip install -q pyinstaller -r requirements.txt
python -m PyInstaller --noconfirm --onefile --windowed --name YTStudio --hidden-import edge_tts --hidden-import googleapiclient --hidden-import google_auth_oauthlib gui.py
copy /y dist\YTStudio.exe . >nul
echo.
echo YTStudio.exe ready in this folder (keep it next to config.json and data\).
pause
