export type StoredReaderMode = 'continuous' | 'single' | 'book';
export type StoredReaderTheme = 'light' | 'sepia' | 'dark';
export type StoredFitMode = 'custom' | 'width' | 'page';

export interface ReaderPreferencesV1 {
    version: 1;
    mode: StoredReaderMode;
    theme: StoredReaderTheme;
    scale: number;
    fitMode: StoredFitMode;
    rotation: number;
    updatedAt: string;
}

export interface ReaderProgressV1 {
    version: 1;
    slug: string;
    revision: string;
    page: number;
    pageOffsetRatio: number;
    documentProgress: number;
    totalPages: number;
    updatedAt: string;
}

const STORAGE_VERSION = 1 as const;
const PREFERENCES_KEY = 'digital-library.reader.preferences.v1';
const PROGRESS_PREFIX = 'digital-library.reader.progress.v1:';

function localStorageOrNull(): Storage | null {
    if (typeof window === 'undefined') return null;

    try {
        const storage = window.localStorage;
        const probe = '__digital_library_reader_probe__';

        storage.setItem(probe, '1');
        storage.removeItem(probe);

        return storage;
    } catch {
        return null;
    }
}

function readJson(key: string): unknown {
    const storage = localStorageOrNull();

    if (!storage) return null;

    try {
        const raw = storage.getItem(key);

        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

function writeJson(key: string, value: unknown): boolean {
    const storage = localStorageOrNull();

    if (!storage) return false;

    try {
        storage.setItem(key, JSON.stringify(value));

        return true;
    } catch {
        return false;
    }
}

function removeKey(key: string): boolean {
    const storage = localStorageOrNull();

    if (!storage) return false;

    try {
        storage.removeItem(key);

        return true;
    } catch {
        return false;
    }
}

function progressKey(slug: string): string {
    return `${PROGRESS_PREFIX}${encodeURIComponent(slug)}`;
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function validMode(value: unknown): value is StoredReaderMode {
    return value === 'continuous' || value === 'single' || value === 'book';
}

function validTheme(value: unknown): value is StoredReaderTheme {
    return value === 'light' || value === 'sepia' || value === 'dark';
}

function validFitMode(value: unknown): value is StoredFitMode {
    return value === 'custom' || value === 'width' || value === 'page';
}

function finiteNumber(value: unknown): value is number {
    return typeof value === 'number' && Number.isFinite(value);
}

function clamp(value: number, min: number, max: number) {
    return Math.min(max, Math.max(min, value));
}

function normalizeRotation(value: number) {
    const normalized = ((Math.round(value / 90) * 90) % 360 + 360) % 360;

    return [0, 90, 180, 270].includes(normalized) ? normalized : 0;
}

export function readerLocalStorageAvailable(): boolean {
    return localStorageOrNull() !== null;
}

export function readReaderPreferences(): ReaderPreferencesV1 | null {
    const value = readJson(PREFERENCES_KEY);

    if (
        !isRecord(value)
        || value.version !== STORAGE_VERSION
        || !validMode(value.mode)
        || !validTheme(value.theme)
        || !validFitMode(value.fitMode)
        || !finiteNumber(value.scale)
        || !finiteNumber(value.rotation)
        || typeof value.updatedAt !== 'string'
    ) {
        return null;
    }

    return {
        version: STORAGE_VERSION,
        mode: value.mode,
        theme: value.theme,
        scale: clamp(value.scale, 0.5, 2),
        fitMode: value.fitMode,
        rotation: normalizeRotation(value.rotation),
        updatedAt: value.updatedAt,
    };
}

export function writeReaderPreferences(
    preferences: Omit<ReaderPreferencesV1, 'version' | 'updatedAt'>,
): boolean {
    return writeJson(PREFERENCES_KEY, {
        version: STORAGE_VERSION,
        ...preferences,
        scale: clamp(preferences.scale, 0.5, 2),
        rotation: normalizeRotation(preferences.rotation),
        updatedAt: new Date().toISOString(),
    } satisfies ReaderPreferencesV1);
}

export function clearReaderPreferences(): boolean {
    return removeKey(PREFERENCES_KEY);
}

export function readBookProgress(
    slug: string,
    revision?: string,
): ReaderProgressV1 | null {
    const value = readJson(progressKey(slug));

    if (
        !isRecord(value)
        || value.version !== STORAGE_VERSION
        || value.slug !== slug
        || typeof value.revision !== 'string'
        || !finiteNumber(value.page)
        || !finiteNumber(value.pageOffsetRatio)
        || !finiteNumber(value.documentProgress)
        || !finiteNumber(value.totalPages)
        || typeof value.updatedAt !== 'string'
    ) {
        return null;
    }

    if (revision && value.revision !== revision) {
        return null;
    }

    return {
        version: STORAGE_VERSION,
        slug,
        revision: value.revision,
        page: Math.max(1, Math.round(value.page)),
        pageOffsetRatio: clamp(value.pageOffsetRatio, 0, 1),
        documentProgress: clamp(value.documentProgress, 0, 1),
        totalPages: Math.max(0, Math.round(value.totalPages)),
        updatedAt: value.updatedAt,
    };
}

export function writeBookProgress(
    progress: Omit<ReaderProgressV1, 'version' | 'updatedAt'>,
): boolean {
    return writeJson(progressKey(progress.slug), {
        version: STORAGE_VERSION,
        ...progress,
        page: Math.max(1, Math.round(progress.page)),
        pageOffsetRatio: clamp(progress.pageOffsetRatio, 0, 1),
        documentProgress: clamp(progress.documentProgress, 0, 1),
        totalPages: Math.max(0, Math.round(progress.totalPages)),
        updatedAt: new Date().toISOString(),
    } satisfies ReaderProgressV1);
}

export function clearBookProgress(slug: string): boolean {
    return removeKey(progressKey(slug));
}
