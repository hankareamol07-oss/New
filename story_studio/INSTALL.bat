@echo off
cd /d "%~dp0"
python -m pip install -r requirements.txt
if not exist config.json copy config.example.json config.json >nul
echo.
echo Done. Now double-click START_GUI.bat  (ffmpeg must be installed or ffmpeg.exe placed in this folder)
pause
