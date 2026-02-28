@extends('layouts.dashboard')

@section('title', 'Ask TOCSEA')

@section('content')
<div class="ask-tocsea-page" id="askTocseaPage"
    data-ask-url="{{ $askUrl }}"
    data-user-name="{{ auth()->user()?->name ?? 'You' }}"
    data-calculation-context="{{ $calculationContext ? json_encode($calculationContext) : '' }}">
    <header class="dashboard-header fade-in-element">
        <div class="header-card ask-header-inner">
            <h1 class="ask-header-title">
                <i data-lucide="message-circle" class="lucide-icon lucide-icon-md ask-header-icon" aria-hidden="true"></i>
                <span>Ask TOCSEA</span>
            </h1>
            <p class="ask-header-subtitle">
                AI-powered Environmental Decision Support — interpret soil loss, coastal hazards, and recommend interventions.
            </p>
        </div>
    </header>

    <section class="ask-section fade-in-element">
        <div class="ask-layout">
            {{-- Calculation context card (compact, from Soil Calculator) --}}
            <div id="askContextBanner" class="ask-context-card hidden" aria-live="polite">
                <div id="askContextCardContent"></div>
            </div>
            {{-- Main chat panel --}}
            <div class="ask-chat-panel">
                <div class="ask-messages" id="askMessages" role="log" aria-live="polite">
                    <div id="askWelcome" class="ask-welcome" aria-live="polite">
                        <p>Ask a question about soil erosion, coastal hazards, model outputs, or environmental risk.</p>
                    </div>
                </div>
                <div id="askLoading" class="ask-loading hidden" aria-live="polite" aria-busy="true">
                    <span class="ask-loading-avatar" aria-hidden="true">T</span>
                    <span class="ask-loading-dots"></span>
                    <span>TOCSEA is thinking…</span>
                </div>
                <div class="ask-input-wrap">
                    {{-- Suggested Questions (disappears on type or send) --}}
                    <div class="ask-suggested-questions" id="askSuggestedQuestions" role="group" aria-label="Suggested questions">
                        <p class="ask-suggested-hint" id="askSuggestedHint">Ask a question about soil erosion, coastal hazards, model outputs, or environmental risk.</p>
                        <div class="ask-suggested-chips" id="askSuggestedChips"></div>
                    </div>
                    <textarea id="askInput" class="ask-input" rows="3" placeholder="Type your question…" maxlength="8000" aria-label="Your question"></textarea>
                    <div class="ask-input-actions">
                        <button type="button" id="askClear" class="ask-btn ask-btn-ghost" aria-label="Clear chat">Clear chat</button>
                        <button type="button" id="askCopySummary" class="ask-btn ask-btn-ghost" aria-label="Copy report summary" title="Copy the last report-ready paragraph from the latest response">
                            <i data-lucide="copy" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                            Copy summary
                        </button>
                        <button type="button" id="askSend" class="ask-btn ask-btn-primary" aria-label="Send message">
                            <i data-lucide="send" class="lucide-icon lucide-icon-sm" aria-hidden="true"></i>
                            Send
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('styles')
    @vite(['resources/css/ask-tocsea.css'])
@endpush

@push('scripts')
    @vite(['resources/js/ask-tocsea.js'])
@endpush
