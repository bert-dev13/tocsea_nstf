<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calculation {{ $calculation->id }} - TOCSEA</title>
    <style>
        body { font-family: system-ui, sans-serif; font-size: 14px; line-height: 1.5; color: #1a1a1a; max-width: 700px; margin: 2rem auto; padding: 0 1rem; }
        h1 { font-size: 1.25rem; margin-bottom: 0.5rem; }
        .meta { color: #666; font-size: 0.875rem; margin-bottom: 1.5rem; }
        dl { margin: 0; }
        dt { font-weight: 600; color: #444; margin-top: 0.75rem; }
        dd { margin: 0.25rem 0 0; }
        .formula { background: #f5f5f5; padding: 0.75rem; border-radius: 6px; font-family: ui-monospace, monospace; font-size: 0.8125rem; word-break: break-all; }
        .advisory { background: #fef3c7; border: 1px solid #f59e0b; padding: 0.75rem; border-radius: 6px; font-size: 0.875rem; margin-top: 1rem; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
        .badge-low { background: #d1fae5; color: #065f46; }
        .badge-moderate { background: #fef3c7; color: #92400e; }
        .badge-high { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <h1>Soil Calculator Run</h1>
    <p class="meta">ID: {{ $calculation->id }} · {{ $calculation->created_at->format('M j, Y g:i A') }}</p>

    <dl>
        <dt>User</dt>
        <dd>{{ $calculation->user?->name ?? '—' }} ({{ $calculation->user?->email ?? '—' }})</dd>

        <dt>Location</dt>
        <dd>{{ $location_display }}</dd>

        <dt>Model/Equation</dt>
        <dd>{{ $calculation->equation_name }}</dd>

        <dt>Predicted Soil Loss</dt>
        <dd>{{ number_format($calculation->result, 2) }} m²/year</dd>

        <dt>Risk Level</dt>
        <dd><span class="badge badge-{{ strtolower($risk_level) }}">{{ $risk_level }}</span></dd>

        <dt>Formula</dt>
        <dd class="formula">{{ html_entity_decode($calculation->formula_snapshot ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') }}</dd>

        <dt>Inputs</dt>
        <dd>
            @if(is_array($calculation->inputs))
                <ul style="margin:0; padding-left:1.25rem;">
                    @foreach($calculation->inputs as $key => $value)
                        <li><strong>{{ $key }}</strong>: {{ is_array($value) ? json_encode($value) : $value }}</li>
                    @endforeach
                </ul>
            @else
                —
            @endif
        </dd>

        @if($calculation->notes)
        <dt>Notes</dt>
        <dd>{{ $calculation->notes }}</dd>
        @endif
    </dl>

    <p class="advisory">This output is generated through validated models and site-specific parameters. For critical decisions, validation with appropriate environmental authorities is advised.</p>

    <p style="margin-top:2rem; font-size:0.75rem; color:#888;">TOCSEA Admin · Generated {{ now()->format('M j, Y g:i A') }}</p>
</body>
</html>
