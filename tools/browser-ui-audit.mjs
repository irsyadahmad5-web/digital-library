#!/usr/bin/env node

import { spawn } from 'node:child_process';
import { mkdir, writeFile } from 'node:fs/promises';
import { join } from 'node:path';

const baseUrl = process.env.QA_BASE_URL;
const chromeBin = process.env.CHROME_BIN;
const outputDir = process.env.QA_UI_AUDIT_DIR;

if (!baseUrl || !chromeBin || !outputDir) {
    console.error('QA_BASE_URL, CHROME_BIN and QA_UI_AUDIT_DIR are required.');
    process.exit(2);
}

await mkdir(outputDir, { recursive: true });

const debugPort = 9300 + (process.pid % 500);
const chromeProfile = join(outputDir, 'chrome-profile');

const chrome = spawn(chromeBin, [
    '--headless=new',
    '--no-sandbox',
    '--disable-gpu',
    '--hide-scrollbars',
    '--remote-debugging-address=127.0.0.1',
    '--remote-debugging-port=' + debugPort,
    '--remote-allow-origins=*',
    '--user-data-dir=' + chromeProfile,
    'about:blank',
], {
    stdio: ['ignore', 'ignore', 'pipe'],
});

let chromeStderr = '';
chrome.stderr.setEncoding('utf8');
chrome.stderr.on('data', (chunk) => {
    chromeStderr += chunk;
});

function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

async function waitForDevtools() {
    for (let attempt = 0; attempt < 80; attempt += 1) {
        try {
            const response = await fetch('http://127.0.0.1:' + debugPort + '/json/version');
            if (response.ok) return;
        } catch {
            // Chrome is still starting.
        }

        await sleep(100);
    }

    throw new Error('Chrome DevTools endpoint did not become ready.\n' + chromeStderr);
}

class CdpSession {
    constructor(url) {
        this.url = url;
        this.nextId = 1;
        this.pending = new Map();
        this.ws = null;
    }

    async open() {
        this.ws = new WebSocket(this.url);

        await new Promise((resolve, reject) => {
            const timeout = setTimeout(() => reject(new Error('CDP websocket open timeout')), 5000);

            this.ws.addEventListener('open', () => {
                clearTimeout(timeout);
                resolve();
            }, { once: true });

            this.ws.addEventListener('error', () => {
                clearTimeout(timeout);
                reject(new Error('CDP websocket failed to open'));
            }, { once: true });
        });

        this.ws.addEventListener('message', (event) => {
            const message = JSON.parse(String(event.data));

            if (!message.id || !this.pending.has(message.id)) return;

            const pending = this.pending.get(message.id);
            this.pending.delete(message.id);

            if (message.error) {
                pending.reject(new Error(message.error.message || 'CDP command failed'));
                return;
            }

            pending.resolve(message.result ?? {});
        });
    }

    call(method, params = {}) {
        if (!this.ws) throw new Error('CDP session is not open.');

        const id = this.nextId;
        this.nextId += 1;

        return new Promise((resolve, reject) => {
            const timeout = setTimeout(() => {
                this.pending.delete(id);
                reject(new Error('CDP timeout: ' + method));
            }, 10000);

            this.pending.set(id, {
                resolve: (value) => {
                    clearTimeout(timeout);
                    resolve(value);
                },
                reject: (error) => {
                    clearTimeout(timeout);
                    reject(error);
                },
            });

            this.ws.send(JSON.stringify({ id, method, params }));
        });
    }

    close() {
        this.ws?.close();
    }
}

async function openPage() {
    const response = await fetch(
        'http://127.0.0.1:' + debugPort + '/json/new?' + encodeURIComponent('about:blank'),
        { method: 'PUT' },
    );

    if (!response.ok) throw new Error('Unable to create Chrome target.');

    const target = await response.json();
    const session = new CdpSession(target.webSocketDebuggerUrl);

    await session.open();
    await session.call('Page.enable');
    await session.call('Runtime.enable');
    await session.call('Network.enable');

    return session;
}

async function evaluate(session, expression) {
    const result = await session.call('Runtime.evaluate', {
        expression,
        returnByValue: true,
        awaitPromise: true,
    });

    if (result.exceptionDetails) {
        throw new Error('Runtime evaluation failed: ' + JSON.stringify(result.exceptionDetails));
    }

    return result.result?.value;
}

