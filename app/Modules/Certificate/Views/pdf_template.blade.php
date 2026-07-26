<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate — {{ $certificate->certificate_number }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background: #fafafa; margin: 0; padding: 40px; }
        .cert-border { border: 10px solid #1e293b; padding: 40px; background: #ffffff; border-radius: 8px; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .logo { font-size: 28px; font-weight: bold; color: #4f46e5; margin-bottom: 20px; }
        .title { font-size: 36px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 2px; }
        .subtitle { font-size: 16px; color: #64748b; margin-top: 5px; }
        .recipient { font-size: 32px; font-weight: 700; color: #1e1b4b; margin: 30px 0 10px; border-bottom: 2px solid #e2e8f0; display: inline-block; padding-bottom: 5px; }
        .course { font-size: 20px; color: #334155; margin-bottom: 30px; }
        .details { margin-top: 40px; display: flex; justify-content: space-between; font-size: 14px; color: #475569; text-align: left; }
        .qr-placeholder { border: 2px dashed #cbd5e1; padding: 10px; font-family: monospace; font-size: 11px; color: #64748b; background: #f8fafc; border-radius: 4px; display: inline-block; }
    </style>
</head>
<body>
    <div class="cert-border">
        <div class="logo">iC.edu Assessment Platform</div>
        <div class="title">Certificate of Achievement</div>
        <div class="subtitle">This is officially awarded to</div>

        <div class="recipient">{{ $certificate->user?->name ?? 'Candidate Name' }}</div>

        <div class="subtitle">for successfully passing the computer-based evaluation:</div>
        <div class="course"><strong>{{ $certificate->attempt?->test?->title ?? 'Evaluation Test' }}</strong></div>

        <div class="qr-placeholder">
            QR CODE VERIFICATION<br>
            Code: {{ $certificate->verification_code }}<br>
            URL: {{ route('public.verify', ['code' => $certificate->verification_code]) }}
        </div>

        <div class="details">
            <div>
                <strong>Certificate ID:</strong> {{ $certificate->certificate_number }}<br>
                <strong>Issue Date:</strong> {{ $certificate->issued_at?->format('F d, Y') }}
            </div>
            <div style="text-align: right;">
                <strong>Status:</strong> {{ strtoupper($certificate->status->value) }}<br>
                <strong>Expires:</strong> {{ $certificate->expires_at?->format('F d, Y') }}
            </div>
        </div>
    </div>
</body>
</html>
