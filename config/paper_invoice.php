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
        // Desligada por omissao: ter a chave no .env para outra coisa nao
        // pode significar gastar creditos a ler faturas sem se querer.
        'activa' => env('PAPER_INVOICE_CLAUDE', false),
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('CLAUDE_INVOICE_MODEL', 'claude-sonnet-5'),
        'timeout' => (int) env('CLAUDE_INVOICE_TIMEOUT', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tentativas de OCR
    |--------------------------------------------------------------------------
    |
    | Não há um tratamento de imagem que sirva todas as fotos. Cada tentativa
    | é lida e pontuada pelas contas das linhas (quantidade x preço = total);
    | fica a melhor, e para-se assim que uma acerta tudo.
    |
    | Cada tentativa custa alguns segundos. Se o carregamento estiver a expirar
    | no servidor, corte para as duas primeiras.
    |
    | tratamento: 'normal' (contraste global) ou 'binaria' (limiar local, para
    | sombras e papel amarelado). psm: modo de segmentação do tesseract.
    |
    */

    'tentativas' => [
        // A binarizacao apaga a marca de agua que atravessa a tabela, e o psm 3
        // deixa o tesseract analisar a estrutura da pagina em vez de assumir um
        // bloco unico. Foi esta combinacao que leu a fatura da Casa Queridos.
        ['tratamento' => 'binaria', 'psm' => '3'],
        ['tratamento' => 'binaria', 'psm' => '4'],
        ['tratamento' => 'normal', 'psm' => '3'],
    ],

    'binaries' => [
        'pdftotext' => env('PDFTOTEXT_BINARY'),
        'pdftoppm'  => env('PDFTOPPM_BINARY'),
        'zbarimg'   => env('ZBARIMG_BINARY'),
        'tesseract' => env('TESSERACT_BINARY'),
    ],

];
