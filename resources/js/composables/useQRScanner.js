import jsQR from 'jsqr';

export function useQRScanner() {
    async function scanFromFile(file) {
        if (!file || !file.type.startsWith('image/')) {
            throw new Error('Apenas imagens são suportadas para scan QR.');
        }
        return new Promise((resolve, reject) => {
            const img = new Image();
            const url = URL.createObjectURL(file);
            img.onload = () => {
                const canvas = document.createElement('canvas');
                canvas.width = img.naturalWidth;
                canvas.height = img.naturalHeight;
                canvas.getContext('2d').drawImage(img, 0, 0);
                const imageData = canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height);
                URL.revokeObjectURL(url);
                const code = jsQR(imageData.data, imageData.width, imageData.height);
                if (code) resolve(code.data);
                else reject(new Error('QR code não encontrado na imagem.'));
            };
            img.onerror = () => {
                URL.revokeObjectURL(url);
                reject(new Error('Não foi possível ler a imagem.'));
            };
            img.src = url;
        });
    }

    // Parseia o formato AT português:
    // A:NIF*B:NIF2*C:PT*D:FT*E:N*F:YYYYMMDD*G:FT 2024/1*H:ATCUD*...*N:iva*O:total*Q:hash*R:cert
    function parseATQRCode(str) {
        const fields = {};
        str.split('*').forEach(pair => {
            const colon = pair.indexOf(':');
            if (colon > 0) fields[pair.substring(0, colon)] = pair.substring(colon + 1);
        });

        const rawDate = fields['F'] ?? '';
        const data = rawDate.length === 8
            ? `${rawDate.substring(0, 4)}-${rawDate.substring(4, 6)}-${rawDate.substring(6, 8)}`
            : null;

        const rawDocNum = fields['G'] ?? '';

        return {
            nif_fornecedor: fields['A'] ?? null,
            data,
            numero_fatura: rawDocNum || null,
            total_iva:     parseFloat(fields['N'] ?? '') || null,
            total:         parseFloat(fields['O'] ?? '') || null,
            is_at_qr:      ('A' in fields) && ('O' in fields),
        };
    }

    async function scanAndParseAT(file) {
        const raw = await scanFromFile(file);
        return parseATQRCode(raw);
    }

    return { scanFromFile, parseATQRCode, scanAndParseAT };
}
