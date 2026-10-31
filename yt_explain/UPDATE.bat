@echo off
rem Copies the bundled yt_studio\ytstudio files from yt_explain\_update\ into ..\yt_studio\ytstudio (run after unzipping a fix).
cd /d "%~dp0"
if not exist "_update\ytstudio" (echo _update\ytstudio folder not found - unzip the fix over yt_explain_full_v2 first. & pause & exit /b 1)
if not exist "..\yt_studio\ytstudio" mkdir "..\yt_studio\ytstudio"
xcopy /y /s /q "_update\ytstudio\*" "..\yt_studio\ytstudio\" >nul
echo Updated ..\yt_studio\ytstudio
echo.
call CHECK_FILES.bat
