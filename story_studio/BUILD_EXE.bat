@echo off
cd /d "%~dp0"
python -m pip install -q pyinstaller -r requirements.txt
python -m PyInstaller --noconfirm --onefile --windowed --name StoryStudio --paths . --paths ..\yt_studio ^
  --hidden-import skia --hidden-import lxml.etree --hidden-import lxml._elementpath --hidden-import edge_tts ^
  --hidden-import ytstudio.llm --hidden-import ytstudio.vertex --collect-submodules engine --collect-all edge_tts gui.py
copy /y dist\StoryStudio.exe . >nul
echo.
echo StoryStudio.exe ready in this folder. Keep it next to assets\, library\, stories\ (and ffmpeg.exe).
pause
