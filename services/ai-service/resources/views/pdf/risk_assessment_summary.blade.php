<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; }
        h1 { font-size: 18px; color: #252578; margin-bottom: 4px; }
        .meta { color: #666; font-size: 11px; margin-bottom: 20px; }
        .summary-box { background: #f5f6fa; border-left: 4px solid #252578; padding: 12px 16px; margin-bottom: 20px; }
        .summary-box .level { font-weight: bold; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #f0f0f5; text-align: left; padding: 8px; font-size: 10px; text-transform: uppercase; color: #555; border-bottom: 2px solid #ddd; }
        td { padding: 8px; border-bottom: 1px solid #eee; vertical-align: top; font-size: 11px; }
        .severity { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .severity-low { background: #d1fae5; color: #065f46; }
        .severity-medium { background: #fef3c7; color: #92400e; }
        .severity-high, .severity-critical { background: #fee2e2; color: #b91c1c; }
        .footer { margin-top: 24px; font-size: 9px; color: #999; }
    </style>
</head>
<body>
    <h1>AI Risk Assessment Summary</h1>
    <p class="meta">
        Contract #{{ $result->contract_id }} &middot;
        Scanned: {{ $result->scanned_at?->format('M d, Y H:i') ?? 'N/A' }} &middot;
        Generated: {{ now()->format('M d, Y H:i') }}
    </p>

    <div class="summary-box">
        <p>
            Overall Risk Level:
            <span class="level">{{ $result->risk_level ?? 'N/A' }}</span>
            &nbsp;|&nbsp; Risk Score: {{ $result->risk_score ?? 'N/A' }}
            &nbsp;|&nbsp; Flagged Clauses: {{ $findings->count() }}
        </p>
        <p style="margin-top: 8px; font-size: 10px; color: #555;">
            This assessment is a recommendation to support your review — it is not
            an automatic verdict. Every finding below is grounded in a specific
            retrieved playbook clause and a structured judgment (RAG), not a
            free-floating model claim.
        </p>
    </div>

    @if ($findings->isEmpty())
        <p>No flagged clauses were identified in this assessment.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">Severity</th>
                    <th style="width: 20%;">Playbook Clause</th>
                    <th style="width: 30%;">Deviation Reason</th>
                    <th style="width: 40%;">Recommended Remediation</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($findings as $finding)
                    <tr>
                        <td><span class="severity severity-{{ $finding->severity }}">{{ $finding->severity }}</span></td>
                        <td>
                            @if ($finding->playbookClause)
                                <strong>{{ $finding->playbookClause->clause_code }}</strong><br>
                                {{ $finding->playbookClause->title }}
                            @else
                                &mdash;
                            @endif
                        </td>
                        <td>{{ $finding->deviation_reason }}</td>
                        <td>{{ $finding->recommended_remediation }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="footer">CMS Capstone &middot; AI Risk Assessment (US-026) &middot; Generated automatically, for internal review purposes.</p>
</body>
</html>
