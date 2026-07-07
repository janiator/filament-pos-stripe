        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            color: #000;
            line-height: 1.4;
            padding: 15px;
            background-color: #fff;
        }
        .header {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 18pt;
            font-weight: 600;
            margin-bottom: 15px;
            color: #111827;
        }
        .header-info {
            display: table;
            width: 100%;
            font-size: 9pt;
        }
        .header-info > div {
            display: table-cell;
            width: 50%;
            padding-right: 15px;
            vertical-align: top;
            margin-bottom: 5px;
        }
        .header-info strong {
            color: #6b7280;
        }
        .metrics {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .metrics > div {
            display: table-cell;
            width: 25%;
            padding: 0 7px;
            vertical-align: top;
        }
        .metrics > div:first-child {
            padding-left: 0;
        }
        .metrics > div:last-child {
            padding-right: 0;
        }
        .metric-card {
            background-color: #fff;
            border: 1px solid #e5e7eb;
            padding: 15px;
            border-radius: 5px;
        }
        .metric-label {
            font-size: 8pt;
            color: #6b7280;
            margin-bottom: 8px;
        }
        .metric-value {
            font-size: 16pt;
            font-weight: 700;
            color: #111827;
        }
        .section {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .section-title {
            font-size: 12pt;
            font-weight: 600;
            margin-bottom: 12px;
            color: #111827;
        }
        .cash-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .cash-grid > div {
            display: table-cell;
            width: 25%;
            padding: 0 7px;
            vertical-align: top;
        }
        .cash-grid.with-tips > div {
            width: 20%;
        }
        .cash-grid > div:first-child {
            padding-left: 0;
        }
        .cash-grid > div:last-child {
            padding-right: 0;
        }
        .cash-item {
            padding: 12px;
            border-radius: 5px;
        }
        .cash-item.yellow {
            background-color: #fefce8;
            border: 1px solid #fde047;
        }
        .cash-item.purple {
            background-color: #faf5ff;
            border: 1px solid #e9d5ff;
        }
        .cash-item.red {
            background-color: #fef2f2;
            border: 1px solid #fca5a5;
        }
        .cash-item.green {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
        }
        .cash-item.blue {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
        }
        .cash-item strong {
            font-size: 9pt;
            color: #4b5563;
            display: block;
            margin-bottom: 5px;
        }
        .cash-item .value {
            font-size: 14pt;
            font-weight: 700;
            color: #111827;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 7pt;
        }
        table th {
            background-color: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            padding: 5px 4px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 7pt;
        }
        table td {
            padding: 5px 4px;
            border-bottom: 1px solid #f3f4f6;
            color: #4b5563;
            font-size: 7pt;
            word-wrap: break-word;
        }
        table tr:last-child td {
            border-bottom: none;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .page-break {
            page-break-after: always;
        }
        .transaction-table {
            font-size: 6.5pt;
        }
        .transaction-table th {
            font-size: 6.5pt;
            padding: 4px 3px;
        }
        .transaction-table td {
            font-size: 6.5pt;
            padding: 4px 3px;
        }
        .status-succeeded {
            color: #22c55e;
            font-weight: 600;
        }
        .status-refunded {
            color: #dc2626;
            font-weight: 600;
        }
        .status-pending {
            color: #eab308;
            font-weight: 600;
        }
        .status-processing {
            color: #3b82f6;
            font-weight: 600;
        }
        .status-failed, .status-cancelled {
            color: #6b7280;
            font-weight: 600;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
