# TrustStack — Forensic Certificate Verification with Automated Royalty Routing

> **OPay Innovation Challenge 2026**

TrustStack is a B2B forensic verification platform that transforms how institutional trust is managed. When an HR department needs to verify a candidate's academic credentials, TrustStack runs AI-powered Error Level Analysis (ELA) and OCR forensic scans on the uploaded certificate — then **automatically routes a royalty payment to the issuing university's bank account** via OPay's Transfer API.

It turns fraud prevention into a revenue stream for academic institutions.

---

## The Problem

Certificate fraud is rampant — and current verification methods are slow, manual, and expensive. HR teams have no way to instantly detect digitally altered names, dates, or grades on academic certificates. Universities, meanwhile, have zero visibility into how their credentials are being used and receive nothing when employers verify them.

## The Innovation

TrustStack solves both problems with a single payment-gated workflow:

1. **HR uploads a certificate** → pays ₦5,000 via OPay Checkout
2. **AI engine runs forensic analysis** → ELA heatmap + OCR + alignment detection
3. **₦1,000 royalty auto-routes** → transferred to the university's bank via OPay Transfer API
4. **Verdict rendered** → PASS or FAIL with visual evidence

The university earns passive income. The employer gets instant trust verification. OPay & OPay power the financial pipeline.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        TrustStack Platform                        │
├──────────────────┬──────────────────┬───────────────────────────┤
│   Laravel 13     │   Python Engine  │      OPay API Suite      │
│                  │                  │                           │
│  • Auth + RBAC   │  • ELA Analysis  │  • Initiate Payment       │
│  • Payment Flow  │  • OCR (Tesser.) │  • Verify Transaction     │
│  • Webhook Ctrl  │  • Alignment     │  • Transfer API (Royalty) │
│  • Dashboard     │  • Composite     │  • Sub-Merchant Register  │
│  • Blade Views   │    Scoring       │  • Account Lookup         │
└──────────────────┴──────────────────┴───────────────────────────┘
```

### OPay API Integration (4 Endpoints)

| API | Purpose | Endpoint |
|-----|---------|----------|
| **Initiate Payment** | Collect ₦5,000 verification fee via inline modal | `POST /transaction/initiate` |
| **Verify Transaction** | Server-side confirmation before releasing results | `GET /transaction/verify/{ref}` |
| **Transfer API** | Route ₦1,000 royalty to university bank account | `POST /payout/transfer` |
| **Sub-Merchant** | Register universities as aggregator sub-merchants | `POST /merchant/create-sub-users` |

### Financial Flow

```
₦5,000 Payment
    │
    ├── ₦4,000 → TrustStack (platform fee)
    │
    └── ₦1,000 → University Bank Account
                  (via OPay Transfer API)
                  Transfer ref: {MERCHANT_ID}_ROYALTY_{ledger_id}_{random}
