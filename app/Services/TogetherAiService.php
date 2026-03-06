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
You are the TOCSEA Environmental Advisory for coastal soil erosion management. Generate tree and vegetation recommendations including estimated number of trees or plant clusters based on the predicted soil loss and other inputs provided.

ADAPT TO: Predicted Soil Loss, Soil Type, Risk Level, Storm and Flood impact, and coastal protection status.

SPECIES SELECTION BY SOIL TYPE:
- Clay: Prefer Vetiver Grass, Carabao Grass, Ipil-Ipil, Bamboo, Mangrove.
- Sandy: Prefer Mangrove, Agoho, Coconut, Beach Grass, Bamboo.
- Loamy: Prefer Mahogany, Bamboo, Ipil-Ipil, Vetiver Grass, Jackfruit.
- Silty/Peaty/Chalky: Use species from the closest of Clay, Sandy, or Loamy as appropriate (e.g. Vetiver, Bamboo, Mangrove, Nipa).

QUANTITY ESTIMATION (vegetation units = trees, clusters, or patches; distribute across species, round to whole numbers):
- Soil loss 0–20,000 m²/year → 200–500 vegetation units total (low risk: smaller density).
- Soil loss 20,000–60,000 m²/year → 500–1,200 vegetation units total (moderate density).
- Soil loss 60,000–120,000 m²/year → 1,200–2,500 vegetation units total (intensive density).
- Soil loss above 120,000 m²/year → 2,500+ vegetation units total.
Scale down quantities when risk level is Low; scale up when High.

RULES:
- Do NOT mention regression formulas, calculation process, or that you are an AI model.
- Recommend 3–5 species total: split into ground cover (grasses, ground vegetation) and coastal protection trees.
- Each species: short benefit description and "Recommended planting: X clusters/trees" (use appropriate unit: clusters for grasses, trees for trees).
- Prefer Philippine coastal/inland species (mangroves, vetiver, bamboo, ipil-ipil, agoho, talisay, nipa).

