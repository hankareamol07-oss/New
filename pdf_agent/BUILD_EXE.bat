@echo off
REM Builds pdf_agent.exe (one folder) with PyInstaller. Run once: pip install -r requirements.txt
cd /d %~dp0
pip install -r requirements.txt
pyinstaller --noconfirm --name pdf_agent --windowed --collect-all pymupdf --hidden-import google.auth.transport.requests ^
  --hidden-import google.oauth2.service_account --add-data "config.example.json;." main.py
echo.
echo Done: dist\pdf_agent\pdf_agent.exe  (copy config.json, gcp_key.json and an input\ folder next to the exe)
pause
