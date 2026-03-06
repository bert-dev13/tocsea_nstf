<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Calculation History Report - TOCSEA</title>
    <style>
        body {
            margin: 0;
            padding: 14mm;
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #111;
        }
        .report-header {
            margin-bottom: 14px;
        }
        .report-title {
            margin: 0 0 4px;
            font-size: 18px;
            font-weight: 700;
            color: #1a1a1a;
        }
        .report-subtitle {
            margin: 0;
            font-size: 11px;
            color: #444;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 8px;
        }
        .report-table th,
        .report-table td {
            padding: 8px 10px;
            border: 1px solid #bbb;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .report-table thead {
            display: table-header-group;
        }
        .report-table th {
            background: #424242;
            color: #fff;
            font-weight: 700;
            text-align: left;
        }
        .report-table th.col-date   { width: 25%; }
        .report-table th.col-name   { width: 35%; }
        .report-table th.col-inputs { width: 15%; }
        .report-table th.col-result { width: 25%; }
        .report-table td.col-date   { width: 25%; text-align: left; }
        .report-table td.col-name   { width: 35%; text-align: left; }
        .report-table td.col-inputs  { width: 15%; text-align: center; }
        .report-table td.col-result { width: 25%; text-align: right; white-space: nowrap; }
        .report-table tbody tr:nth-child(even) {
            background: #f9f9f9;
        }
    </style>
</head>
<body>
    <div class="report-header">
        <h1 class="report-title">Calculation History Report</h1>
        <p class="report-subtitle">TOCSEA — {{ $dateGenerated }}</p>
    </div>

    <table class="report-table">
        <thead>
            <tr>
                <th class="col-date">Date / Time</th>
                <th class="col-name">Equation Name</th>
                <th class="col-inputs">Inputs</th>
                <th class="col-result">Result</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
            <tr>
                <td class="col-date">{{ $row['date_time'] }}</td>
                <td class="col-name">{{ $row['equation_name'] }}</td>
                <td class="col-inputs">{{ $row['inputs'] }}</td>
                <td class="col-result">{{ $row['result_formatted'] }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="4" style="text-align: center; padding: 16px;">No records to display.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
