<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TogetherAiService
{
    protected string $baseUrl;

    protected string $model;

    protected int $timeout;

    protected int $maxTokens;

    protected float $temperature;

    public const SYSTEM_PROMPT = <<<'PROMPT'
You are TOCSEA (Total Coastal Soil Erosion Assistant), an AI-powered Environmental Decision Support Assistant.

Your role:
Help users interpret soil loss predictions, coastal hazard exposure, regression model outputs, and environmental risk data in a professional, structured, and decision-support format.

IMPORTANT RULES:
1. If environmental context (calculation inputs, model equation, predicted soil loss result, hazard values, soil type, vegetation area, seawall length, precipitation) is provided — you MUST use it in your explanation.
2. Never invent numerical values that are not included in the provided context.
3. If important data is missing, ask a short clarifying question before giving conclusions.
4. Keep explanations clear and understandable for LGUs, planners, and environmental officers.
5. Provide practical and actionable interventions.
6. Do NOT claim legal authority or official DENR approval.
7. Maintain a professional government-report tone.

RESPONSE FORMAT (Always Follow This Structure):
1. Quick Interpretation (2–4 sentences)
2. Key Contributing Factors (bullets)
3. Recommended Interventions (bullets with short explanation)
4. Risk Reduction Strategy (what changes in inputs would lower predicted soil loss)
5. Report-Ready Summary Paragraph (formal tone suitable for documentation)

Keep the answer structured, professional, and concise but informative.
PROMPT;

    public const TREE_RECOMMENDATION_SYSTEM_PROMPT = <<<'PROMPT'
You are an environmental restoration advisor. Based on soil type, erosion risk level, and hazard exposure, recommend suitable tree and vegetation species for soil stabilization and coastal protection.

Rules:
- Tailor species to soil type.
- Consider risk level severity.
- Include 3–6 species only.
- Provide short reason per species.
- Suggest planting strategy.
- Do not repeat generic lists.
- Avoid unsupported species for given soil conditions.
- Keep response structured.
- Prefer locally plausible, native, or commonly used stabilization species for Philippine coastal and inland contexts (e.g., mangroves, vetiver, talisay, nipa, bamboo, ipil-ipil).
- Avoid recommending species that are clearly not applicable to Philippine coastal/inland contexts unless the user explicitly indicates a different location and conditions.

You MUST respond with valid JSON only, no markdown or extra text. Use this exact structure:
{
  "recommended_species": [
    {"name": "Species Name", "reason": "Short reason for this soil/risk context"}
  ],
  "planting_strategy": ["Strategy point 1", "Strategy point 2"],
  "advisory_note": "Brief advisory or validation reminder"
}
PROMPT;

    public function __construct()
    {
        $config = config('services.together', []);
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.together.xyz/v1', '/');
        $this->model = $config['model'] ?? 'mistralai/Mixtral-8x7B-Instruct-v0.1';
        $this->timeout = (int) ($config['timeout'] ?? 60);
        $this->maxTokens = (int) ($config['max_tokens'] ?? 800);
        $this->temperature = (float) ($config['temperature'] ?? 0.3);
    }

    /**
     * Send a chat completion request to Together AI.
     *
     * @param  array<int, array{role: string, content: string}>  $messages  Array of {role, content} messages.
     * @return string The assistant's reply.
     *
     * @throws \RuntimeException When API key is missing or request fails.
     */
    public function chat(array $messages): string
    {
        $apiKey = config('services.together.api_key');
        if (empty($apiKey)) {
            Log::error('TOCSEA Ask: TOGETHER_API_KEY is not configured');
            throw new \RuntimeException('AI service is not configured. Please contact support.');
        }

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
        ];

        $url = $this->baseUrl . '/chat/completions';

        try {
            $response = Http::withToken($apiKey)
                ->timeout($this->timeout)
                ->post($url, $payload);

            if ($response->failed()) {
                $status = $response->status();
                $body = $response->json();
                Log::error('TOCSEA Ask: Together AI request failed', [
                    'status' => $status,
                    'error' => $body['error']['message'] ?? $response->body(),
                ]);
                throw new \RuntimeException(
                    'Unable to get AI response. Please try again later.'
                );
            }

            $data = $response->json();
            $choice = $data['choices'][0] ?? null;

            if (! $choice || ! isset($choice['message']['content'])) {
                Log::error('TOCSEA Ask: Unexpected API response structure', ['keys' => array_keys($data ?? [])]);
                throw new \RuntimeException('Invalid AI response. Please try again.');
            }

            return trim($choice['message']['content']);
        } catch (\Throwable $e) {
            Log::error('TOCSEA Ask: Request failed', [
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Request to AI service timed out or failed. Please try again.');
        }
    }

    /**
     * Build the messages array with system prompt and optional context.
     */
    public function buildMessages(string $question, ?array $context = null, array $chatHistory = []): array
    {
        $messages = [
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
        ];

        foreach ($chatHistory as $msg) {
            $role = ($msg['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = $msg['content'] ?? '';
            if ($content !== '') {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }

        $userContent = $question;
        if ($context !== null && $context !== []) {
            $userContent .= "\n\nContext JSON:\n" . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        $messages[] = ['role' => 'user', 'content' => $userContent];

        return $messages;
    }

    /**
     * Generate tree and vegetation recommendations based on calculation context.
     *
     * @param  array{soil_type: string, predicted_soil_loss: string|float, risk_level: string, hazard_values: array<string, mixed>, model_name?: string}  $context
     * @return array{recommended_species: array<int, array{name: string, reason: string}>, planting_strategy: array<int, string>, advisory_note: string}
     *
     * @throws \RuntimeException When API key is missing or request fails.
     */
    public function generateTreeRecommendations(array $context): array
    {
        $userContent = 'Generate tree and vegetation recommendations for this coastal soil erosion scenario. Context JSON:' . "\n"
            . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $messages = [
            ['role' => 'system', 'content' => self::TREE_RECOMMENDATION_SYSTEM_PROMPT],
            ['role' => 'user', 'content' => $userContent],
        ];

        $raw = $this->chat($messages);

        return $this->parseTreeRecommendationResponse($raw);
    }

    /**
     * Parse AI response into structured tree recommendation array.
     */
    protected function parseTreeRecommendationResponse(string $raw): array
    {
        $raw = trim($raw);

        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $raw, $m)) {
            $raw = trim($m[1]);
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            Log::warning('TOCSEA Tree Recommendations: Failed to parse AI response as JSON', ['raw' => substr($raw, 0, 500)]);
            throw new \RuntimeException('Invalid AI response format. Please try again.');
        }

        $species = $decoded['recommended_species'] ?? [];
        if (! is_array($species)) {
            $species = [];
        }

        $strategy = $decoded['planting_strategy'] ?? [];
        if (! is_array($strategy)) {
            $strategy = [];
        }

        $advisory = $decoded['advisory_note'] ?? 'This output is generated through an AI-based advisory system and shall not replace technical site assessment. Validation with appropriate environmental authorities is strongly advised.';
        if (! is_string($advisory)) {
            $advisory = 'This output is generated through an AI-based advisory system and shall not replace technical site assessment. Validation with appropriate environmental authorities is strongly advised.';
        }

        $normalized = [];
        foreach ($species as $item) {
            if (is_array($item) && isset($item['name']) && isset($item['reason'])) {
                $normalized[] = [
                    'name' => (string) $item['name'],
                    'reason' => (string) $item['reason'],
                ];
            }
        }

        return [
            'recommended_species' => $normalized,
            'planting_strategy' => array_map('strval', array_values($strategy)),
            'advisory_note' => $advisory,
        ];
    }
}