async function setViewport(session, width, height) {
    const mobile = width <= 430;

    await session.call('Emulation.setDeviceMetricsOverride', {
        width,
        height,
        deviceScaleFactor: 1,
        mobile,
        screenWidth: width,
        screenHeight: height,
    });

    await session.call('Emulation.setTouchEmulationEnabled', {
        enabled: mobile,
        maxTouchPoints: mobile ? 5 : 1,
    });
}

async function navigate(session, path) {
    await session.call('Page.navigate', { url: baseUrl + path });

    for (let attempt = 0; attempt < 60; attempt += 1) {
        const ready = await evaluate(
            session,
            "document.readyState === 'complete' && Boolean(document.querySelector('#app'))",
        );

        if (ready) {
            await sleep(350);
            return;
        }

        await sleep(100);
    }

    throw new Error('Page did not become ready: ' + path);
}

function auditPage() {
    const root = document.documentElement;
    const body = document.body;

    const visible = (element) => {
        const style = getComputedStyle(element);
        const rect = element.getBoundingClientRect();

        return style.display !== 'none'
            && style.visibility !== 'hidden'
            && Number(style.opacity) !== 0
            && rect.width > 0
            && rect.height > 0;
    };

    const labelFor = (element) => {
        const ariaLabel = element.getAttribute('aria-label');
        if (ariaLabel && ariaLabel.trim()) return ariaLabel.trim();

        const labelledBy = element.getAttribute('aria-labelledby');
        if (labelledBy) {
            const text = labelledBy
                .split(/\s+/)
                .map((id) => document.getElementById(id)?.textContent?.trim() || '')
                .filter(Boolean)
                .join(' ');

            if (text) return text;
        }

        if (element.id) {
            const explicit = document.querySelector('label[for="' + CSS.escape(element.id) + '"]');
            if (explicit?.textContent?.trim()) return explicit.textContent.trim();
        }

        const wrapping = element.closest('label');
        if (wrapping?.textContent?.trim()) return wrapping.textContent.trim();

        const text = element.textContent?.trim();
        if (text) return text;

        const title = element.getAttribute('title');
        if (title && title.trim()) return title.trim();

        const alt = element.querySelector('img[alt]')?.getAttribute('alt');
        if (alt && alt.trim()) return alt.trim();

        return '';
    };

    const visibleHeadings = [...document.querySelectorAll('h1')].filter(visible);
    const allInteractive = [...document.querySelectorAll(
        'button, a[href], input:not([type="hidden"]), select, textarea, [role="button"], [role="switch"], [role="checkbox"], [tabindex]'
    )].filter((element) => visible(element) && element.getAttribute('tabindex') !== '-1');

    const unlabeled = allInteractive
        .filter((element) => !labelFor(element))
        .map((element) => {
            const rect = element.getBoundingClientRect();
            return {
                tag: element.tagName.toLowerCase(),
                type: element.getAttribute('type') || '',
                className: String(element.className || '').slice(0, 120),
                width: Math.round(rect.width),
                height: Math.round(rect.height),
            };
        })
        .slice(0, 12);

    const coarseTargetCandidates = [...document.querySelectorAll(
        'button:not([role="checkbox"]):not([role="switch"]), [role="button"], a[aria-label]'
    )].filter(visible);

    const undersizedTargets = coarseTargetCandidates
        .filter((element) => {
            const rect = element.getBoundingClientRect();
            return rect.width < 43 || rect.height < 43;
        })
        .map((element) => {
            const rect = element.getBoundingClientRect();
            return {
                tag: element.tagName.toLowerCase(),
                label: labelFor(element).slice(0, 80),
                width: Math.round(rect.width),
                height: Math.round(rect.height),
            };
        })
        .slice(0, 12);

    const overflow = Math.max(root.scrollWidth, body?.scrollWidth || 0) - root.clientWidth;

    return {
        path: location.pathname + location.search,
        title: document.title,
        h1Count: visibleHeadings.length,
        h1Text: visibleHeadings.map((element) => element.textContent?.trim().slice(0, 120) || ''),
        clientWidth: root.clientWidth,
        scrollWidth: Math.max(root.scrollWidth, body?.scrollWidth || 0),
        overflow,
        unlabeled,
        undersizedTargets,
    };
}

