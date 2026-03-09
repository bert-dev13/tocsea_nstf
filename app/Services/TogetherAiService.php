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
You are TOCSEA, an intelligent environmental analysis assistant designed to interpret coastal hazard and soil erosion results.

Your role is to explain environmental risk results in a clear, professional, and analytical way suitable for students, researchers, planners, and local government decision makers.

OUTPUT RULES — CRITICAL:
- Do NOT use fixed report templates (Quick Interpretation, Key Contributing Factors, Recommended Interventions, Risk Reduction Strategy, Report-Ready Summary Paragraph).
- Do NOT show reasoning steps (Step 1, Step 2, Step 3) or chain-of-thought. Output only your final explanation.
- Provide a natural explanation similar to how an environmental scientist would explain the results.

STRUCTURE (logical flow, not rigid headings):
- Start by explaining the overall risk level.
- Describe the environmental conditions contributing to the result.
- Explain how these factors influence soil erosion or land stability.
- Provide realistic mitigation or management recommendations.
- End with a short concluding insight about the environmental implications.

RESPONSE GUIDELINES:
- Clear, professional, concise, and informative.
- Avoid repeating the same format in every answer. Each response should feel naturally written.
- Use short paragraphs for readability.
- If numerical values are present (precipitation, storm surges, soil loss), briefly explain their environmental impact.
- Ensure the response is understandable for both technical and non-technical users.
- Sound like an environmental expert interpreting coastal risk data rather than a rigid AI-generated report.

