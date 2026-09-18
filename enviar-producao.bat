@echo off
REM ============================================================
REM  enviar-producao.bat - Deploy unico para agro.codebehind.pt
REM  Uso: enviar-producao.bat "mensagem do commit" [opcoes]
REM
REM  Opcoes (passadas ao git-deploy-server.sh, ver la o detalhe):
REM    --testes     corre os testes no servidor ANTES de migrar e aborta
REM                 se falharem (SQLite em memoria, nao toca na BD real)
REM    --unificar   aplica a campanha unica 2025/2026; sem isto mostra
REM                 apenas o plano
REM
REM  Exemplo: enviar-producao.bat "alfaias com custos proprios" --testes
REM ============================================================
setlocal EnableDelayedExpansion
cd /d "%~dp0"

set MSG=%~1
if "%MSG%"=="" set MSG=deploy: atualizacao

REM Tudo o que vier depois da mensagem segue para o script do servidor. Cada
REM opcao vai entre plicas: a linha do ssh e lida pela shell do servidor, e um
REM "--testes=A|B" sem plicas virava um pipe a meio do comando.
set OPCOES=
shift
:ler_opcoes
if "%~1"=="" goto fim_opcoes
set OPCOES=!OPCOES! '%~1'
shift
goto ler_opcoes
:fim_opcoes

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
REM --testes sem valor usa o filtro por omissao do script do servidor. Para
REM limitar a uns quantos testes, as aspas sao obrigatorias por causa do '|':
REM   enviar-producao.bat "msg" "--testes=FaturaAlfaiaTest|UnificarCampanhasTest"
ssh -i "%SSHKEY%" -o StrictHostKeyChecking=no root@agro.codebehind.pt "bash -s -- --local!OPCOES!" < git-deploy-server.sh > "_local\ultimo-deploy.txt" 2>&1
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