function stressPage() {
    const candidates = [...document.querySelectorAll(
        '[class*="truncate"], [class*="line-clamp"], h1, h2'
    )].filter((element) => {
        const style = getComputedStyle(element);
        const rect = element.getBoundingClientRect();

        return style.display !== 'none'
            && style.visibility !== 'hidden'
            && rect.width > 0
            && rect.height > 0;
    }).slice(0, 10);

    const originals = candidates.map((element) => element.textContent);
    const stress = 'Konten sangat panjang untuk menguji responsivitas tata letak perpustakaan digital tanpa menyebabkan overflow horizontal pada berbagai ukuran layar dan perangkat.';

    candidates.forEach((element) => {
        element.textContent = stress;
    });

    const root = document.documentElement;
    const body = document.body;
    const overflow = Math.max(root.scrollWidth, body?.scrollWidth || 0) - root.clientWidth;

    candidates.forEach((element, index) => {
        element.textContent = originals[index];
    });

    return {
        testedNodes: candidates.length,
        overflow,
    };
}

async function keyboardFocusCheck(session) {
    await session.call('Input.dispatchKeyEvent', {
        type: 'keyDown',
        key: 'Tab',
        code: 'Tab',
        windowsVirtualKeyCode: 9,
        nativeVirtualKeyCode: 9,
    });
    await session.call('Input.dispatchKeyEvent', {
        type: 'keyUp',
        key: 'Tab',
        code: 'Tab',
        windowsVirtualKeyCode: 9,
        nativeVirtualKeyCode: 9,
    });

    await sleep(80);

    const focusExpression = '(' + function () {
        const element = document.activeElement;

        if (!element || element === document.body || element === document.documentElement) {
            return { ok: false, reason: 'No interactive element received keyboard focus.' };
        }

        const style = getComputedStyle(element);
        const ringVisible = style.outlineStyle !== 'none'
            || style.boxShadow !== 'none'
            || Number.parseFloat(style.outlineWidth || '0') > 0;

        return {
            ok: ringVisible,
            tag: element.tagName.toLowerCase(),
            label: element.getAttribute('aria-label') || element.textContent?.trim().slice(0, 80) || '',
            outline: style.outlineStyle + ' ' + style.outlineWidth,
            boxShadow: style.boxShadow,
        };
    }.toString() + ')()';

    return evaluate(session, focusExpression);
}

async function reducedMotionCheck(session) {
    await session.call('Emulation.setEmulatedMedia', {
        features: [
            { name: 'prefers-reduced-motion', value: 'reduce' },
        ],
    });

    const expression = '(' + function () {
        const node = document.createElement('div');
        node.className = 'reader-page-turn';
        node.style.transition = 'transform 5s ease';
        document.body.appendChild(node);

        const style = getComputedStyle(node);
        const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
        const animation = style.animationDuration;
        const transition = style.transitionDuration;

        node.remove();

        return { reduced, animation, transition };
    }.toString() + ')()';

    const result = await evaluate(session, expression);
    await session.call('Emulation.setEmulatedMedia', { features: [] });

    return result;
}

await waitForDevtools();

const session = await openPage();
const failures = [];
const results = [];
const auditExpression = '(' + auditPage.toString() + ')()';
const stressExpression = '(' + stressPage.toString() + ')()';

const referenceViewports = [
    [360, 800],
    [390, 844],
    [430, 932],
    [768, 1024],
    [1024, 768],
    [1280, 900],
    [1440, 1000],
    [1600, 1000],
];

const coreRoutes = ['/', '/library', '/admin/login'];

const representativeRoutes = [
    '/categories',
    '/authors',
    '/publishers',
    '/collections',
    '/about',
    '/contact',
    '/book/qa-reader-book',
    '/book/qa-long-content-book',
];