CONTENT RULES:
- If environmental context (calculation inputs, model equation, predicted soil loss, hazard values, soil type, vegetation, seawall, precipitation) is provided — use it in your explanation.
- Never invent numerical values not in the provided context.
- If important data is missing, ask one short clarifying question, then stop.
- Do NOT claim legal authority or official DENR approval.
- Do not use markdown headings (no ## or ###).
PROMPT;

    public const TREE_RECOMMENDATION_SYSTEM_PROMPT = <<<'PROMPT'
You are the TOCSEA Environmental Advisory for coastal soil erosion management.

You MUST respond with valid JSON only: no markdown, no code fences, no extra text before or after the JSON.

Use this EXACT structure (only the "recommendations" array is required; every item must have species, purpose, recommended_number as a number, and unit):
{
  "recommendations": [
    {
      "species": "Vetiver Grass",
      "purpose": "Low-maintenance slope stabilization",
      "recommended_number": 400,
      "unit": "clumps/hectare"
    },
    {
      "species": "Carabao Grass",
      "purpose": "Ground cover for soil retention",
      "recommended_number": 300,
      "unit": "patches/hectare"
    },
    {
      "species": "Ipil-Ipil (Leucaena)",
      "purpose": "Nitrogen-fixing, light cover",
      "recommended_number": 150,
      "unit": "trees/hectare"
    }
  ]
}

RULES:
- Always provide a numeric value for recommended_number (integer). Never leave it blank, null, or missing.
- Use proper units: trees/hectare, clumps/hectare, patches/hectare, shrubs/hectare.
- Recommend 3–5 species. Prefer Philippine coastal/inland species (mangroves, vetiver, bamboo, ipil-ipil, agoho, talisay, nipa).
- Adapt to soil type: Clay (Vetiver, Carabao Grass, Ipil-Ipil, Bamboo, Mangrove); Sandy (Mangrove, Agoho, Coconut, Beach Grass); Loamy (Mahogany, Bamboo, Ipil-Ipil, Vetiver).
- Every recommendations[] item MUST have: species (string), purpose (string), recommended_number (number), unit (string).
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

        $messages = $this->normalizeMessagesForApi($messages);
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
        ];

        $url = $this->baseUrl . '/chat/completions';

        if (config('app.debug')) {
            Log::debug('TOCSEA Ask: Together AI request', [
                'model' => $this->model,
                'message_count' => count($messages),
            ]);
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout($this->timeout)
                ->post($url, $payload);

            if ($response->failed()) {
                $status = $response->status();
                $body = $response->json();
                $apiMessage = $body['error']['message'] ?? $body['message'] ?? $response->body();
                Log::error('TOCSEA Ask: Together AI request failed', [
                    'status' => $status,
                    'error' => $apiMessage,
                ]);
                $userMessage = is_string($apiMessage) && strlen($apiMessage) < 200
                    ? $apiMessage
                    : 'Unable to get AI response. Please try again later.';
                throw new \RuntimeException($userMessage);
            }

            $data = $response->json();
            $choice = $data['choices'][0] ?? null;

            if (! $choice || ! isset($choice['message']['content'])) {
                Log::error('TOCSEA Ask: Unexpected API response structure', [
                    'keys' => array_keys($data ?? []),
                    'raw_preview' => is_string($response->body()) ? substr($response->body(), 0, 500) : null,
                ]);
                throw new \RuntimeException('Invalid AI response. Please try again.');
            }

            $raw = trim($choice['message']['content']);
            return $this->cleanAskTocseaResponse($raw);
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('TOCSEA Ask: Connection failed (timeout or unreachable)', [
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Request to AI service timed out or could not connect. Please try again.');
        } catch (\Throwable $e) {
            Log::error('TOCSEA Ask: Request failed', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            throw new \RuntimeException('Request to AI service failed. Please try again.');
        }
    }

    /**
     * Remove internal reasoning steps (Step 1, Step 2, chain-of-thought) from Ask TOCSEA response.
     * Keeps only the final structured answer (Quick Interpretation, Key Contributing Factors, etc.).
     */
    protected function cleanAskTocseaResponse(string $content): string
    {
        if ($content === '') {
            return $content;
        }

        $lines = preg_split('/\r\n|\r|\n/', $content);
        $out = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                $out[] = '';
                continue;
            }

            $lower = strtolower($trimmed);

            // Remove internal reasoning: "Step 1:", "Step 2:", etc.
            if (preg_match('/^\s*Step\s*\d+\s*:?\s*/i', $trimmed)) {
                continue;
            }
            // Remove numbered reasoning like "1. Understand the relationship..."
            if (preg_match('/^\d+\.\s*(?:Understand|Identify|Analyze|Consider|Summarize|Evaluate)\s/i', $trimmed)) {
                continue;
            }
            // Remove chain-of-thought preamble lines
            if (preg_match('/^(?:First\s+I\s+will|Let\s+me\s+(?:analyze|consider|explain)|To\s+answer\s+this|I\'ll\s+start\s+by|In\s+order\s+to)\s/i', $lower)) {
                continue;
            }

            $out[] = $line;
        }

        $joined = implode("\n", $out);
        $joined = preg_replace('/\n{3,}/', "\n\n", $joined);
        return trim($joined);
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
     * Ensure all message contents are strings for the Together API (avoids input validation errors).
     *
     * @param  array<int, array{role: string, content: mixed}>  $messages
     * @return array<int, array{role: string, content: string}>
     */
    protected function normalizeMessagesForApi(array $messages): array
    {
        $out = [];
        foreach ($messages as $i => $msg) {
            $role = isset($msg['role']) && is_string($msg['role']) ? $msg['role'] : 'user';
            $content = $msg['content'] ?? '';
            if (is_array($content)) {
                $content = json_encode($content);
            }
            $content = trim((string) $content);
            if ($content === '' && $role === 'system') {
                continue;
            }
            $out[] = ['role' => $role, 'content' => $content];
        }
        return $out;
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
        $riskLevel = $context['risk_level'] ?? 'moderate';
        $impactLevel = is_string($riskLevel) ? ucfirst(strtolower($riskLevel)) : 'Moderate';
        $inputs = [
            'Predicted_Soil_Loss_m2_per_year' => $context['predicted_soil_loss'] ?? 0,
            'Impact_Level' => $impactLevel,
            'Soil_Type' => $context['soil_type'] ?? 'loamy',
            'Seawall_Length_m' => $context['seawall_length'] ?? $h['Seawall_m'] ?? $h['seawall'] ?? null,
            'Precipitation_mm' => $context['precipitation'] ?? $h['Precipitation_mm'] ?? $h['precipitation'] ?? null,
            'Tropical_Storms' => $context['tropical_storms'] ?? $h['Tropical_Storms'] ?? $h['tropical_storm'] ?? null,
            'Floods' => $context['floods'] ?? $h['Floods'] ?? $h['floods'] ?? null,
        ];

        $userContent = 'Generate vegetation recommendations. Return ONLY valid JSON with a "recommendations" array. Each item must have species, purpose, recommended_number (numeric), unit. Use these inputs:' . "\n"
            . json_encode($inputs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $messages = [
            ['role' => 'system', 'content' => self::TREE_RECOMMENDATION_SYSTEM_PROMPT],
            ['role' => 'user', 'content' => $userContent],
        ];

        try {
            $raw = $this->chat($messages);
        } catch (\Throwable $e) {
            Log::warning('TOCSEA Tree Recommendations: AI request failed, using fallback', ['message' => $e->getMessage()]);

            return $this->buildFallbackTreeRecommendations($context);
        }

        Log::debug('TOCSEA Tree Recommendations: raw Together AI response', [
            'length' => strlen($raw),
            'preview' => substr($raw, 0, 600),
        ]);

        try {
            $result = $this->parseTreeRecommendationResponse($raw, $context);
        } catch (\Throwable $e) {
            Log::warning('TOCSEA Tree Recommendations: Parse failed, using fallback', ['message' => $e->getMessage()]);

            return $this->buildFallbackTreeRecommendations($context);
        }

        if (empty($result['recommended_species'])) {
            Log::warning('TOCSEA Tree Recommendations: AI returned no species, using fallback');

            return $this->buildFallbackTreeRecommendations($context);
        }

        Log::debug('TOCSEA Tree Recommendations: final result passed to frontend', [
            'recommended_species' => array_map(function ($s) {
                return [
                    'name' => $s['name'],
                    'recommended_planting' => $s['recommended_planting'] ?? 'Not available',
                ];
            }, $result['recommended_species'] ?? []),
        ]);

        return $result;
    }

    /**
     * Extract recommended quantity string from a species item (multiple possible keys or from reason text).
     *
     * @param  array<string, mixed>  $item  Decoded species object from AI
     * @param  string  $reason  Reason/purpose text (may contain "X units / hectare" etc.)
     * @return string Non-empty string for display, or empty if not found
     */
    protected static function extractRecommendedQuantity(array $item, string $reason): string
    {
        $lower = array_change_key_case($item, CASE_LOWER);
        // When both recommended_number and unit exist, combine them for display (e.g. "400 clumps/hectare")
        $num = null;
        $unit = null;
        if (isset($lower['recommended_number']) && $lower['recommended_number'] !== '' && $lower['recommended_number'] !== null) {
            $n = $lower['recommended_number'];
            if (is_numeric($n)) {
                $num = (string) (int) $n;
            } elseif (is_scalar($n)) {
                $num = trim((string) $n);
            }
        }
        if (isset($lower['unit']) && is_scalar($lower['unit']) && trim((string) $lower['unit']) !== '') {
            $unit = trim((string) $lower['unit']);
        }
        if ($num !== null && $num !== '') {
            return $unit !== null ? $num . ' ' . $unit : $num;
        }

        $keys = [
            'recommended_planting', 'quantity', 'recommended_quantity',
            'number_per_hectare', 'plants_per_hectare',
        ];
        foreach ($keys as $key) {
            if (isset($lower[$key]) && $lower[$key] !== '' && $lower[$key] !== null) {
                $v = $lower[$key];
                if (is_scalar($v)) {
                    return trim((string) $v);
                }
            }
        }
        foreach (array_keys($item) as $key) {
            if (is_string($key) && (stripos($key, 'recommend') !== false || stripos($key, 'quantity') !== false || stripos($key, 'number') !== false) && isset($item[$key]) && is_scalar($item[$key])) {
                $v = trim((string) $item[$key]);
                if ($v !== '') {
                    return $v;
                }
            }
        }
        if (isset($item['recommended']) && is_array($item['recommended'])) {
            $rec = $item['recommended'];
            $n = isset($rec['number']) ? trim((string) $rec['number']) : '';
            $u = isset($rec['unit']) ? trim((string) $rec['unit']) : '';
            if ($n !== '') {
                return $u !== '' ? $n . ' ' . $u : $n;
            }
        }
        if ($reason !== '') {
            if (preg_match('/\d{1,6}\s*(?:trees?|clumps?|patches?|shrubs?|plants?)\s*(?:\/|per)\s*hectare/i', $reason, $m)) {
                return trim($m[0]);
            }
            if (preg_match('/(?:recommended|recommendation|planting):\s*([^.—\n]+?)(?:\s*[.—]|$)/i', $reason, $m)) {
                return trim($m[1]);
            }
        }
        return '';
    }

    /**
     * Try to extract a recommended quantity string from raw AI response text near the species name.
     */
    protected static function extractQuantityFromRaw(string $speciesName, string $raw): string
    {
        if ($speciesName === '' || $raw === '') {
            return '';
        }
        $pos = stripos($raw, $speciesName);
        if ($pos === false) {
            return '';
        }
        $window = substr($raw, $pos, 400);
        if (preg_match('/\d{1,6}\s*(?:trees?|clumps?|patches?|shrubs?|plants?)\s*(?:\/|per)\s*hectare/i', $window, $m)) {
            return trim($m[0]);
        }
        if (preg_match('/(?:recommended|recommendation|planting):\s*([^.\n"]+)/i', $window, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/\d{1,6}\s*(?:trees?|clumps?|patches?|shrubs?)/i', $window, $m)) {
            return trim($m[0]) . ' / hectare';
        }
        return '';
    }

    /**
     * Parse AI response into structured tree recommendation array.
     * Supports new format (ground_cover, coastal_trees, planting_strategy object) and legacy (recommended_species, planting_strategy array).
     */
    protected function parseTreeRecommendationResponse(string $raw, array $context = []): array
    {
        $fullRaw = trim($raw);
        $raw = $fullRaw;

        Log::debug('TOCSEA Tree Recommendations: raw AI response length', ['length' => strlen($raw), 'preview' => substr($raw, 0, 300)]);

        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $raw, $m)) {
            $raw = trim($m[1]);
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            // Try to extract JSON object from response (e.g. "Here is the JSON: {...}")
            $firstBrace = strpos($raw, '{');
            if ($firstBrace !== false) {
                $lastBrace = strrpos($raw, '}');
                if ($lastBrace !== false && $lastBrace > $firstBrace) {
                    $decoded = json_decode(substr($raw, $firstBrace, $lastBrace - $firstBrace + 1), true);
                }
            }
        }
        if (! is_array($decoded)) {
            Log::warning('TOCSEA Tree Recommendations: Failed to parse AI response as JSON', ['raw' => substr($raw, 0, 500)]);
            throw new \RuntimeException('Invalid AI response format. Please try again.');
        }

        Log::debug('TOCSEA Tree Recommendations: decoded JSON keys', ['keys' => array_keys($decoded)]);

        $advisory = $decoded['advisory_note'] ?? 'This output is generated through an AI-based advisory system and shall not replace technical site assessment. Validation with appropriate environmental authorities is strongly advised.';
        if (! is_string($advisory)) {
            $advisory = 'This output is generated through an AI-based advisory system and shall not replace technical site assessment. Validation with appropriate environmental authorities is strongly advised.';
        }

        $defaultStrategy = [
            'shoreline' => 'Plant mangroves and coastal species in the intertidal zone.',
            'mid_slope' => 'Use deep-rooted species and grasses for slope stabilization.',
            'inland' => 'Maintain ground cover and reinforce with trees where suitable.',
        ];

        $strategyObj = $decoded['planting_strategy'] ?? [];
        if (! is_array($strategyObj)) {
            $strategyObj = [];
        }

        // Normalize one species item: accept name/species, reason/purpose, and build recommended_planting from recommended_number + unit or extract
        $normalizeOne = function (array $item): ?array {
            $name = isset($item['species']) ? trim((string) $item['species']) : (isset($item['name']) ? trim((string) $item['name']) : '');
            if ($name === '') {
                return null;
            }
            $reason = isset($item['purpose']) ? (string) $item['purpose'] : (isset($item['reason']) ? (string) $item['reason'] : '');
            $number = static::extractRecommendedQuantity($item, $reason);
            if ($number === '' && isset($item['recommended_number']) && isset($item['unit'])) {
                $n = $item['recommended_number'];
                $u = trim((string) $item['unit']);
                if (is_numeric($n) && $u !== '') {
                    $number = (string) (int) $n . ' ' . $u;
                }
            }
            if ($number === '') {
                $number = 'Not available';
            }
            return [
                'name' => $name,
                'reason' => $reason,
                'recommended_planting' => $number,
            ];
        };

        $groundCover = [];
        $coastalTrees = [];
        $recommendedSpecies = [];

        // New format: flat "recommendations" array with species, purpose, recommended_number, unit
        $flatRecommendations = $decoded['recommendations'] ?? null;
        if (is_array($flatRecommendations) && ! empty($flatRecommendations)) {
            foreach ($flatRecommendations as $item) {
                $norm = $normalizeOne(is_array($item) ? $item : []);
                if ($norm === null) {
                    continue;
                }
                $recommendedSpecies[] = $norm;
                $n = strtolower($norm['name']);
                if (str_contains($n, 'grass') || str_contains($n, 'vetiver') || str_contains($n, 'carabao') || str_contains($n, 'ground')) {
                    $groundCover[] = $norm;
                } else {
                    $coastalTrees[] = $norm;
                }
            }
        }

        // Legacy format: ground_cover + coastal_trees arrays (name, reason, recommended_number)
        if (empty($recommendedSpecies)) {
            $groundCover = $decoded['ground_cover'] ?? [];
            $coastalTrees = $decoded['coastal_trees'] ?? [];
            if (! is_array($groundCover)) {
                $groundCover = [];
            }
            if (! is_array($coastalTrees)) {
                $coastalTrees = [];
            }
            $normalizeSpecies = function (array $items): array {
                $out = [];
                foreach ($items as $item) {
                    $norm = $this->normalizeOneSpeciesItem($item);
                    if ($norm !== null) {
                        $out[] = $norm;
                    }
                }
                return $out;
            };
            $groundCover = $normalizeSpecies($groundCover);
            $coastalTrees = $normalizeSpecies($coastalTrees);

            foreach ($groundCover as &$s) {
                if (($s['recommended_planting'] ?? '') === '') {
                    $extracted = static::extractQuantityFromRaw((string) $s['name'], $fullRaw);
                    $s['recommended_planting'] = $extracted !== '' ? $extracted : 'Not available';
                }
            }
            foreach ($coastalTrees as &$s) {
                if (($s['recommended_planting'] ?? '') === '') {
                    $extracted = static::extractQuantityFromRaw((string) $s['name'], $fullRaw);
                    $s['recommended_planting'] = $extracted !== '' ? $extracted : 'Not available';
                }
            }

            foreach (array_merge($groundCover, $coastalTrees) as $s) {
                $rp = $s['recommended_planting'] ?? '';
                if ($rp === '') {
                    $rp = 'Not available';
                }
                $recommendedSpecies[] = [
                    'name' => $s['name'],
                    'reason' => $s['reason'],
                    'recommended_planting' => $rp,
                ];
            }
        }

        // Legacy recommended_species only when we still have nothing
        if (empty($recommendedSpecies)) {
            $legacySpecies = $decoded['recommended_species'] ?? [];
            foreach (is_array($legacySpecies) ? $legacySpecies : [] as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $norm = $normalizeOne($item);
                if ($norm === null && isset($item['name'])) {
                    $rp = isset($item['recommended_planting']) ? (string) $item['recommended_planting'] : '';
                    if ($rp === '' && isset($item['recommended_number']) && isset($item['unit'])) {
                        $n = $item['recommended_number'];
                        $u = trim((string) $item['unit']);
                        $rp = (is_numeric($n) && $u !== '') ? (string) (int) $n . ' ' . $u : 'Not available';
                    }
                    if ($rp === '') {
                        $rp = 'Not available';
                    }
                    $norm = [
                        'name' => (string) $item['name'],
                        'reason' => isset($item['reason']) ? (string) $item['reason'] : (isset($item['purpose']) ? (string) $item['purpose'] : ''),
                        'recommended_planting' => $rp,
                    ];
                }
                if ($norm !== null) {
                    $recommendedSpecies[] = $norm;
                }
            }
        }

        $plantingStrategy = [
            'shoreline' => isset($strategyObj['shoreline']) && is_string($strategyObj['shoreline'])
                ? trim($strategyObj['shoreline']) : $defaultStrategy['shoreline'],
            'mid_slope' => isset($strategyObj['mid_slope']) && is_string($strategyObj['mid_slope'])
                ? trim($strategyObj['mid_slope']) : $defaultStrategy['mid_slope'],
            'inland' => isset($strategyObj['inland']) && is_string($strategyObj['inland'])
                ? trim($strategyObj['inland']) : $defaultStrategy['inland'],
        ];

        $legacyStrategy = [
            'Shoreline — ' . $plantingStrategy['shoreline'],
            'Mid-Slope — ' . $plantingStrategy['mid_slope'],
            'Inland — ' . $plantingStrategy['inland'],
        ];

        $result = [
            'ground_cover' => $groundCover,
            'coastal_trees' => $coastalTrees,
            'planting_strategy' => $plantingStrategy,
            'recommended_species' => $recommendedSpecies,
            'planting_strategy_array' => $legacyStrategy,
            'advisory_note' => $advisory,
        ];

        Log::debug('TOCSEA Tree Recommendations: parsed output', [
            'recommended_species_count' => count($recommendedSpecies),
            'recommended_planting_values' => array_map(function ($s) {
                return ['name' => $s['name'], 'recommended_planting' => $s['recommended_planting'] ?? ''];
            }, $recommendedSpecies),
        ]);

        return $result;
    }

    /**
     * Normalize one species item from legacy ground_cover/coastal_trees (name, reason keys).
     */
    protected function normalizeOneSpeciesItem(array $item): ?array
    {
        $name = isset($item['name']) ? trim((string) $item['name']) : (isset($item['species']) ? trim((string) $item['species']) : '');
        if ($name === '') {
            return null;
        }
        $reason = isset($item['reason']) ? (string) $item['reason'] : (isset($item['purpose']) ? (string) $item['purpose'] : '');
        $number = static::extractRecommendedQuantity($item, $reason);
        if ($number === '') {
            $number = 'Not available';
        }
        return [
            'name' => $name,
            'reason' => $reason,
            'recommended_planting' => $number,
        ];
    }

    /**
     * Build fallback tree recommendations with default numbers when AI is unavailable or returns empty.
     * Uses soil type and risk level to pick species and scale quantities.
     *
     * @param  array{soil_type: string, risk_level: string, predicted_soil_loss?: string|float}  $context
     * @return array{ground_cover: array, coastal_trees: array, planting_strategy: array, recommended_species: array, planting_strategy_array: array, advisory_note: string}
     */
    public function buildFallbackTreeRecommendations(array $context): array
    {
        $soil = strtolower((string) ($context['soil_type'] ?? 'loamy'));
        $risk = strtolower((string) ($context['risk_level'] ?? 'moderate'));
        $scale = $risk === 'high' ? 1.2 : ($risk === 'low' ? 0.8 : 1.0);

        $advisory = 'This output is generated through an AI-based advisory system and shall not replace technical site assessment. Validation with appropriate environmental authorities is strongly advised.';

        $defaultStrategy = [
            'shoreline' => 'Plant mangroves and coastal species in the intertidal zone.',
            'mid_slope' => 'Use deep-rooted species and grasses for slope stabilization.',
            'inland' => 'Maintain ground cover and reinforce with trees where suitable.',
        ];
        $plantingStrategyArray = [
            'Shoreline — ' . $defaultStrategy['shoreline'],
            'Mid-Slope — ' . $defaultStrategy['mid_slope'],
            'Inland — ' . $defaultStrategy['inland'],
        ];

        // Species by soil type (mirror frontend TREE_RECOMMENDATIONS) with default quantity templates: [name, reason, baseNumber, unit]
        $templates = [
            'sandy' => [
                ['Vetiver Grass', 'Deep roots for slope stabilization', (int) round(350 * $scale), 'clumps/hectare'],
                ['Beach Naupaka (Scaevola)', 'Salt-tolerant coastal cover', (int) round(300 * $scale), 'patches/hectare'],
                ['Agoho (Casuarina)', 'Windbreak and sand stabilization', (int) round(180 * $scale), 'trees/hectare'],
                ['Mangrove Species', 'Coastal protection', (int) round(200 * $scale), 'trees/hectare'],
            ],
            'clay' => [
                ['Vetiver Grass', 'Deep roots for slope stabilization', (int) round(380 * $scale), 'clumps/hectare'],
                ['Clumping Bamboo', 'Soil binding and windbreak', (int) round(150 * $scale), 'clumps/hectare'],
                ['Ipil-Ipil (Leucaena)', 'Nitrogen-fixing, erosion control', (int) round(150 * $scale), 'trees/hectare'],
                ['Talisay', 'Coastal buffer species', (int) round(160 * $scale), 'trees/hectare'],
            ],
            'loamy' => [
                ['Vetiver Grass', 'Deep roots for slope stabilization', (int) round(370 * $scale), 'clumps/hectare'],
                ['Clumping Bamboo', 'Soil binding and windbreak', (int) round(150 * $scale), 'clumps/hectare'],
                ['Ipil-Ipil (Leucaena)', 'Nitrogen-fixing, erosion control', (int) round(150 * $scale), 'trees/hectare'],
                ['Talisay', 'Coastal buffer species', (int) round(160 * $scale), 'trees/hectare'],
            ],
            'silty' => [
                ['Vetiver Grass', 'Deep roots for slope stabilization', (int) round(360 * $scale), 'clumps/hectare'],
                ['Clumping Bamboo', 'Soil binding', (int) round(150 * $scale), 'clumps/hectare'],
                ['Ipil-Ipil (Leucaena)', 'Nitrogen-fixing, erosion control', (int) round(150 * $scale), 'trees/hectare'],
                ['Nipa Palm', 'Wetland stabilization', (int) round(120 * $scale), 'trees/hectare'],
            ],
            'peaty' => [
                ['Vetiver Grass', 'Deep roots for slope stabilization', (int) round(340 * $scale), 'clumps/hectare'],
                ['Nipa Palm', 'Wetland stabilization', (int) round(120 * $scale), 'trees/hectare'],
                ['Mangrove Species', 'Coastal protection', (int) round(200 * $scale), 'trees/hectare'],
            ],
            'chalky' => [
                ['Vetiver Grass', 'Deep roots for slope stabilization', (int) round(360 * $scale), 'clumps/hectare'],
                ['Clumping Bamboo', 'Soil binding', (int) round(150 * $scale), 'clumps/hectare'],
                ['Talisay', 'Coastal buffer species', (int) round(160 * $scale), 'trees/hectare'],
            ],
        ];

        $list = $templates[$soil] ?? $templates['loamy'];
        $recommendedSpecies = [];
        $groundCover = [];
        $coastalTrees = [];

        foreach ($list as [$name, $reason, $num, $unit]) {
            $recommended_planting = $num . ' ' . $unit;
            $item = [
                'name' => $name,
                'reason' => $reason,
                'recommended_planting' => $recommended_planting,
            ];
            $recommendedSpecies[] = $item;
            $n = strtolower($name);
            if (str_contains($n, 'grass') || str_contains($n, 'naupaka') || str_contains($n, 'carabao')) {
                $groundCover[] = $item;
            } else {
                $coastalTrees[] = $item;
            }
        }

        return [
            'ground_cover' => $groundCover,
            'coastal_trees' => $coastalTrees,
            'planting_strategy' => $defaultStrategy,
            'recommended_species' => $recommendedSpecies,
            'planting_strategy_array' => $plantingStrategyArray,
            'advisory_note' => $advisory,
        ];
    }
}