You MUST respond with valid JSON only, no markdown or extra text. Use this exact structure:
{
  "ground_cover": [
    {"name": "Species Name", "reason": "Short benefit description", "recommended_planting": "e.g. 1,250 clusters"}
  ],
  "coastal_trees": [
    {"name": "Species Name", "reason": "Short benefit description", "recommended_planting": "e.g. 380 trees"}
  ],
  "planting_strategy": {
    "shoreline": "One sentence recommendation for shoreline.",
    "mid_slope": "One sentence recommendation for mid-slope.",
    "inland": "One sentence recommendation for inland."
  },
  "advisory_note": "This output is generated through an AI-based advisory system and shall not replace technical site assessment. Validation with appropriate environmental authorities is strongly advised."
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
     * @param  array{soil_type: string, predicted_soil_loss: string|float, risk_level: string, hazard_values: array<string, mixed>, model_name?: string, impact_summary?: string, seawall_length?: float|string, precipitation?: float|string, tropical_storms?: float|string, floods?: float|string}  $context
     * @return array{ground_cover: array, coastal_trees: array, planting_strategy: array, recommended_species: array, advisory_note: string}
     *
     * @throws \RuntimeException When API key is missing or request fails.
     */
    public function generateTreeRecommendations(array $context): array
    {
        $h = $context['hazard_values'] ?? [];
        $inputs = [
            'soil_loss_result' => $context['predicted_soil_loss'] ?? 0,
            'risk_level' => $context['risk_level'] ?? 'Moderate',
            'soil_type' => $context['soil_type'] ?? 'loamy',
            'seawall_length' => $context['seawall_length'] ?? $h['Seawall_m'] ?? $h['seawall'] ?? null,
            'precipitation' => $context['precipitation'] ?? $h['Precipitation_mm'] ?? $h['precipitation'] ?? null,
            'tropical_storms' => $context['tropical_storms'] ?? $h['Tropical_Storms'] ?? $h['tropical_storm'] ?? null,
            'floods' => $context['floods'] ?? $h['Floods'] ?? $h['floods'] ?? null,
            'impact_summary' => $context['impact_summary'] ?? null,
        ];

        $userContent = 'Generate tree and vegetation recommendations using these system inputs:' . "\n"
            . json_encode($inputs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $messages = [
            ['role' => 'system', 'content' => self::TREE_RECOMMENDATION_SYSTEM_PROMPT],
            ['role' => 'user', 'content' => $userContent],
        ];

        $raw = $this->chat($messages);

        return $this->parseTreeRecommendationResponse($raw);
    }

    /**
     * Parse AI response into structured tree recommendation array.
     * Supports new format (ground_cover, coastal_trees, planting_strategy object) and legacy (recommended_species, planting_strategy array).
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

        $advisory = $decoded['advisory_note'] ?? 'This output is generated through an AI-based advisory system and shall not replace technical site assessment. Validation with appropriate environmental authorities is strongly advised.';
        if (! is_string($advisory)) {
            $advisory = 'This output is generated through an AI-based advisory system and shall not replace technical site assessment. Validation with appropriate environmental authorities is strongly advised.';
        }

        $defaultStrategy = [
            'shoreline' => 'Plant mangroves and coastal species in the intertidal zone.',
            'mid_slope' => 'Use deep-rooted species and grasses for slope stabilization.',
            'inland' => 'Maintain ground cover and reinforce with trees where suitable.',
        ];

        $groundCover = $decoded['ground_cover'] ?? [];
        $coastalTrees = $decoded['coastal_trees'] ?? [];
        $strategyObj = $decoded['planting_strategy'] ?? [];

        if (! is_array($groundCover)) {
            $groundCover = [];
        }
        if (! is_array($coastalTrees)) {
            $coastalTrees = [];
        }
        if (! is_array($strategyObj)) {
            $strategyObj = [];
        }

        $normalizeSpecies = function (array $items): array {
            $out = [];
            foreach ($items as $item) {
                if (! is_array($item) || empty($item['name'])) {
                    continue;
                }
                $reason = isset($item['reason']) ? (string) $item['reason'] : '';
                $planting = isset($item['recommended_planting']) ? (string) $item['recommended_planting'] : '';
                if ($planting !== '') {
                    $reason = trim($reason . ($reason !== '' ? ' ' : '') . 'Recommended planting: ' . $planting);
                }
                $out[] = [
                    'name' => (string) $item['name'],
                    'reason' => $reason,
                    'recommended_planting' => $planting,
                ];
            }
            return $out;
        };

        $groundCover = $normalizeSpecies($groundCover);
        $coastalTrees = $normalizeSpecies($coastalTrees);

        $plantingStrategy = [
            'shoreline' => isset($strategyObj['shoreline']) && is_string($strategyObj['shoreline'])
                ? trim($strategyObj['shoreline']) : $defaultStrategy['shoreline'],
            'mid_slope' => isset($strategyObj['mid_slope']) && is_string($strategyObj['mid_slope'])
                ? trim($strategyObj['mid_slope']) : $defaultStrategy['mid_slope'],
            'inland' => isset($strategyObj['inland']) && is_string($strategyObj['inland'])
                ? trim($strategyObj['inland']) : $defaultStrategy['inland'],
        ];

        $legacySpecies = $decoded['recommended_species'] ?? [];
        $legacyStrategy = $decoded['planting_strategy'] ?? [];
        if (is_array($legacyStrategy) && ! isset($legacyStrategy['shoreline'])) {
            $legacyStrategy = array_map('strval', array_values($legacyStrategy));
        } else {
            $legacyStrategy = [
                'Shoreline — ' . $plantingStrategy['shoreline'],
                'Mid-Slope — ' . $plantingStrategy['mid_slope'],
                'Inland — ' . $plantingStrategy['inland'],
            ];
        }

        $recommendedSpecies = [];
        if (! empty($groundCover) || ! empty($coastalTrees)) {
            foreach (array_merge($groundCover, $coastalTrees) as $s) {
                $recommendedSpecies[] = ['name' => $s['name'], 'reason' => $s['reason']];
            }
        } else {
            foreach (is_array($legacySpecies) ? $legacySpecies : [] as $item) {
                if (is_array($item) && isset($item['name']) && isset($item['reason'])) {
                    $recommendedSpecies[] = [
                        'name' => (string) $item['name'],
                        'reason' => (string) $item['reason'],
                    ];
                }
            }
        }

        return [
            'ground_cover' => $groundCover,
            'coastal_trees' => $coastalTrees,
            'planting_strategy' => $plantingStrategy,
            'recommended_species' => $recommendedSpecies,
            'planting_strategy_array' => $legacyStrategy,
            'advisory_note' => $advisory,
        ];
    }
}
