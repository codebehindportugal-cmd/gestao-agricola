<?php

return [

    /*
    | Notificacoes push por ntfy (https://ntfy.sh), lidas na app do telemovel.
    |
    | Mesma configuracao do painel Ateneya: as variaveis tem os mesmos nomes,
    | por isso o bloco do .env pode ser copiado tal e qual, incluindo o topico
    | — as duas aplicacoes escrevem para o mesmo sitio e as notificacoes
    | distinguem-se pelo titulo.
    |
    | Sem NTFY_TOPIC preenchido nao se envia nada e o resto continua a
    | funcionar. Nunca deve deitar abaixo uma tarefa por a notificacao ter
    | falhado.
    */

    'enabled' => (bool) env('NTFY_ENABLED', true),

    /* Servidor. O publico chega; um proprio tem de estar acessivel da VPS. */
    'url' => rtrim((string) env('NTFY_URL', 'https://ntfy.sh'), '/'),

    /*
    | O topico e a unica coisa que protege isto no servidor publico: quem souber
    | o nome le as notificacoes. Usa um nome longo e aleatorio.
    */
    'topic' => env('NTFY_TOPIC'),

    /* Token de acesso, se o servidor exigir autenticacao. */
    'token' => env('NTFY_TOKEN'),

    'timeout' => (int) env('NTFY_TIMEOUT', 8),

    /* Que avisos enviar. Desliga-se cada um por si. */
    'avisos' => [
        'compromissos' => (bool) env('NTFY_AVISA_COMPROMISSOS', true),
    ],

    /*
    | Quantos dias antes do prazo avisar. Um compromisso marcado com 30 dias de
    | antecedencia gera tres avisos; um criado para daqui a tres dias so gera o
    | de 1 dia. Depois do prazo passar ha ainda um aviso de atraso.
    */
    'marcos_dias' => [30, 7, 1],

];
