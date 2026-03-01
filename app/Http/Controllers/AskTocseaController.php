<?php

namespace App\Http\Controllers;

use App\Models\CalculationHistory;
use App\Services\TogetherAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AskTocseaController extends Controller
{
    public function __construct(
        protected TogetherAiService $aiService
    ) {}

    /**
     * Display the Ask TOCSEA chat page.
     * Calculation context is passed only when redirected from Soil Calculator via withContext().
     */
    public function index(): View
    {
        $calculationContext = session()->pull('ask_tocsea_calculation_context');

        return view('user.ask-tocsea.index', [
            'askUrl' => url('/api/ask-tocsea'),
            'calculationContext' => $calculationContext,
        ]);
    }

    /**
     * Store calculation context in session flash and redirect to Ask TOCSEA.
     * Context is one-time only: consumed on first page load, gone on refresh/navigation.
     */
    public function withContext(Request $request): RedirectResponse
    {
        $raw = $request->input('calculation_context');
        $context = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);

        if (! is_array($context) || empty($context['model_name'] ?? null)) {
            return redirect()->route('ask-tocsea');
        }

        $validated = validator($context, [
            'model_name' => ['required', 'string', 'max:500'],
            'equation' => ['nullable', 'string', 'max:4000'],
            'inputs' => ['nullable', 'array'],
            'result' => ['nullable', 'array'],
            'risk_level' => ['nullable', 'string', 'max:100'],
            'contributing_factors' => ['nullable', 'array'],
        ])->validate();

        session()->flash('ask_tocsea_calculation_context', $validated);

        return redirect()->route('ask-tocsea');
    }

    /**
     * Handle POST /api/ask-tocsea — send question to Together AI and return answer.
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:8000'],
            'context_mode' => ['required', 'string', 'in:none,latest,history,inline'],
            'calculation_id' => ['nullable', 'integer', 'exists:calculation_histories,id'],
            'calculation_context' => ['required_if:context_mode,inline', 'array'],
            'calculation_context.model_name' => ['required_if:context_mode,inline', 'string', 'max:500'],
            'calculation_context.equation' => ['nullable', 'string', 'max:4000'],
            'calculation_context.inputs' => ['nullable', 'array'],
            'calculation_context.result' => ['nullable', 'array'],
            'calculation_context.risk_level' => ['nullable', 'string', 'max:100'],
            'calculation_context.contributing_factors' => ['nullable', 'array'],
            'chat_history' => ['nullable', 'array'],
            'chat_history.*.role' => ['required_with:chat_history', 'string', 'in:user,assistant'],
            'chat_history.*.content' => ['required_with:chat_history', 'string', 'max:16000'],
        ]);

        if ($validated['context_mode'] === 'history' && empty($validated['calculation_id'])) {
            return response()->json([
                'error' => 'calculation_id is required when context_mode is "history"',
            ], 422);
        }

        if ($validated['context_mode'] === 'inline' && empty($validated['calculation_context'])) {
            return response()->json([
                'error' => 'calculation_context is required when context_mode is "inline"',
            ], 422);
        }

        $user = $request->user();
        $context = $this->resolveContext(
            $user->id,
            $validated['context_mode'],
            (int) ($validated['calculation_id'] ?? 0),
            $validated['calculation_context'] ?? null
        );

        $chatHistory = $validated['chat_history'] ?? [];
        $chatHistory = array_slice($chatHistory, -12);

        $messages = $this->aiService->buildMessages(
            trim($validated['question']),
            $context,
            $chatHistory
        );

        try {
            $answer = $this->aiService->chat($messages);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'answer' => $answer,
        ]);
    }

    /**
     * Resolve context payload from calculation history or inline context.
     *
     * @param  array<string, mixed>|null  $inlineContext
     * @return array<string, mixed>|null
     */
    protected function resolveContext(int $userId, string $mode, int $calculationId, ?array $inlineContext = null): ?array
    {
        if ($mode === 'inline' && $inlineContext !== null) {
            return [
                'model_name' => $inlineContext['model_name'] ?? null,
                'equation' => $inlineContext['equation'] ?? null,
                'inputs' => $inlineContext['inputs'] ?? [],
                'result' => $inlineContext['result'] ?? null,
                'risk_level' => $inlineContext['risk_level'] ?? null,
                'contributing_factors' => $inlineContext['contributing_factors'] ?? null,
            ];
        }

        if ($mode === 'none') {
            return null;
        }

        $query = CalculationHistory::where('user_id', $userId);

        if ($mode === 'history' && $calculationId > 0) {
            $query->where('id', $calculationId);
        } elseif ($mode === 'latest') {
            $query->orderBy('created_at', 'desc')->limit(1);
        }

        $record = $query->first();
        if (! $record) {
            return null;
        }

        return [
            'model_name' => $record->equation_name ?? null,
            'equation' => $record->formula_snapshot ?? null,
            'inputs' => is_array($record->inputs) ? $record->inputs : [],
            'result' => [
                'predicted_soil_loss' => (string) ((float) $record->result),
                'units' => 'm²/year',
            ],
            'notes' => $record->notes ?? null,
        ];
    }
}