```

### Forensic Engine (Four-Layer Analysis)

| Layer | Technology | Weight | What It Detects |
|-------|-----------|--------|-----------------|
| **Visual (ELA)** | Pillow, NumPy | 35% | Compression inconsistencies from pixel edits — name/date splicing shows as red hotspots on the heatmap |
| **Textual (OCR)** | Tesseract 5.3 | 30% | Candidate name presence, bounding box alignment anomalies, ELA–text region cross-referencing |
| **Noise Consistency** | Laplacian Filter | 20% | Local noise variance to detect spliced regions from different camera sensors or compression histories |
| **Edge Coherence** | Sobel Operators | 15% | Edge density anomalies that indicate cropped and pasted text blocks |

The four scores are combined into a **composite score** (0–100). The engine outputs a Confidence Level (HIGH/MEDIUM/LOW) via multi-layer agreement. Scores ≥ 40 trigger a **FAIL** verdict.

---

## Tech Stack

| Component | Technology | Version |
|-----------|-----------|---------|
| Backend | Laravel (PHP) | 13.8 |
| Frontend | Blade + Tailwind CSS (OPay Brand Identity) | 4.x |
| AI Engine | Python + Pillow + NumPy | 3.12 |
| OCR | Tesseract OCR + pytesseract | 5.3.4 |
| Payments | OPay API (Sandbox) | v1 |
| Database | SQLite | 3.x |
| Build | Vite | 6.x |

---

## Project Structure

```
Hackathon/
├── web/                              # Laravel application
│   ├── app/
│   │   ├── Http/Controllers/
│   │   │   ├── PaymentController.php       # Payment initiation + callback
│   │   │   ├── OPayWebhookController.php  # HMAC-validated webhook handler
│   │   │   ├── VerificationController.php  # Upload + results
│   │   │   └── DashboardController.php     # Stats + listing
│   │   ├── Models/
│   │   │   ├── Verification.php            # Core entity
│   │   │   ├── Payment.php                 # Transaction tracking
│   │   │   ├── Institution.php             # University registry
│   │   │   └── RoyaltyLedgerEntry.php      # Royalty transfer audit trail
│   │   └── Services/
│   │       ├── OPayPaymentService.php      # 6 methods across 4 OPay APIs
│   │       ├── ForensicAnalysisService.php  # Orchestrates Python engine
│   │       └── FeeSplit.php                 # Static fee allocation logic
│   ├── resources/views/
│   │   ├── auth/login.blade.php             # Animated login page
│   │   ├── dashboard.blade.php              # Stats + verification table
│   │   ├── verifications/
│   │   │   ├── create.blade.php             # Drag-and-drop upload
│   │   │   └── show.blade.php               # Verdict + heatmap + score gauge
│   │   └── payments/show.blade.php          # OPay inline modal + fee breakdown
│   └── database/
│       ├── migrations/                      # 6 migration files
│       └── seeders/DatabaseSeeder.php       # 4 universities + OPay sub-merchant registration
│
├── python/                            # Forensic AI engine
│   ├── truststack_forensics.py          # Two-layer analysis (ELA + OCR)
│   ├── generate_test_certs.py         # Test dataset generator (80 certificates)
│   ├── requirements.txt
│   └── .venv/                         # Python virtual environment
│
└── test_images/                       # Generated test dataset
    ├── manifest.json                  # Maps each file to candidate name + expected verdict
    ├── unilag/{clean,forged}/         # 10 + 10 University of Lagos certificates
    ├── ui/{clean,forged}/             # 10 + 10 University of Ibadan certificates
    ├── cu/{clean,forged}/             # 10 + 10 Covenant University certificates
    └── abu/{clean,forged}/            # 10 + 10 Ahmadu Bello University certificates
```

---

## Setup & Installation

### Prerequisites

- PHP 8.2+ with `sqlite3`, `gd`, `mbstring` extensions
- Composer 2.x
- Node.js 18+
- Python 3.10+
- Tesseract OCR 5.x

### 1. Clone & Install

```bash
git clone <repo-url> && cd Hackathon

# Laravel dependencies
cd web
composer install
npm install
cp .env.example .env    # then configure OPay keys (see below)
php artisan key:generate

# Python dependencies
cd ../python
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt

