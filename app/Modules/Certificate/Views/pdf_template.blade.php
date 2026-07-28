<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate — {{ $certificate->certificate_number }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background: #f8fafc; margin: 0; padding: 40px; }
        .cert-border { border: 12px solid #1e1b4b; padding: 50px; background: #ffffff; border-radius: 12px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.12); position: relative; }
        .logo { font-size: 26px; font-weight: 800; color: #4f46e5; letter-spacing: 1px; margin-bottom: 20px; }
        .title { font-size: 38px; font-weight: 900; color: #0f172a; text-transform: uppercase; letter-spacing: 3px; }
        .subtitle { font-size: 15px; color: #64748b; margin-top: 6px; font-style: italic; }
        .recipient { font-size: 34px; font-weight: 800; color: #1e1b4b; margin: 30px 0 10px; border-bottom: 3px solid #6366f1; display: inline-block; padding-bottom: 8px; }
        .course { font-size: 22px; color: #334155; margin-bottom: 35px; margin-top: 10px; }
        .qr-box { border: 2px solid #6366f1; padding: 16px 24px; background: #eef2ff; border-radius: 10px; display: inline-block; text-align: center; margin-bottom: 30px; }
        .qr-title { font-size: 11px; font-weight: 800; color: #4338ca; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        .details { margin-top: 30px; display: flex; justify-content: space-between; font-size: 13px; color: #475569; text-align: left; border-t: 1px solid #e2e8f0; pt-20px; }
    </style>
</head>
<body>
    <div class="cert-border">
        <div class="logo">🎓 iC.edu Assessment Platform</div>
        <div class="title">Certificate of Achievement</div>
        <div class="subtitle">This official certificate is proudly presented to</div>

        <div class="recipient">{{ $certificate->user?->name ?? 'Candidate Name' }}</div>

        <div class="subtitle">for successfully demonstrating mastery and passing the examination:</div>
        <div class="course"><strong>{{ $certificate->attempt?->test?->title ?? 'Evaluation Test' }}</strong></div>

        <div class="qr-box">
            <div class="qr-title">Official Verification QR Code</div>
            <div style="margin-bottom: 8px;">
                {!! $qrSvg ?? '' !!}
            </div>
            <div style="font-family: monospace; font-size: 13px; font-weight: bold; color: #1e1b4b;">
                VERIFICATION CODE: {{ $certificate->verification_code }}
            </div>
            <div style="font-size: 10px; color: #475569; margin-top: 4px;">
                Scan QR or visit: {{ $verifyUrl ?? route('public.verify.code', $certificate->verification_code) }}
            </div>
        </div>

        <div class="details" style="display: flex; justify-content: space-between; font-size: 13px; color: #475569; border-top: 1px solid #e2e8f0; padding-top: 20px;">
            <div style="text-align: left;">
                <strong>Certificate Number:</strong> #{{ $certificate->certificate_number }}<br>
                <strong>Date of Issuance:</strong> {{ $certificate->issued_at?->format('F d, Y') }}
            </div>
            <div style="text-align: right;">
                <strong>Authenticity Status:</strong> <span style="color: #16a34a; font-weight: bold;">VALID &amp; AUTHENTIC</span><br>
                <strong>Expiration Date:</strong> {{ $certificate->expires_at?->format('F d, Y') ?? 'No Expiration' }}
            </div>
        </div>
    </div>
</body>
</html>
