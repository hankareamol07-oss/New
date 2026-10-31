@echo off
cd /d "%~dp0"
echo Sign in with the Google account that OWNS your YouTube channel.
python auto.py --login
if exist youtube_token.json (
  notepad youtube_token.json
  start "" "https://github.com/hankareamol07-oss/New/settings/secrets/actions/new"
  echo.
  echo Copy ALL text from Notepad. On the GitHub page: Name = YOUTUBE_TOKEN_JSON, Secret = paste, then "Add secret".
)
pause