# System dependency
sudo apt install tesseract-ocr    # Ubuntu/Debian
```

### 2. Configure Environment

Edit `web/.env`:

```env
OPAY_BASE_URL=https://sandbox-api-d.opayco.com
OPAY_PUBLIC_KEY=sandbox_pk_...
OPAY_SECRET_KEY=sandbox_sk_...
OPAY_MERCHANT_ID=SBL7D5D4VL
TRUESEAL_PYTHON_BIN=/absolute/path/to/python/.venv/bin/python3
```

### 3. Database & Seed

```bash
cd web
php artisan migrate --seed
```

This creates the SQLite database, seeds 4 Nigerian universities with bank details, and **registers each as a OPay sub-merchant** via the API.

### 4. Build & Run

```bash
npm run build          # Compile Tailwind + Vite assets
php artisan serve      # Start at http://localhost:8000
```

### 5. Generate Test Certificates

```bash
cd python
source .venv/bin/activate
python3 generate_test_certs.py --output-dir ../test_images
```

Generates **80 certificates** (40 clean + 40 forged) across all 4 universities with a `manifest.json` for reference.

---

## Demo Walkthrough

### Login

| Field | Value |
|-------|-------|
| Email | `hr@truststack.test` |
| Password | `password` |

### Test Case 1: Clean Certificate (Expected: PASS)

1. Click **"New scan"**
2. Select **University of Lagos** as the issuing university
3. Enter candidate name: `Emeka Oluwaseun Oni`
4. Upload `test_images/unilag/clean/unilag_clean_01.jpg`
5. Click **"Continue to payment"** → complete OPay checkout
6. View result: **PASS** with low score, uniform heatmap

### Test Case 2: Forged Certificate (Expected: FAIL)

1. Click **"New scan"**
2. Select **University of Lagos** as the issuing university
3. Enter candidate name: `Adebayo Adaeze Eze`
4. Upload `test_images/unilag/forged/unilag_forged_01.jpg`
5. Click **"Continue to payment"** → complete OPay checkout
6. View result: **FAIL** with high score, red hotspots on name/date regions

### What to Look For

- **ELA Heatmap**: Forged certificates show bright red regions where the name and year were digitally spliced — the double-JPEG compression creates a visible signature
- **Four-Layer Breakdown**: 4 color-coded progress bars showing per-layer scores (ELA, OCR, Noise, Edge) with layer tags and confidence badges
- **Score Gauge**: Animated SVG ring shows the composite score (0–100)
- **Alignment Anomalies**: OCR detects words with abnormal vertical positioning
- **Royalty Transfer**: After payment, the dashboard shows the transfer status (green=success, cyan=queued, red=failed) and reference for the ₦1,000 routed to the university

---

## Webhook Integration

TrustStack listens for OPay payment webhooks at `POST /payments/webhook`.

**Security**: Every incoming webhook is validated with HMAC SHA-512 signature verification using the OPay secret key. The signature is read from the `x-opay-encrypted-body` header.

**Flow on `charge_successful`**:
1. Validate HMAC signature
2. Look up payment by `transaction_ref`
3. Mark payment as `paid`
4. Create royalty ledger entry
5. Trigger forensic scan
6. Initiate royalty transfer via OPay Transfer API

To configure in OPay Dashboard:
- Set webhook URL to `https://yourdomain.com/payments/webhook`

---

## Royalty Ledger

Every payment creates an auditable `RoyaltyLedgerEntry` that tracks:

| Field | Description |
|-------|-------------|
| `amount_kobo` | ₦1,000 (100000 kobo) per verification |
| `opay_reference` | Original payment transaction reference |
| `transfer_reference` | OPay Transfer API reference (`{MERCHANT_ID}_ROYALTY_{id}_{random}`) |
| `transfer_status` | `recorded` → `initiated` → `success` |
| `transfer_response` | Full OPay Transfer API JSON response |

This creates a **complete audit trail** proving that every royalty payment was actually transferred to the university's bank account.

---


## Sandbox Constraints

| Constraint | Detail |
|-----------|--------|
| Transfer API banks | Sandbox transfers are limited to **GTBank (000013)** — demo universities use this code |
| Transfer API access | Sandbox merchants are not profiled for Payouts (returns 400). Transfers are marked as **queued** (cyan) instead of failed. |
| Payment amounts | Sandbox accepts test card numbers from OPay documentation |
| Sub-merchant registration | All 4 universities are auto-registered during `db:seed` |
| Webhook testing | Use [ngrok](https://ngrok.com) or [Hookdeck](https://hookdeck.com) to expose localhost |

---


## License

MIT
