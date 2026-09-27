@echo off
cd /d "%~dp0"
pip install -r requirements.txt
if not exist config.json copy config.example.json config.json
echo.
echo Now edit config.json (API keys) and put client_secret.json here. Then run AUTO_UPLOAD.bat
pause
