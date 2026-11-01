@echo off
rem ===========================================================================
rem Windows Task Scheduler Wrapper for HRIS Database Backup
rem Location: scripts/backup/backup-db.bat
rem ===========================================================================
setlocal EnableDelayedExpansion

set "SCRIPT_DIR=%~dp0"
set "PROJECT_ROOT=%SCRIPT_DIR%..\.."
cd /d "%PROJECT_ROOT%"

if not exist "storage\logs" mkdir "storage\logs"
if not exist "backups" mkdir "backups"

echo [%date% %time%] Launching automated HRIS database backup... >> storage\logs\backup.log

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%SCRIPT_DIR%backup-db.ps1" >> storage\logs\backup.log 2>&1

set "EXIT_CODE=%ERRORLEVEL%"
if %EXIT_CODE% EQU 0 (
    echo [%date% %time%] Backup finished with exit code 0. >> storage\logs\backup.log
) else (
    echo [%date% %time%] ERROR: Backup failed with exit code %EXIT_CODE%. >> storage\logs\backup.log
)

exit /b %EXIT_CODE%

