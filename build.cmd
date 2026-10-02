@echo off
setlocal enabledelayedexpansion
REM Build script for Issue Preselection Plugin
REM Creates a production-ready .tar.gz package

echo Building Issue Preselection Plugin...

REM Read version from version.xml using PowerShell
for /f "usebackq tokens=*" %%a in (`powershell -NoProfile -Command "$xml = [xml](Get-Content 'version.xml'); $xml.version.release"`) do set VERSION=%%a

if "!VERSION!"=="" (
    echo ERROR: Could not read version from version.xml
    exit /b 1
)

echo Version: !VERSION!

REM Clean dist directory
if exist dist rmdir /s /q dist
mkdir dist

REM Create temporary build directory
set TEMP_DIR=dist\issuePreselection
mkdir %TEMP_DIR%

echo Copying plugin files...

REM Copy necessary files and directories
xcopy /E /I /Y classes %TEMP_DIR%\classes
xcopy /E /I /Y locale %TEMP_DIR%\locale
xcopy /E /I /Y templates %TEMP_DIR%\templates
if exist styles xcopy /E /I /Y styles %TEMP_DIR%\styles
copy /Y IssuePreselectionPlugin.php %TEMP_DIR%\
copy /Y version.xml %TEMP_DIR%\
copy /Y index.php %TEMP_DIR%\
if exist README.md copy /Y README.md %TEMP_DIR%\
if exist LICENSE copy /Y LICENSE %TEMP_DIR%\

echo Excluding development files (node_modules, cypress, pnpm-lock.yaml, etc.)

echo Creating archive...

REM Replace dots with hyphens in version for filename
set FILENAME_VERSION=!VERSION:.=-!

REM Create tar.gz and zip archives (requires tar command available in Windows 10+)
cd dist
tar -czf issuePreselection_!FILENAME_VERSION!.tar.gz issuePreselection
tar -a -cf issuePreselection_!FILENAME_VERSION!.zip issuePreselection
cd ..

REM Clean up temp directory
rmdir /s /q !TEMP_DIR!

echo.
echo ========================================
echo Build complete!
echo Packages:
echo   - dist\issuePreselection_!FILENAME_VERSION!.tar.gz
echo   - dist\issuePreselection_!FILENAME_VERSION!.zip
echo ========================================
echo.
echo To install:
echo 1. Upload via OJS Plugin Gallery
echo 2. Or extract to plugins/generic/ directory
echo.
