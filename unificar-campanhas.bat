@echo off
REM ============================================================
REM  unificar-campanhas.bat - Junta as campanhas da epoca numa so,
REM  directamente no servidor. Nao faz deploy nenhum.
REM
REM  Basta correr o ficheiro (duplo clique ou pelo nome):
REM    1. mostra o PLANO, que nao altera nada
REM    2. pergunta se e para aplicar - escrever SIM aplica
REM
REM  unificar-campanhas.bat aplicar  -> aplica sem perguntar.
REM
REM  Com --apagar, so as campanhas que a aplicacao inventou sozinha (sem nome
REM  proprio, agarradas a uma cultura e sem data de fim) sao apagadas de vez.
REM  As que foram criadas de proposito ficam arquivadas. E so se mexe no que
REM  ficou mesmo vazio depois de tudo repontado. O plano diz, campanha a
REM  campanha, qual e o destino de cada uma.
REM
REM  O comando corre tudo numa transaccao: se falhar, nada muda.
REM  O deploy faz um backup da base de dados antes de migrar; se isto
REM  for corrido muito depois do ultimo deploy, vale a pena fazer um.
REM ============================================================
setlocal
cd /d "%~dp0"

set SSHKEY=%USERPROFILE%\.ssh\ateneya_vps_key
if not exist "%SSHKEY%" set SSHKEY=%USERPROFILE%\.ssh\id_rsa
if not exist "%SSHKEY%" (
    echo ERRO: nao encontrei chave SSH nenhuma em %USERPROFILE%\.ssh
    pause
    exit /b 1
)

set REMOTO=/var/www/vhosts/agro.codebehind.pt/httpdocs
if not exist "_local" mkdir "_local"

REM ---------- 1. Plano ----------
if /I "%~1"=="aplicar" goto aplicar

echo.
echo ==^> PLANO ^(nada vai ser alterado^)
echo.
ssh -i "%SSHKEY%" -o StrictHostKeyChecking=no root@agro.codebehind.pt "cd %REMOTO% && php artisan agri:unificar-campanhas --apagar" > "_local\ultima-unificacao.txt" 2>&1
set CODIGO=%errorlevel%
type "_local\ultima-unificacao.txt"

if not "%CODIGO%"=="0" (
    echo.
    echo ============ O PLANO FALHOU ^(codigo %CODIGO%^) ============
    echo Nada foi alterado. Saida em _local\ultima-unificacao.txt
    pause
    exit /b %CODIGO%
)

echo.
echo ============================================================
set /p RESPOSTA=Aplicar estas alteracoes? Escreve SIM e Enter:
if /I not "%RESPOSTA%"=="SIM" (
    echo.
    echo Nada foi alterado.
    pause
    exit /b 0
)

REM ---------- 2. Aplicar ----------
:aplicar
echo.
echo ==^> A APLICAR a campanha unica no servidor...
echo.
ssh -i "%SSHKEY%" -o StrictHostKeyChecking=no root@agro.codebehind.pt "cd %REMOTO% && php artisan agri:unificar-campanhas --confirmar --apagar" > "_local\ultima-unificacao.txt" 2>&1
set CODIGO=%errorlevel%
type "_local\ultima-unificacao.txt"

echo.
if not "%CODIGO%"=="0" (
    echo ============ FALHOU ^(codigo %CODIGO%^) ============
    echo Nada foi alterado: o comando corre tudo numa transaccao.
    echo Saida completa em _local\ultima-unificacao.txt
    pause
    exit /b %CODIGO%
)
echo Feito. Saida guardada em _local\ultima-unificacao.txt
pause
endlocal
