/**
 * TOCSEA - Ask TOCSEA AI Chat
 * Uses Together AI for environmental decision support.
 */

import { createIcons, Copy, Layers, MessageCircle, Send } from 'lucide';

/** General suggested questions (when no calculation context) - XSS-safe via textContent */
const SUGGESTED_QUESTIONS = [
    'What causes soil erosion?',
    'How do coastal hazards affect land stability?',
    'What are effective ways to reduce coastal erosion?',
    'What vegetation helps prevent soil loss?',
];

/** Map input keys to friendly labels */
const INPUT_LABELS = {
    seawall: 'Seawall (m)',
    Seawall_m: 'Seawall (m)',
    precipitation: 'Precip (mm)',
    Precipitation_mm: 'Precip (mm)',
    tropical_storm: 'Tropical Storms',
    Tropical_Storm: 'Tropical Storms',
    Typhoons: 'Super Typhoons',
    Super_Typhoons: 'Super Typhoons',
    floods: 'Floods',
    Floods: 'Floods',
    Storm_Surges: 'Storm Surges',
    soil_type: 'Soil Type',
    Soil_Type: 'Soil Type',
};

const page = document.getElementById('askTocseaPage');
if (!page) throw new Error('Ask TOCSEA page not found');

const askUrl = page.dataset.askUrl || '/api/ask-tocsea';
const userName = page.dataset.userName || 'You';
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
const messagesEl = document.getElementById('askMessages');
const welcomeEl = document.getElementById('askWelcome');
const loadingEl = document.getElementById('askLoading');
const inputEl = document.getElementById('askInput');
const sendBtn = document.getElementById('askSend');
const clearBtn = document.getElementById('askClear');
const copySummaryBtn = document.getElementById('askCopySummary');
const suggestedQuestionsEl = document.getElementById('askSuggestedQuestions');
const suggestedChipsEl = document.getElementById('askSuggestedChips');
const suggestedHintEl = document.getElementById('askSuggestedHint');
const contextBannerEl = document.getElementById('askContextBanner');
const contextCardContentEl = document.getElementById('askContextCardContent');

/** @type {Array<{role: 'user'|'assistant', content: string}>} */
let chatHistory = [];

/** Calculation context from Soil Calculator (model, inputs, result, risk, factors) */
let calculationContext = null;

const MAX_CHAT_HISTORY = 12;

/**
 * Sanitize text for safe display (prevent XSS).
 * @param {string} str
 * @returns {string}
 */
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

/**
 * Get initials from a name (first letter, or "You" → "Y").
 * @param {string} name
 * @returns {string}
 */
function getInitials(name) {
    if (!name || typeof name !== 'string') return '?';
    const trimmed = name.trim();
    if (!trimmed) return '?';
    return trimmed.charAt(0).toUpperCase();
}

/**
 * Convert AI response text to styled HTML (sections, bullets, bold).
 * Strips "Response as TOCSEA", converts markdown, XSS-safe.
 * Section titles (Quick Interpretation, Key Contributing Factors, etc.) become bold headers.
 * @param {string} text
 * @returns {string}
 */
function textToHtml(text) {
    if (!text || typeof text !== 'string') return '';
    let s = text.trim();

    // Remove "Response as TOCSEA" and similar headers (case-insensitive)
    s = s.replace(/^\s*(?:\*\*)?\s*Response\s+as\s+TOCSEA\s*(?:\*\*)?\s*:?\s*\n?/i, '');

    s = escapeHtml(s);

    // Convert **bold** to <strong>
    s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');

    // Process block by block for valid HTML structure
    const blocks = s.split(/\n\n+/);
    const parts = [];

    for (const block of blocks) {
        const trimmed = block.trim();
        if (!trimmed) continue;

        const lines = trimmed.split(/\n/);

        // Single line matching "1. Title" or "2. Title"
        if (lines.length === 1 && /^\d+\.\s+.+/.test(trimmed)) {
            const title = trimmed.replace(/^\d+\.\s+/, '');
            parts.push(`<div class="ask-response-section-title">${title}</div>`);
            continue;
        }

        // Single line that looks like a section title (ends with colon, or common report headers)
        const isSectionTitle = lines.length === 1 && (
            /:\s*$/.test(trimmed) ||
            /^(Quick Interpretation|Key Contributing Factors|Recommended Actions|Summary|Conclusion|Risk Assessment|Notes|Additional Context|Interpretation|Factors|Recommendations|Key Findings|Next Steps)\s*$/i.test(trimmed)
        );
        if (isSectionTitle) {
            const title = trimmed.replace(/:?\s*$/, '').trim();
            if (title) parts.push(`<div class="ask-response-section-title">${title}</div>`);
            continue;
        }

        // All lines are bullets (- * •)
        const bulletMatch = lines.every((l) => /^\s*[-*•]\s+/.test(l));
        if (bulletMatch && lines.length > 0) {
            const items = lines
                .map((l) => l.replace(/^\s*[-*•]\s+/, ''))
                .map((c) => `<li>${c}</li>`)
                .join('');
            parts.push(`<ul class="ask-response-list">${items}</ul>`);
            continue;
        }

        // Regular paragraph
        parts.push(`<p class="ask-response-para">${trimmed.replace(/\n/g, '<br>')}</p>`);
    }

    return '<div class="ask-response-body">' + parts.join('') + '</div>';
}

