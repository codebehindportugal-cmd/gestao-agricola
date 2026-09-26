<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Compressao das fotografias
    |--------------------------------------------------------------------------
    |
    | Uma foto de telemovel sao 3 a 5 MB e nenhum ecra da aplicacao mostra mais
    | do que uns 1200 px de largura. Guardar o original e' pagar disco e
    | largura de banda por pixeis que ninguem ve. Isto reduz tudo o que entra
    | — pelo ecra, pela API ou pelo chat — antes de tocar no disco.
    |
    | O lado maximo e' generoso de proposito: uma fatura tem de continuar
    | legivel, por uma pessoa e pelo leitor automatico.
    |
    */

    'comprimir' => (bool) env('IMAGENS_COMPRIMIR', true),

    // Maior lado da imagem, em pixeis. Nunca amplia.
    'lado_maximo' => (int) env('IMAGENS_LADO_MAXIMO', 2000),

    // 0-100. Abaixo de 70 comeca a ver-se nas letras pequenas das faturas.
    'qualidade' => (int) env('IMAGENS_QUALIDADE', 80),

    // webp (metade do tamanho de um JPEG equivalente), jpeg, ou manter.
    'formato' => env('IMAGENS_FORMATO', 'webp'),

    // Ficheiros pequenos ficam como estao: recomprimir um thumbnail de 80 kB
    // so lhe tira qualidade.
    'minimo_bytes' => (int) env('IMAGENS_MINIMO_BYTES', 150 * 1024),

    /*
    | Memoria para a descompressao. Uma foto de 12 MP ocupa ~48 MB em memoria
    | enquanto e' tratada, o que rebenta com um memory_limit de 128M ja' meio
    | gasto. null deixa como esta' no servidor.
    */
    'memoria' => env('IMAGENS_MEMORIA', '512M'),

];