try {
    for (const [width, height] of referenceViewports) {
        await setViewport(session, width, height);

        for (const path of coreRoutes) {
            await navigate(session, path);
            const audit = await evaluate(session, auditExpression);

            results.push({ width, height, path, audit });

            if (audit.overflow > 2) {
                failures.push(width + 'x' + height + ' ' + path + ': horizontal overflow ' + audit.overflow + 'px');
            }

            if (audit.h1Count !== 1) {
                failures.push(width + 'x' + height + ' ' + path + ': expected one visible h1, found ' + audit.h1Count);
            }

            if (audit.unlabeled.length) {
                failures.push(width + 'x' + height + ' ' + path + ': unlabeled interactive controls ' + JSON.stringify(audit.unlabeled));
            }

            if (width <= 430 && audit.undersizedTargets.length) {
                failures.push(width + 'x' + height + ' ' + path + ': undersized touch targets ' + JSON.stringify(audit.undersizedTargets));
            }
        }
    }

    for (const [width, height] of [[390, 844], [1440, 1000]]) {
        await setViewport(session, width, height);

        for (const path of representativeRoutes) {
            await navigate(session, path);
            const audit = await evaluate(session, auditExpression);
            const stress = await evaluate(session, stressExpression);

            results.push({ width, height, path, audit, stress });

            if (audit.overflow > 2 || stress.overflow > 2) {
                failures.push(width + 'x' + height + ' ' + path + ': content overflow detected');
            }

            if (audit.h1Count !== 1) {
                failures.push(width + 'x' + height + ' ' + path + ': expected one visible h1, found ' + audit.h1Count);
            }

            if (audit.unlabeled.length) {
                failures.push(width + 'x' + height + ' ' + path + ': unlabeled interactive controls ' + JSON.stringify(audit.unlabeled));
            }

            if (width <= 430 && audit.undersizedTargets.length) {
                failures.push(width + 'x' + height + ' ' + path + ': undersized touch targets ' + JSON.stringify(audit.undersizedTargets));
            }
        }
    }

    for (const [width, height] of [[390, 844], [844, 390], [1440, 900]]) {
        await setViewport(session, width, height);
        await navigate(session, '/read/qa-reader-book');
        const audit = await evaluate(session, auditExpression);

        results.push({ width, height, path: '/read/qa-reader-book', audit });

        if (audit.overflow > 2) {
            failures.push(width + 'x' + height + ' reader: horizontal overflow ' + audit.overflow + 'px');
        }

        if (audit.unlabeled.length) {
            failures.push(width + 'x' + height + ' reader: unlabeled interactive controls ' + JSON.stringify(audit.unlabeled));
        }

        if (width <= 430 && audit.undersizedTargets.length) {
            failures.push(width + 'x' + height + ' reader: undersized touch targets ' + JSON.stringify(audit.undersizedTargets));
        }
    }

    for (const path of ['/', '/library', '/admin/login']) {
        await setViewport(session, 1440, 1000);
        await navigate(session, path);

        const focus = await keyboardFocusCheck(session);
        results.push({ path, keyboardFocus: focus });

        if (!focus.ok) {
            failures.push(path + ': keyboard focus is not visibly indicated ' + JSON.stringify(focus));
        }
    }

    await setViewport(session, 390, 844);
    await navigate(session, '/');
    const reducedMotion = await reducedMotionCheck(session);
    results.push({ path: '/', reducedMotion });

    if (!reducedMotion.reduced) {
        failures.push('Reduced-motion media emulation was not honored.');
    }

    if (!String(reducedMotion.animation).includes('0.00001') && !String(reducedMotion.animation).includes('0s')) {
        failures.push('Reduced-motion animation duration is not effectively disabled: ' + reducedMotion.animation);
    }

    await writeFile(
        join(outputDir, 'ui-audit.json'),
        JSON.stringify({ failures, results }, null, 2),
        'utf8',
    );

    if (failures.length) {
        console.error('Responsive/accessibility browser audit failed:');
        for (const failure of failures) console.error(' - ' + failure);
        process.exitCode = 1;
    } else {
        console.log(
            'Responsive/accessibility browser audit: PASS '
            + '(8 reference widths, long-content stress, reader portrait/landscape, keyboard focus, reduced motion)',
        );
    }
} finally {
    session.close();
    chrome.kill('SIGTERM');
}
