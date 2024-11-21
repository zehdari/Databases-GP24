@echo off
:: Define the source and destination directories
set SOURCE_DIR=data
set DEST_DIR=C:\tempdata

:: Define the SQL script file path
set SQL_SCRIPT=utils\CreateDBWindows.sql

:: Define the CSV files to copy (space-separated list)
set CSV_FILES="META Historical Data.csv" "AAPL Historical Data.csv" "GOOGL Historical Data.csv" "AMZN Historical Data.csv"

:: Create the destination directory if it doesn't exist
if not exist "%DEST_DIR%" (
    echo Creating directory %DEST_DIR%...
    mkdir "%DEST_DIR%"
)

:: Copy the CSV files
echo Copying CSV files to %DEST_DIR%...
for %%F in (%CSV_FILES%) do (
    if exist "%SOURCE_DIR%\%%~F" (
        copy "%SOURCE_DIR%\%%~F" "%DEST_DIR%\%%~F" >nul
        echo Copied %SOURCE_DIR%\%%~F to %DEST_DIR%\%%~F.
    ) else (
        echo Warning: %SOURCE_DIR%\%%~F does not exist and was skipped.
    )
)

:: Check if the SQL script exists
if exist "%SQL_SCRIPT%" (
    echo Running SQL script %SQL_SCRIPT%...
    echo Enter password for root:
    mysql --local-infile=1 -u root -p < "%SQL_SCRIPT%"
    if not errorlevel 1 (
        echo Database setup complete.
    ) else (
        echo Error: Failed to run the SQL script.
        exit /b 1
    )
) else (
    echo Error: SQL script %SQL_SCRIPT% not found.
    exit /b 1
)

pause