function showLoading() {
    loadingEl?.classList.remove('hidden');
}

function hideLoading() {
    loadingEl?.classList.add('hidden');
}

function appendMessage(role, content) {
    const wrap = document.createElement('div');
    wrap.className = `ask-message ask-message-${role}`;

    const inner = document.createElement('div');
    inner.className = 'ask-message-inner';

    const label = document.createElement('div');
    label.className = 'ask-message-label';

    const avatar = document.createElement('span');
    avatar.className = 'ask-message-avatar';
    avatar.setAttribute('aria-hidden', 'true');

    const nameSpan = document.createElement('span');
    nameSpan.className = 'ask-message-name';

    if (role === 'user') {
        avatar.textContent = getInitials(userName);
        avatar.classList.add('ask-avatar-user');
        nameSpan.textContent = userName;
    } else {
        avatar.textContent = 'T';
        avatar.classList.add('ask-avatar-tocsea');
        nameSpan.textContent = 'TOCSEA';
        nameSpan.classList.add('ask-name-tocsea');
    }

    label.appendChild(avatar);
    label.appendChild(nameSpan);

    const bubble = document.createElement('div');
    bubble.className = 'ask-message-bubble';
    bubble.innerHTML = role === 'assistant' ? textToHtml(content) : escapeHtml(content).replace(/\n/g, '<br>');

    inner.appendChild(label);
    inner.appendChild(bubble);
    wrap.appendChild(inner);
    messagesEl?.appendChild(wrap);
    messagesEl?.scrollTo({ top: messagesEl.scrollHeight, behavior: 'smooth' });
}

function showWelcome(show) {
    if (welcomeEl) {
        welcomeEl.hidden = !show;
        welcomeEl.classList.toggle('hidden', !show);
    }
}

async function sendQuestion() {
    const question = inputEl?.value?.trim();
    if (!question) return;

    sendBtn.disabled = true;
    showLoading();
    showWelcome(false);
    appendMessage('user', question);
    chatHistory.push({ role: 'user', content: question });
    inputEl.value = '';

    const payload = {
        question,
        context_mode: calculationContext ? 'inline' : 'none',
        chat_history: chatHistory.slice(0, -1).slice(-MAX_CHAT_HISTORY).map((m) => ({ role: m.role, content: m.content })),
    };
    if (calculationContext) {
        payload.calculation_context = calculationContext;
    }

    try {
        const res = await fetch(askUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken || '',
            },
            body: JSON.stringify(payload),
        });

        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            const errMsg = data.error || res.statusText || 'Request failed.';
            throw new Error(errMsg);
        }

        const answer = data.answer;
        if (answer) {
            appendMessage('assistant', answer);
            chatHistory.push({ role: 'assistant', content: answer });
        } else {
            throw new Error('Empty response from AI.');
        }
    } catch (err) {
        appendMessage(
            'assistant',
            'Sorry, I could not process your question at this time. ' +
                (err.message || 'Please try again.')
        );
        chatHistory.push({ role: 'assistant', content: 'Error: ' + err.message });
    } finally {
        hideLoading();
        sendBtn.disabled = false;
    }

    showWelcome(false);
    hideSuggestedQuestions();
}

