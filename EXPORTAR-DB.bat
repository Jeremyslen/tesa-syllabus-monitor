@echo off
title Exportar Base de Datos - TESA Syllabus Monitor
color 0A

echo.
echo ========================================================
echo       EXPORTAR BASE DE DATOS A DOCKER
echo ========================================================
echo.
echo  Base de datos: tesa_syllabus_monitor
echo  Usuario:       root (sin contrasena)
echo  Destino:       database\init.sql
echo.
echo ========================================================
echo.

REM Crear carpeta database si no existe
if not exist database mkdir database

REM Exportar base de datos
echo [*] Exportando base de datos...
echo.

"C:\xampp\mysql\bin\mysqldump.exe" -u root tesa_syllabus_monitor > database\init.sql 2>error.log

REM Verificar resultado
if %ERRORLEVEL% EQU 0 (
    del error.log 2>nul
    echo ========================================================
    echo     [OK] EXPORTACION EXITOSA
    echo ========================================================
    echo.
    echo  Archivo creado: database\init.sql
    echo.
    for %%I in (database\init.sql) do echo  Tamano: %%~zI bytes
    echo.
    echo ========================================================
    echo     PASOS SIGUIENTES:
    echo ========================================================
    echo.
    echo  1. Verifica tu archivo config/config.php
    echo  2. Copia tus credenciales de BrightSpace al archivo .env
    echo  3. Abre PowerShell o CMD en la carpeta del proyecto
    echo  4. Ejecuta: docker-compose up -d
    echo  5. Espera 30 segundos
    echo  6. Abre: http://localhost:8080
    echo.
    echo ========================================================
    echo.
) else (
    echo ========================================================
    echo     [ERROR] NO SE PUDO EXPORTAR
    echo ========================================================
    echo.
    type error.log
    echo.
    echo  SOLUCIONES:
    echo.
    echo  1. Verifica que MySQL este corriendo en XAMPP
    echo  2. Verifica que la base de datos 'tesa_syllabus_monitor' exista
    echo  3. Si XAMPP esta en otra ubicacion, edita este archivo
    echo     y cambia la ruta de mysqldump.exe
    echo.
    echo ========================================================
    echo.
)

pause
