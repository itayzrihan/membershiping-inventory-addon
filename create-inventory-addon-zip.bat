@echo off
echo.
echo ========================================
echo  MEMBERSHIPING INVENTORY ADDON ZIPPER
echo ========================================
echo.

REM Check if Node.js is installed
node --version >nul 2>&1
if errorlevel 1 (
    echo ❌ ERROR: Node.js is not installed or not in PATH
    echo.
    echo Please install Node.js from https://nodejs.org/
    echo After installation, restart your command prompt and try again.
    echo.
    pause
    exit /b 1
)

REM Display Node.js version
echo ✅ Node.js found:
node --version
echo.

REM Check if package.json exists, if not create it
if not exist "package.json" (
    echo 📦 Creating package.json...
    echo {> package.json
    echo   "name": "membershiping-inventory-addon",>> package.json
    echo   "version": "1.0.0",>> package.json
    echo   "description": "Membershiping Inventory Management Addon",>> package.json
    echo   "main": "membershiping-inventory.php",>> package.json
    echo   "scripts": {>> package.json
    echo     "zip": "node create-zip.js">> package.json
    echo   },>> package.json
    echo   "devDependencies": {>> package.json
    echo     "archiver": "^6.0.1">> package.json
    echo   }>> package.json
    echo }>> package.json
    echo ✅ package.json created
    echo.
)

REM Check if archiver is installed
node -e "require.resolve('archiver')" >nul 2>&1
if errorlevel 1 (
    echo 📦 Installing archiver package...
    npm install archiver
    if errorlevel 1 (
        echo ❌ ERROR: Failed to install archiver package
        echo.
        echo Please check your internet connection and try again.
        echo.
        pause
        exit /b 1
    )
    echo ✅ archiver package installed
    echo.
)

REM Run the Node.js zipper
echo 🚀 Starting ZIP creation process...
echo.
node create-zip.js

REM Check if the zip was created successfully
if exist "membershiping-inventory-addon.zip" (
    echo.
    echo ✅ SUCCESS: membershiping-inventory-addon.zip created successfully!
    echo.
    echo 📄 File size:
    for %%A in (membershiping-inventory-addon.zip) do echo    %%~zA bytes
    echo.
    echo 📁 Location: %CD%\membershiping-inventory-addon.zip
    echo.
) else (
    echo.
    echo ❌ ERROR: ZIP file was not created
    echo.
)

echo Press any key to exit...
pause >nul