function clearChat() {
    chatHistory = [];
    showWelcome(true);
    const bubbles = messagesEl?.querySelectorAll('.ask-message') || [];
    bubbles.forEach((el) => el.remove());
    showSuggestedQuestions();
}

function copyReportSummary() {
    const assistantMessages = chatHistory.filter((m) => m.role === 'assistant');
    const last = assistantMessages[assistantMessages.length - 1];
    if (!last) {
        alert('No response to copy yet.');
        return;
    }

    // Use the last paragraph as the "Report-Ready Summary"
    const paras = last.content.split(/\n\n+/).filter(Boolean);
    const summary = paras[paras.length - 1] || last.content;

    navigator.clipboard.writeText(summary).then(
        () => {
            copySummaryBtn.textContent = 'Copied!';
            setTimeout(() => {
                copySummaryBtn.innerHTML = '<i data-lucide="copy" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i> Copy summary';
                createIcons({ icons: { Copy } });
            }, 1500);
        },
        () => alert('Failed to copy.')
    );
}

/* --------------------------------------------------------------------------
   Suggested Questions (4 chips above textarea, disappear on type/send)
   -------------------------------------------------------------------------- */
let suggestedJustFilled = false;

function hideSuggestedQuestions() {
    suggestedQuestionsEl?.classList.add('is-hidden');
}

function showSuggestedQuestions() {
    suggestedQuestionsEl?.classList.remove('is-hidden');
}

function renderSuggestedChips() {
    if (!suggestedChipsEl) return;
    suggestedChipsEl.innerHTML = '';
    SUGGESTED_QUESTIONS.forEach((text) => {
        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'ask-suggested-chip';
        chip.textContent = text;
        chip.dataset.question = text;
        chip.addEventListener('click', handleSuggestedChipClick);
        suggestedChipsEl.appendChild(chip);
    });
}

function handleSuggestedChipClick(e) {
    const chip = e.currentTarget;
    const question = chip.dataset.question;
    if (!question || !inputEl) return;

    suggestedJustFilled = true;
    inputEl.value = question;
    inputEl.focus();

    chip.classList.add('is-active');
    setTimeout(() => chip.classList.remove('is-active'), 400);
}

function onInputChange() {
    if (suggestedJustFilled) {
        suggestedJustFilled = false;
        return;
    }
    hideSuggestedQuestions();
}

renderSuggestedChips();
inputEl?.addEventListener('input', onInputChange);

sendBtn?.addEventListener('click', () => sendQuestion());

inputEl?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendQuestion();
    }
});

clearBtn?.addEventListener('click', clearChat);
copySummaryBtn?.addEventListener('click', copyReportSummary);

/* --------------------------------------------------------------------------
   Calculation context from Soil Calculator (one-time, server flash only)
   -------------------------------------------------------------------------- */
function loadCalculationContext() {
    try {
        const raw = page?.dataset?.calculationContext?.trim();
        if (raw) {
            const parsed = JSON.parse(raw);
            if (parsed && typeof parsed === 'object' && parsed.model_name) {
                return parsed;
            }
        }
    } catch (_) {}
    return null;
}

