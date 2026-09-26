/**
 * Reduz uma fotografia no browser, antes de ela subir.
 *
 * O servidor também comprime (CompressorImagens), mas isso só poupa disco: o
 * telemóvel ainda teve de enviar os 4 MB. Fazê-lo aqui poupa os dados móveis e
 * o tempo de espera no campo, que é onde as fatuas são fotografadas.
 *
 * Nunca falha para fora: se o browser não souber fazer isto, ou se o resultado
 * ficar maior que o original, devolve o ficheiro como veio. Mais vale enviar
 * uma foto grande do que não enviar nenhuma.
 */

const PREDEFINICOES = {
    ladoMaximo: 2000,
    qualidade: 0.8,
    // Abaixo disto não vale a pena: recomprimir uma imagem pequena só lhe tira
    // qualidade e não poupa nada que se note.
    minimoBytes: 150 * 1024,
};

/** O browser sabe gravar WebP? (Safari antigo não.) */
function suportaWebp() {
    try {
        const canvas = document.createElement('canvas');
        canvas.width = 1;
        canvas.height = 1;

        return canvas.toDataURL('image/webp').startsWith('data:image/webp');
    } catch {
        return false;
    }
}

function paraBlob(canvas, tipo, qualidade) {
    return new Promise((resolve) => {
        canvas.toBlob((blob) => resolve(blob), tipo, qualidade);
    });
}

function trocarExtensao(nome, extensao) {
    return `${String(nome).replace(/\.[^./\\]+$/, '')}.${extensao}`;
}

/**
 * @param {File} ficheiro
 * @param {{ladoMaximo?: number, qualidade?: number, minimoBytes?: number}} opcoes
 * @returns {Promise<File>} o ficheiro comprimido, ou o original
 */
export async function comprimirImagem(ficheiro, opcoes = {}) {
    const { ladoMaximo, qualidade, minimoBytes } = { ...PREDEFINICOES, ...opcoes };

    if (!ficheiro || !ficheiro.type?.startsWith('image/')) {
        return ficheiro;
    }

    if (ficheiro.size < minimoBytes) {
        return ficheiro;
    }

    try {
        // from-image: a foto tirada de lado traz a rotação só nos metadados, e
        // sem isto sairia deitada do canvas.
        const bitmap = await createImageBitmap(ficheiro, { imageOrientation: 'from-image' });

        const maior = Math.max(bitmap.width, bitmap.height);
        const escala = maior > ladoMaximo ? ladoMaximo / maior : 1;

        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(bitmap.width * escala));
        canvas.height = Math.max(1, Math.round(bitmap.height * escala));

        const contexto = canvas.getContext('2d');
        contexto.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close?.();

        const webp = suportaWebp();
        const tipo = webp ? 'image/webp' : 'image/jpeg';
        const blob = await paraBlob(canvas, tipo, qualidade);

        if (!blob || blob.size >= ficheiro.size) {
            return ficheiro;
        }

        return new File([blob], trocarExtensao(ficheiro.name, webp ? 'webp' : 'jpg'), {
            type: tipo,
            lastModified: Date.now(),
        });
    } catch {
        return ficheiro;
    }
}

/** "4,2 MB" — para dizer ao utilizador o que se poupou. */
export function emMb(bytes) {
    return `${(Number(bytes || 0) / 1048576).toFixed(1).replace('.', ',')} MB`;
}
