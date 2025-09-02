// Servicio centralizado para cargar voces dinámicamente
export class VoiceService {
    static voicesCache = null;
    static cacheTimestamp = 0;
    static CACHE_DURATION = 60000; // 1 minuto

    static async loadVoices() {
        // Cache por 1 minuto para no hacer tantas llamadas
        if (this.voicesCache && (Date.now() - this.cacheTimestamp < this.CACHE_DURATION)) {
            return this.voicesCache;
        }

        try {
            const response = await fetch('/api/generate.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'list_voices'})
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.voicesCache = data.voices;
                this.cacheTimestamp = Date.now();
                return data.voices;
            }
        } catch (error) {
            console.error('Error loading voices:', error);
        }
        
        // Fallback: solo Juan Carlos
        return {
            juan_carlos: {
                id: 'G4IAP30yc6c1gK0csDfu',
                label: 'Juan Carlos',
                gender: 'M'
            }
        };
    }

    static clearCache() {
        this.voicesCache = null;
        this.cacheTimestamp = 0;
    }
}
