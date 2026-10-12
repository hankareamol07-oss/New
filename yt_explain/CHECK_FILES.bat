@echo off
cd /d "%~dp0"
python -c "import sys, os, inspect; sys.argv=['x']; import auto; import ytstudio, ytstudio.slides as S, ytstudio.video as V, explain, avatar, vrig; print('python  :', sys.executable); print('ytstudio:', ytstudio.__file__); print('slides  :', S.__file__, os.path.getsize(S.__file__), 'bytes'); print('title() :', inspect.signature(S.Renderer.title)); print('video   :', V.__file__); print('explain :', explain.__file__); print('avatar  :', avatar.__file__); print('enc     :', getattr(__import__('ytstudio.enc', fromlist=['x']), '__file__', '?')); print('sys.path:'); [print('   ', p) for p in sys.path[:6]]"
echo.
echo Copy the text above and send it to Devin.
pause
