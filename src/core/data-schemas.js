// /var/www/tts-mall/v2/shared/data-schemas.js
/**
 * Data Schemas - Estructuras de datos del sistema
 * @module DataSchemas
 */

export const MessageSchema = {
    id: null,          // Generated UUID
    name: '',          // Nombre del mensaje
    text: '',          // Texto del mensaje
    voice: 'cristian', // Voz seleccionada
    settings: {
        speed: 'normal',
        style: 0.5,
        stability: 0.75,
        similarity_boost: 0.8,
        emphasis: {
            enabled: true,
            level: 'medium',
            custom_words: []
        },
        pauses: {
            enabled: true,
            sentence: 0.5,
            comma: 0.2
        }
    },
    category: 'general',
    tags: [],
    audioUrl: null,
    createdAt: null,
    updatedAt: null
};

export const VoiceProfileSchema = {
    id: null,
    name: '',
    voice: 'cristian',
    settings: {
        style: 0.5,
        stability: 0.75,
        similarity_boost: 0.8,
        speed: 'normal'
    },
    isDefault: false,
    createdAt: null
};

export const CampaignSchema = {
    id: null,
    name: '',
    description: '',
    messages: [], // Array of message IDs
    schedule: {
        type: 'manual', // manual, scheduled, recurring
        startDate: null,
        endDate: null,
        times: []
    },
    active: false,
    createdAt: null,
    updatedAt: null
};

// Utility functions
export function createMessage(data = {}) {
    return {
        ...MessageSchema,
        ...data,
        id: data.id || generateId(),
        createdAt: data.createdAt || Date.now(),
        updatedAt: Date.now()
    };
}

export function createVoiceProfile(data = {}) {
    return {
        ...VoiceProfileSchema,
        ...data,
        id: data.id || generateId(),
        createdAt: data.createdAt || Date.now()
    };
}

export function generateId() {
    return 'id_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
}