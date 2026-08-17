@echo off
REM Set Java 17 environment
set JAVA_HOME=C:\Users\xXn00bslayerXx\.jdk\jdk-17.0.16
set PATH=%JAVA_HOME%\bin;%PATH%

REM Display environment info
echo JAVA_HOME is set to: %JAVA_HOME%
java -version

REM Navigate to android directory
cd /d %~dp0

echo.
echo Ready to build. Run: gradlew clean build
pause
