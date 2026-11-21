import axios from 'axios';

/**
 * Télécharge un fichier (Excel, PDF, CSV...) en arrière-plan sans ouvrir de nouvel onglet
 * et sans déclencher d'avertissement de sécurité ou de popup bloqué dans Chrome.
 *
 * @param {string} url - L'URL du endpoint d'export (ex: /militaires/export?...).
 * @param {object} [options={}] - Options de configuration et de callbacks.
 * @param {string} [options.defaultFilename='export.xlsx'] - Nom de fichier par défaut.
 * @param {object} [options.toast=null] - Instance PrimeVue Toast (useToast()).
 * @param {function} [options.onStart=null] - Déclenché au lancement de la requête.
 * @param {function} [options.onFinish=null] - Déclenché à la fin (succès ou échec).
 * @param {function} [options.onProgress=null] - Callback recevant le pourcentage ou la progression.
 * @param {string} [options.loadingMessage='Génération du fichier Excel en cours... Veuillez patienter.']
 * @param {string} [options.successMessage='Fichier exporté et téléchargé avec succès !']
 * @param {string} [options.errorMessage='Une erreur est survenue lors de l\'exportation.']
 */
export async function downloadExport(url, options = {}) {
    const {
        defaultFilename = 'export.xlsx',
        toast = null,
        onStart = null,
        onFinish = null,
        onProgress = null,
        loadingMessage = 'Génération du fichier Excel en cours... Veuillez patienter.',
        successMessage = 'Fichier téléchargé avec succès !',
        errorMessage = 'Une erreur est survenue lors du téléchargement de l\'export.'
    } = options;

    if (typeof onStart === 'function') {
        onStart();
    }

    if (toast) {
        toast.add({
            severity: 'info',
            summary: 'Export en cours',
            detail: loadingMessage,
            life: 6000
        });
    }

    try {
        const response = await axios.get(url, {
            responseType: 'blob',
            onDownloadProgress: (progressEvent) => {
                if (typeof onProgress === 'function') {
                    if (progressEvent.total) {
                        const percent = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                        onProgress(percent);
                    } else {
                        onProgress(null);
                    }
                }
            }
        });

        // 1. Récupération intelligente du nom de fichier dans les en-têtes HTTP
        let filename = defaultFilename;
        const disposition = response.headers['content-disposition'];
        if (disposition) {
            const utf8Match = /filename\*=UTF-8''([^;]+)/i.exec(disposition);
            if (utf8Match && utf8Match[1]) {
                filename = decodeURIComponent(utf8Match[1]);
            } else {
                const asciiMatch = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/i.exec(disposition);
                if (asciiMatch && asciiMatch[1]) {
                    filename = asciiMatch[1].replace(/['"]/g, '').trim();
                }
            }
        }

        // 2. Création de l'objet Blob
        const contentType = response.headers['content-type'] || 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        const blob = new Blob([response.data], { type: contentType });

        // 3. Déclenchement du téléchargement direct sans ouvrir d'onglet
        const blobUrl = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = blobUrl;
        link.setAttribute('download', filename);
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();

        // 4. Nettoyage après téléchargement
        setTimeout(() => {
            if (document.body.contains(link)) {
                document.body.removeChild(link);
            }
            window.URL.revokeObjectURL(blobUrl);
        }, 1000);

        if (toast) {
            toast.add({
                severity: 'success',
                summary: 'Export terminé',
                detail: successMessage,
                life: 3000
            });
        }

        return true;
    } catch (error) {
        console.error('Erreur lors du téléchargement de l\'export :', error);
        if (toast) {
            toast.add({
                severity: 'error',
                summary: 'Échec de l\'export',
                detail: errorMessage,
                life: 5000
            });
        }
        throw error;
    } finally {
        if (typeof onFinish === 'function') {
            onFinish();
        }
    }
}
