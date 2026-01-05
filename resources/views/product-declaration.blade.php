<!DOCTYPE html>
<html lang="no">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produktfråsegn - {{ $declaration->product_name }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 8px;
        }

        .header {
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #1a1a1a;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .meta {
            color: #666;
            font-size: 14px;
            margin-top: 10px;
        }

        .meta span {
            margin-right: 20px;
        }

        .content {
            font-size: 16px;
            line-height: 1.8;
        }

        .content h1 {
            font-size: 24px;
            margin-top: 30px;
            margin-bottom: 15px;
            color: #1a1a1a;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 10px;
        }

        .content h2 {
            font-size: 20px;
            margin-top: 25px;
            margin-bottom: 12px;
            color: #2a2a2a;
        }

        .content h3 {
            font-size: 18px;
            margin-top: 20px;
            margin-bottom: 10px;
            color: #3a3a3a;
        }

        .content p {
            margin-bottom: 15px;
        }

        .content ul, .content ol {
            margin-left: 30px;
            margin-bottom: 15px;
        }

        .content li {
            margin-bottom: 8px;
        }

        .content code {
            background-color: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }

        .content pre {
            background-color: #f4f4f4;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            margin-bottom: 15px;
        }

        .content pre code {
            background: none;
            padding: 0;
        }

        .content strong {
            font-weight: 600;
            color: #1a1a1a;
        }

        .content em {
            font-style: italic;
        }

        .content a {
            color: #0066cc;
            text-decoration: none;
        }

        .content a:hover {
            text-decoration: underline;
        }

        .content hr {
            border: none;
            border-top: 1px solid #e0e0e0;
            margin: 30px 0;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
            color: #666;
            font-size: 14px;
            text-align: center;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .container {
                box-shadow: none;
                padding: 20px;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }

            .header h1 {
                font-size: 24px;
            }

            .content {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $declaration->product_name }}</h1>
            <div class="meta">
                @if($declaration->vendor_name)
                    <span><strong>Leverandør:</strong> {{ $declaration->vendor_name }}</span>
                @endif
                <span><strong>Versjon:</strong> {{ $declaration->version }}</span>
                <span><strong>Versjonsidentifikasjon:</strong> {{ $declaration->version_identification }}</span>
                @if($declaration->declaration_date)
                    <span><strong>Dato:</strong> {{ $declaration->declaration_date->format('d.m.Y') }}</span>
                @endif
            </div>
        </div>

        <div class="content">
            {!! $content !!}
        </div>

        <div class="footer">
            <p>Dokument generert: {{ now()->format('d.m.Y H:i') }}</p>
        </div>
    </div>
</body>
</html>




