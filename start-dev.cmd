@echo off
setlocal

REM Fuerza Node de Laragon para esta sesion
set "PATH=C:\laragon\bin\nodejs\node v24.14;%PATH%"

cd /d "%~dp0"

echo [INFO] Usando Node:
where node
node -v
echo.

echo [INFO] Iniciando Vite...
npm run dev
