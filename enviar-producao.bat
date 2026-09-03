@echo off
REM ============================================================
REM  enviar-producao.bat - Deploy unico para agro.codebehind.pt
REM  Uso: enviar-producao.bat "mensagem do commit"
REM ============================================================
setlocal
cd /d "%~dp0"

set MSG=%~1
if "%MSG%"=="" set MSG=deploy: atualizacao

echo.
echo ==^> [1/4] Reparar/atualizar indice git...
git reset -q

echo ==^> [2/4] Commit...
git add -A
git commit -m "%MSG%"
if errorlevel 1 echo (nada novo para commit - a continuar)

echo ==^> [3/4] Push para GitHub...
git push origin main
if errorlevel 1 (
    echo ERRO no push. Verifica a ligacao/credenciais GitHub.
    exit /b 1
)

echo ==^> [4/4] Deploy no servidor via SSH...
set SSHKEY=%USERPROFILE%\.ssh\ateneya_vps_key
if not exist "%SSHKEY%" set SSHKEY=%USERPROFILE%\.ssh\id_rsa
if not exist "%SSHKEY%" (
    echo ERRO: nao encontrei chave SSH nenhuma em %USERPROFILE%\.ssh
    echo O codigo foi para o GitHub mas o servidor NAO foi actualizado.
    exit /b 1
)

REM O que o servidor responde fica tambem em _local\ultimo-deploy.txt, para se
REM poder ver o que correu mal sem ter de repetir o deploy as cegas.
if not exist "_local" mkdir "_local"
ssh -i "%SSHKEY%" -o StrictHostKeyChecking=no root@agro.codebehind.pt "bash -s -- --local" < git-deploy-server.sh > "_local\ultimo-deploy.txt" 2>&1
set DEPLOYCODE=%errorlevel%
type "_local\ultimo-deploy.txt"

echo.
if not "%DEPLOYCODE%"=="0" (
    echo ============ DEPLOY FALHOU ^(codigo %DEPLOYCODE%^) ============
    echo O codigo esta no GitHub mas a producao ficou como estava.
    echo Log completo em _local\ultimo-deploy.txt
    exit /b %DEPLOYCODE%
)
echo ============ DEPLOY CONCLUIDO ============
echo Log em _local\ultimo-deploy.txt
endlocal
