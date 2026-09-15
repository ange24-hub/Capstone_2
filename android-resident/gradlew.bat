@echo off
setlocal
set "RBIM_JAVA=java.exe"
if defined JAVA_HOME set "RBIM_JAVA=%JAVA_HOME%\bin\java.exe"
"%RBIM_JAVA%" -classpath "%~dp0gradle\wrapper\gradle-wrapper.jar" org.gradle.wrapper.GradleWrapperMain %*
exit /b %ERRORLEVEL%
