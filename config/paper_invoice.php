<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Caminhos dos binários externos
    |--------------------------------------------------------------------------
    |
    | Deixar vazio faz o extractor procurar com `which`. Preencher salta essa
    | procura, o que interessa em servidores onde o PHP da web corre com um
    | PATH reduzido.
    |
    | Isto vive num ficheiro de config e não em env() dentro do serviço de
    | propósito: com `php artisan config:cache` — que o deploy corre — as
    | chamadas a env() fora de config/ devolvem null, e as definições
    | pareceriam ignoradas sem qualquer erro.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Leitura assistida (modelo de visão)
    |--------------------------------------------------------------------------
    |
    | Só entra quando o OCR não encontra linhas, ou quando as linhas que
    | encontrou não somam o total da fatura. Sem chave configurada o sistema
    | fica-se pelo OCR e diz-o nos avisos.
    |
    */

    'claude' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('CLAUDE_INVOICE_MODEL', 'claude-sonnet-5'),
        'timeout' => (int) env('CLAUDE_INVOICE_TIMEOUT', 90),
    ],

    'binaries' => [
        'pdftotext' => env('PDFTOTEXT_BINARY'),
        'pdftoppm'  => env('PDFTOPPM_BINARY'),
        'zbarimg'   => env('ZBARIMG_BINARY'),
        'tesseract' => env('TESSERACT_BINARY'),
    ],

];
