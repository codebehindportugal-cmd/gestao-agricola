<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Origem dos streams de vídeo
    |--------------------------------------------------------------------------
    |
    | O go2rtc corre no CT 106 da rede de casa e é servido por HTTPS através do
    | Caddy em casa.codebehind.pt. O vídeo NUNCA passa por este servidor: vai
    | direto da rede local para o browser do escritório.
    |
    | Por isso o painel só mostra imagem quando é aberto de dentro de casa.
    | Fora de casa, as câmaras aparecem indisponíveis e o resto funciona.
    |
    */

    'base_url' => env('CASA_BASE_URL', 'http://192.168.1.60:1984'),

    /*
    | Caminho onde o go2rtc responde dentro do 'base_url'.
    |
    | Ligando direto ao go2rtc (http://192.168.1.60:1984) ele serve na raiz, e
    | isto fica vazio. Atrás do Caddy o bloco 'handle_path /go2rtc/*' corta o
    | prefixo antes de reencaminhar, por isso o URL público leva '/go2rtc'.
    |
    | Enquanto o casa.codebehind.pt não existir, é o IP direto que funciona —
    | mas só com o painel aberto em http. A partir de https://agro.codebehind.pt
    | o browser recusa carregar um iframe http (mixed content), e aí não há
    | volta a dar: é preciso o certificado do Passo 1.
    */
    'caminho_go2rtc' => rtrim((string) env('CASA_CAMINHO_GO2RTC', ''), '/'),

    /*
    | Nomes dos streams no go2rtc, na ordem em que aparecem no mosaico.
    | Devem coincidir com as chaves de streams: em /opt/go2rtc/go2rtc.yaml.
    */
    'cameras' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CASA_CAMERAS', 'entrada,quintal,garagem,lateral'))
    ))),

    /*
    | webrtc  -> H264, menos de 1 s de latência (é o caso das câmaras actuais)
    | mse     -> H265, 1 a 2 s, mas sem transcodificar
    */
    'modo_video' => env('CASA_MODO_VIDEO', 'webrtc'),

    /*
    |--------------------------------------------------------------------------
    | Painel
    |--------------------------------------------------------------------------
    */

    // Intervalo de actualização do feed, em segundos.
    'intervalo_feed' => (int) env('CASA_INTERVALO_FEED', 5),

    // Quantos eventos mostrar na linha temporal.
    'eventos_visiveis' => (int) env('CASA_EVENTOS_VISIVEIS', 40),

    // Dias de compromissos futuros a mostrar na coluna do calendário.
    'dias_calendario' => (int) env('CASA_DIAS_CALENDARIO', 14),

    /*
    | Sem contacto da casa durante este tempo, o painel avisa que os dados
    | podem estar velhos. O Home Assistant envia um snapshot a cada 5 minutos,
    | por isso 12 minutos tolera uma falha isolada sem dar falso alarme.
    */
    'minutos_sem_contacto' => (int) env('CASA_MINUTOS_SEM_CONTACTO', 12),

    /*
    |--------------------------------------------------------------------------
    | Ecrã sempre ligado
    |--------------------------------------------------------------------------
    |
    | O painel do escritório fica aberto semanas a fio no mesmo separador. Os
    | dados actualizam-se sozinhos a cada 'intervalo_feed', mas há três coisas
    | que nenhum feed resolve e que estas opções tratam.
    |
    */

    /*
    | Recarga completa da página, uma vez por dia, a esta hora (HH:MM).
    | Serve o que o feed não cobre: memória acumulada pelo browser ao fim de
    | semanas, e o separador que ficaria preso a uma versão antiga do JS depois
    | de um deploy caso a detecção de versão falhe.
    | Vazio desliga.
    */
    'hora_recarga' => env('CASA_HORA_RECARGA', '04:30'),

    /*
    | De quantos em quantos minutos reatar os streams das câmaras.
    |
    | Uma ligação WebRTC que cai — go2rtc reiniciado, switch a arrancar, câmara
    | sem energia — deixa a imagem congelada sem qualquer erro visível. Num
    | ecrã de parede ninguém dá por isso. Reatar de tempos a tempos custa um
    | piscar de olhos e garante que a imagem no ecrã é de agora.
    | 0 desliga.
    */
    'minutos_camaras' => (int) env('CASA_MINUTOS_CAMARAS', 30),

    /*
    |--------------------------------------------------------------------------
    | Retenção
    |--------------------------------------------------------------------------
    */

    'retencao_dias' => (int) env('CASA_RETENCAO_DIAS', 30),

];