function friendlyLabel(key) {
    return INPUT_LABELS[key] || key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function showContextBanner(ctx) {
    if (!contextBannerEl || !contextCardContentEl) return;
    calculationContext = ctx;
    contextBannerEl.classList.remove('hidden');
    contextBannerEl.hidden = false;

    const inputs = ctx.inputs || {};
    const result = ctx.result?.predicted_soil_loss ?? ctx.result ?? '—';
    const risk = ctx.risk_level || '—';
    const riskSlug = risk.toLowerCase().replace(/\s+/g, '');
    const riskClass = riskSlug.includes('high') ? 'high' : riskSlug.includes('moderate') ? 'moderate' : 'low';

    const inputsGrid = Object.entries(inputs)
        .map(([k, v]) => `<span class="ask-context-kv"><span class="ask-context-kv-label">${escapeHtml(friendlyLabel(k))}</span> ${escapeHtml(String(v))}</span>`)
        .join('');

    const factors = ctx.contributing_factors;
    const factorsChips = factors && (factors.storm || factors.protection || factors.soil)
        ? [
            factors.storm ? `Storm & Flood: ${escapeHtml(factors.storm)}` : '',
            factors.protection ? `Coastal Protection: ${escapeHtml(factors.protection)}` : '',
            factors.soil ? `Soil Type: ${escapeHtml(factors.soil)}` : '',
        ].filter(Boolean).map((t) => `<span class="ask-context-chip">${t}</span>`).join('')
        : '';

    const hasEquation = ctx.equation && ctx.equation.trim().length > 0;
    const viewModelLink = hasEquation
        ? ` <a href="#" class="ask-context-view-model" id="askViewModelBtn" aria-label="View model equation">View Model</a>`
        : '';

    const units = ctx.result?.units || 'm²/year';

    contextCardContentEl.innerHTML = `
        <div class="ask-context-row ask-context-header">
            <span class="ask-context-title">Using Calculation Context</span>
            <span class="ask-context-badge">Active</span>
            <button type="button" id="askClearContext" class="ask-context-clear" aria-label="Clear calculation context">Clear</button>
        </div>
        <div class="ask-context-row ask-context-summary">
            <div class="ask-context-model">
                ${escapeHtml(ctx.model_name || '—')}${viewModelLink}
            </div>
            <div class="ask-context-result">
                <strong>${escapeHtml(String(result))} ${escapeHtml(units)}</strong>
                <span class="ask-context-risk risk-${riskClass}">${escapeHtml(risk)}</span>
            </div>
        </div>
        <div class="ask-context-inputs">
            ${inputsGrid}
        </div>
        ${factorsChips ? `<div class="ask-context-chips">${factorsChips}</div>` : ''}
    `;

    const viewModelBtnEl = document.getElementById('askViewModelBtn');
    if (viewModelBtnEl && ctx.equation) {
        viewModelBtnEl.addEventListener('click', (e) => {
            e.preventDefault();
            alert(`Model equation:\n${ctx.equation}`);
        });
    }

    document.getElementById('askClearContext')?.addEventListener('click', clearCalculationContext);

    updateSuggestedForContext(ctx);
    updateHintForContext(true);
    createIcons({ icons: { Layers }, nameAttr: 'data-lucide' });
}

function getContextSuggestedQuestions(ctx) {
    const risk = ctx.risk_level || 'this risk level';
    const soilType = ctx.inputs?.soil_type || ctx.inputs?.Soil_Type || 'this soil type';
    const soilLabel = String(soilType).charAt(0).toUpperCase() + String(soilType).slice(1);
    return [
        `Why is this result classified as ${risk}?`,
        'Which inputs contributed most to this soil loss (based on this context)?',
        'What interventions can reduce soil loss for this scenario?',
        `What vegetation or nature-based solutions fit ${soilLabel} soil and these hazards?`,
    ];
}

function updateSuggestedForContext(ctx) {
    if (!suggestedChipsEl) return;
    suggestedChipsEl.innerHTML = '';
    const questions = getContextSuggestedQuestions(ctx);
    questions.forEach((text) => {
        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'ask-suggested-chip';
        chip.textContent = text;
        chip.dataset.question = text;
        chip.addEventListener('click', handleSuggestedChipClick);
        suggestedChipsEl.appendChild(chip);
    });
}

function updateHintForContext(hasContext) {
    if (!suggestedHintEl) return;
    suggestedHintEl.textContent = hasContext
        ? 'Ask a question about THIS calculation result, risk level, or how to reduce soil loss.'
        : 'Ask a question about soil erosion, coastal hazards, model outputs, or environmental risk.';
}

function hideContextBanner() {
    if (!contextBannerEl) return;
    calculationContext = null;
    contextBannerEl.classList.add('hidden');
    contextBannerEl.hidden = true;
}

function clearCalculationContext() {
    hideContextBanner();
    updateHintForContext(false);
    renderSuggestedChips();
    if (inputEl) inputEl.value = '';
    showSuggestedQuestions();
    if (chatHistory.length === 0) showWelcome(true);
}

// On page load: check for calculation context
const initialContext = loadCalculationContext();
if (initialContext) {
    showContextBanner(initialContext);
    showWelcome(false); // Hide generic welcome when context is active
}

createIcons({ icons: { MessageCircle, Send, Copy, Layers }, nameAttr: 'data-lucide' });
