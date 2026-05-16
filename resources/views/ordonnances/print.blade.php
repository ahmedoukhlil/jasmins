<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ordonnance — {{ $ordonnance->refOrd ?? '' }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', Arial, sans-serif;
            font-size: 12px;
            color: #1f2937;
            background: #f3f4f6;
            line-height: 1.5;
        }

        /* ── Print controls ── */
        .print-controls {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            padding: 12px 20px;
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
        }
        .print-controls select,
        .print-controls button {
            padding: 7px 14px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 13px;
            font-family: inherit;
            cursor: pointer;
            background: #fff;
            color: #374151;
        }
        .print-controls button { background: #1e3a8a; color: #fff; border: none; font-weight: 600; }
        .print-controls .btn-secondary { background: #6b7280; }
        .print-controls button:hover { filter: brightness(1.1); }

        /* ── Page ── */
        .page {
            width: 210mm;
            height: 297mm;
            margin: 24px auto;
            background: #fff;
            padding: 12mm 15mm 14mm 15mm;
            position: relative;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* ── Header ── */
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 12px;
            border-bottom: 2px solid #1e3a8a;
            margin-bottom: 16px;
        }
        .header-fr { flex: 1; font-size: 10px; line-height: 1.7; color: #374151; }
        .header-fr .cabinet-name { font-size: 12.5px; font-weight: 700; color: #1e3a8a; }
        .header-fr .doctor-name { font-size: 11px; font-weight: 600; }
        .header-fr .specialty { color: #6b7280; font-size: 9.5px; }
        .header-logo { flex: 0 0 auto; text-align: center; padding: 0 14px; }
        .header-logo img { max-height: 75px; max-width: 130px; object-fit: contain; }
        .header-ar { flex: 1; font-size: 10px; line-height: 1.7; color: #374151; text-align: right; direction: rtl; }
        .header-ar .cabinet-name { font-size: 12.5px; font-weight: 700; color: #1e3a8a; }
        .header-ar .doctor-name { font-size: 11px; font-weight: 600; }
        .header-ar .specialty { color: #6b7280; font-size: 9.5px; }

        /* ── Patient info ── */
        .patient-section {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 14px;
            font-size: 11px;
        }
        .patient-grid {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }
        .patient-fields { flex: 1; }
        .patient-row {
            display: flex;
            gap: 6px;
            margin-bottom: 5px;
            align-items: baseline;
        }
        .patient-label { font-weight: 600; color: #64748b; min-width: 100px; font-size: 10.5px; }
        .patient-value {
            flex: 1;
            color: #1e293b;
            font-weight: 500;
            border-bottom: 1px dotted #94a3b8;
            padding-bottom: 1px;
            min-width: 120px;
        }
        .patient-date { text-align: right; font-size: 11px; font-weight: 600; color: #374151; }

        /* ── Title ── */
        .ord-title {
            text-align: center;
            margin: 16px 0 20px 0;
        }
        .ord-title-ar {
            font-size: 18px;
            font-weight: 700;
            color: #1e3a8a;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .ord-title-fr {
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 6px;
            color: #1f2937;
            text-transform: uppercase;
        }
        .ord-title-line {
            width: 60px;
            height: 2px;
            background: #1e3a8a;
            margin: 6px auto 0;
        }

        /* ── Ordonnance body ── */
        .ord-body {
            flex: 1;
            min-height: 380px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 14px 20px;
            font-size: 12px;
            line-height: 2;
            position: relative;
        }

        .medication-item {
            margin-bottom: 14px;
            page-break-inside: avoid;
        }
        .medication-num {
            display: inline-block;
            width: 22px;
            height: 22px;
            background: #1e3a8a;
            color: #fff;
            border-radius: 50%;
            text-align: center;
            line-height: 22px;
            font-size: 10px;
            font-weight: 700;
            margin-right: 6px;
            flex-shrink: 0;
        }
        .medication-name {
            font-weight: 600;
            font-size: 12.5px;
            color: #1e293b;
            display: flex;
            align-items: center;
        }
        .medication-dosage {
            margin-left: 28px;
            font-size: 11.5px;
            color: #4b5563;
            font-style: italic;
            line-height: 1.6;
        }

        /* ── Footer ── */
        .ord-footer {
            margin-top: 14px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .footer-note {
            font-size: 9px;
            color: #9ca3af;
            line-height: 1.6;
        }
        .footer-signature {
            text-align: center;
            min-width: 140px;
        }
        .footer-signature .sig-label { font-size: 10px; font-weight: 600; color: #64748b; margin-bottom: 28px; }
        .footer-signature .sig-line { border-top: 1px solid #1e3a8a; padding-top: 4px; font-size: 10px; font-style: italic; color: #374151; }

        /* ── Print ── */
        @media print {
            body { background: #fff; }
            .print-controls { display: none !important; }
            .page {
                margin: 0;
                padding: 10mm 13mm 12mm 13mm;
                box-shadow: none;
                overflow: hidden;
            }
            .page.a4 {
                width: 210mm;
                height: 297mm;
            }
            .page.a5 {
                width: 148mm;
                height: 210mm;
            }
            .medication-item { page-break-inside: avoid; }
        }

        /* A4 print @page rule */
        body.format-a4 {
            --page-width: 210mm;
            --page-height: 297mm;
        }
        body.format-a5 {
            --page-width: 148mm;
            --page-height: 210mm;
        }

        @media screen {
            .page { box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
        }

        /* A5 */
        .page.a5 {
            width: 148mm;
            height: 210mm;
            padding: 8mm 10mm 10mm 10mm;
        }
        .page.a5 .ord-title-ar { font-size: 14px; }
        .page.a5 .ord-title-fr { font-size: 12px; letter-spacing: 4px; }
        .page.a5 .ord-body { min-height: 200px; font-size: 11px; }
        .page.a5 .medication-name { font-size: 11px; }
        .page.a5 .header-fr,
        .page.a5 .header-ar { font-size: 9px; }
        .page.a5 .header-fr .cabinet-name,
        .page.a5 .header-ar .cabinet-name { font-size: 11px; }
        .page.a5 .patient-section { padding: 7px 10px; margin-bottom: 10px; }
        .page.a5 .doc-header { margin-bottom: 10px; padding-bottom: 8px; }
        .page.a5 .ord-title { margin: 10px 0 12px 0; }
        .page.a5 .footer-note { font-size: 8px; }
    </style>

    <style id="page-size-style">
        @media print { @page { size: A4 portrait; margin: 0; } }
    </style>
</head>
<body>

<div class="print-controls">
    <select id="pageFormat" onchange="updatePageFormat()">
        <option value="A4">Format A4</option>
        <option value="A5">Format A5</option>
    </select>
    <button onclick="window.print()">⎙ Imprimer</button>
    <button class="btn-secondary" onclick="window.location.href='{{ route('ordonnance.blank') }}'">Ordonnance vierge</button>
</div>

<div class="page a4" id="documentPage">

    @php
        use App\Models\Infocabinet;
        $cab = Infocabinet::first();
        $logoBase64 = null;
        if ($cab && $cab->logo && file_exists(public_path($cab->logo))) {
            try {
                $ext = strtolower(pathinfo($cab->logo, PATHINFO_EXTENSION));
                $mime = in_array($ext, ['jpg','jpeg']) ? 'image/jpeg' : ($ext === 'svg' ? 'image/svg+xml' : 'image/png');
                $logoBase64 = 'data:'.$mime.';base64,'.base64_encode(file_get_contents(public_path($cab->logo)));
            } catch (\Exception $e) {}
        }
        $isBlank = !isset($ordonnance->patient) || empty($ordonnance->refOrd);
        $age = null;
        if (!$isBlank && isset($ordonnance->patient) && $ordonnance->patient->DtNaissance) {
            try { $age = \Carbon\Carbon::parse($ordonnance->patient->DtNaissance)->age; } catch (\Exception $e) {}
        }
    @endphp

    {{-- ── Header ── --}}
    <div class="doc-header">
        <div class="header-fr">
            @if($cab && $cab->NomCabFr)<div class="cabinet-name">{{ $cab->NomCabFr }}</div>@endif
            @if($cab && $cab->DrFr)<div class="doctor-name">{{ $cab->DrFr }}</div>@endif
            @if($cab && $cab->Specialite1Fr)<div class="specialty">{{ $cab->Specialite1Fr }}</div>@endif
            @if($cab && $cab->Specialite2fr)<div class="specialty">{{ $cab->Specialite2fr }}</div>@endif
            @if($cab && $cab->AdresseFr1)<div>{{ $cab->AdresseFr1 }}</div>@endif
            @if($cab && $cab->AdresseFr2)<div>{{ $cab->AdresseFr2 }}</div>@endif
            @if($cab && $cab->ContactFR)<div>{{ $cab->ContactFR }}</div>@endif
        </div>
        <div class="header-logo">
            @if($logoBase64)<img src="{{ $logoBase64 }}" alt="Logo">@endif
        </div>
        <div class="header-ar">
            @if($cab && $cab->NomCabAr)<div class="cabinet-name">{{ $cab->NomCabAr }}</div>@endif
            @if($cab && $cab->DrAr)<div class="doctor-name">{{ $cab->DrAr }}</div>@endif
            @if($cab && $cab->Specialite1Ar)<div class="specialty">{{ $cab->Specialite1Ar }}</div>@endif
            @if($cab && $cab->Specialite2Ar)<div class="specialty">{{ $cab->Specialite2Ar }}</div>@endif
            @if($cab && $cab->AdresseL1AR)<div>{{ $cab->AdresseL1AR }}</div>@endif
            @if($cab && $cab->AdresseL2AR)<div>{{ $cab->AdresseL2AR }}</div>@endif
            @if($cab && $cab->ContactAR)<div>{{ $cab->ContactAR }}</div>@endif
        </div>
    </div>

    {{-- ── Patient info ── --}}
    <div class="patient-section">
        <div class="patient-grid">
            <div class="patient-fields">
                <div class="patient-row">
                    <span class="patient-label">Nom et Prénom :</span>
                    <span class="patient-value">{{ !$isBlank && $ordonnance->patient->NomContact ? $ordonnance->patient->NomContact : '' }}</span>
                    <span class="patient-label" style="text-align:right; direction:rtl; margin-left:12px;">الاسم و اللقب</span>
                </div>
                <div class="patient-row">
                    <span class="patient-label">Âge :</span>
                    <span class="patient-value" style="max-width:80px">{{ $age ? $age . ' ans' : '' }}</span>
                    <span class="patient-label" style="margin-left:20px;">Poids :</span>
                    <span class="patient-value" style="max-width:80px"></span>
                    <span class="patient-label" style="text-align:right; direction:rtl; margin-left:12px;">العمر / الوزن</span>
                </div>
            </div>
            <div class="patient-date">
                {{ !$isBlank && isset($ordonnance->dtPrescript) ? \Carbon\Carbon::parse($ordonnance->dtPrescript)->format('d/m/Y') : '' }}
            </div>
        </div>
    </div>

    {{-- ── Title ── --}}
    <div class="ord-title">
        <div class="ord-title-ar">وصفة طبية</div>
        <div class="ord-title-fr">Ordonnance</div>
        <div class="ord-title-line"></div>
    </div>

    {{-- ── Body ── --}}
    <div class="ord-body">
        @if(!$isBlank && isset($ordonnance->ordonnances) && count($ordonnance->ordonnances) > 0)
            @foreach($ordonnance->ordonnances as $index => $ligne)
                <div class="medication-item">
                    <div class="medication-name">
                        <span class="medication-num">{{ $index + 1 }}</span>
                        {{ $ligne->Libelle }}
                    </div>
                    @if($ligne->Utilisation)
                        <div class="medication-dosage">{{ $ligne->Utilisation }}</div>
                    @endif
                </div>
            @endforeach
        @endif
    </div>

    {{-- ── Footer ── --}}
    <div class="ord-footer">
        <div class="footer-note">
            <div>الرجاء إحضار الوصفة عند الاستشارة القادمة</div>
            <div>Prière de rapporter l'ordonnance à la prochaine consultation</div>
        </div>
        <div class="footer-signature">
            <div class="sig-label">Signature &amp; Cachet</div>
            <div class="sig-line">
                @if(!$isBlank && isset($ordonnance->prescripteur))Dr {{ $ordonnance->prescripteur->Nom ?? '' }}@endif
            </div>
        </div>
    </div>

</div>

<script>
function updatePageFormat() {
    const format = document.getElementById('pageFormat').value;
    const isA5 = format === 'A5';
    const page = document.getElementById('documentPage');
    page.classList.toggle('a4', !isA5);
    page.classList.toggle('a5', isA5);

    // Mettre à jour la règle @page pour l'impression
    const styleEl = document.getElementById('page-size-style');
    if (isA5) {
        styleEl.textContent = '@media print { @page { size: A5 portrait; margin: 0; } }';
    } else {
        styleEl.textContent = '@media print { @page { size: A4 portrait; margin: 0; } }';
    }
}

const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('autoprint') === '1') {
    window.onload = () => setTimeout(() => window.print(), 500);
}
</script>
</body>
</html>
