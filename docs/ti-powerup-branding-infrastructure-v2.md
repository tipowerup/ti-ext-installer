# 🚀 TI Powerup - Branding & Infrastructure Guide v2

## 1. Brand Identity

### Logo Concept: "TI" Monogram

**Recommended:** `TI ⚡ Powerup` - Clean wordmark with TI emphasized and ⚡ as visual separator

```
┌─────────────────────────────────┐
│  TI ⚡ Powerup                   │
│  Bold "TI" + lightning + name   │
└─────────────────────────────────┘
```

---

### Color Palette Options

#### Option A: Electric Blue (Tech-Forward)

| Role | Name | Hex | Preview |
|------|------|-----|---------|
| Primary | Electric Blue | `#3B82F6` | 🔵 |
| Primary Dark | Deep Blue | `#2563EB` | 🔵 |
| Accent | Vibrant Orange | `#F97316` | 🟠 |
| Dark | Navy | `#1E293B` | ⬛ |
| Darker | Deep Navy | `#0F172A` | ⬛ |
| Success | Green | `#22C55E` | 🟢 |
| Warning | Amber | `#F59E0B` | 🟡 |
| Error | Red | `#EF4444` | 🔴 |

```css
/* Option A - Tailwind Config */
colors: {
  'ti': {
    50: '#eff6ff',
    100: '#dbeafe',
    200: '#bfdbfe',
    300: '#93c5fd',
    400: '#60a5fa',
    500: '#3b82f6',  /* Primary */
    600: '#2563eb',
    700: '#1d4ed8',
    800: '#1e40af',
    900: '#1e3a8a',
  },
  'ti-accent': '#f97316',
}
```

**Vibe:** Professional, trustworthy, tech-focused. Blue differentiates from TI's orange while accent maintains connection.

---

#### Option B: Emerald Green (Fresh & Growth)

| Role | Name | Hex | Preview |
|------|------|-----|---------|
| Primary | Emerald | `#10B981` | 🟢 |
| Primary Dark | Deep Emerald | `#059669` | 🟢 |
| Accent | Warm Orange | `#F97316` | 🟠 |
| Dark | Slate | `#1E293B` | ⬛ |
| Darker | Deep Slate | `#0F172A` | ⬛ |
| Success | Teal | `#14B8A6` | 🟢 |
| Warning | Amber | `#F59E0B` | 🟡 |
| Error | Rose | `#F43F5E` | 🔴 |

```css
/* Option B - Tailwind Config */
colors: {
  'ti': {
    50: '#ecfdf5',
    100: '#d1fae5',
    200: '#a7f3d0',
    300: '#6ee7b7',
    400: '#34d399',
    500: '#10b981',  /* Primary */
    600: '#059669',
    700: '#047857',
    800: '#065f46',
    900: '#064e3b',
  },
  'ti-accent': '#f97316',
}
```

**Vibe:** Growth, money/success, fresh. Stands out from both TI (orange) and typical blue tech brands.

---

### Dark Mode (Both Options)

| Element | Light | Dark |
|---------|-------|------|
| Background | `#FFFFFF` | `#0F172A` |
| Surface | `#F8FAFC` | `#1E293B` |
| Text Primary | `#1E293B` | `#F8FAFC` |
| Text Secondary | `#64748B` | `#94A3B8` |
| Border | `#E2E8F0` | `#334155` |

---

### Typography & Voice

**Font:** Inter (free, modern, excellent readability)

**Tagline:** "Power up your TastyIgniter"

**Tone:** Friendly, developer-focused, no fluff

---

## 2. Infrastructure Architecture

### Updated Architecture with Cloudflare R2

```
┌─────────────────────────────────────────────────────────────────┐
│                        CLOUDFLARE                               │
│  ┌───────────┐  ┌───────────┐  ┌───────────┐  ┌───────────┐    │
│  │  DNS/CDN  │  │   R2      │  │  DDoS     │  │  SSL/TLS  │    │
│  │  (Free)   │  │  Storage  │  │  Protect  │  │  (Free)   │    │
│  └───────────┘  └───────────┘  └───────────┘  └───────────┘    │
│                       │                                         │
│              Package ZIPs stored here                           │
│              (ZERO egress fees!)                                │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                    HETZNER CLOUD (Germany)                      │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                  Main Server (CPX21)                     │   │
│  │                                                          │   │
│  │  Laravel 12 + Livewire 4                                │   │
│  │  MySQL 8 + Redis                                         │   │
│  │  Nginx                                                   │   │
│  │                                                          │   │
│  │  Optional: Packeton (for Composer users)                │   │
│  │                                                          │   │
│  └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                    GITHUB (Source Storage)                      │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐             │
│  │ Extension 1 │  │ Extension 2 │  │ Theme 1     │  ...        │
│  │ (private)   │  │ (private)   │  │ (private)   │             │
│  └─────────────┘  └─────────────┘  └─────────────┘             │
│                                                                 │
│  Developers can submit via GitHub repo OR direct ZIP upload     │
└─────────────────────────────────────────────────────────────────┘
```

---

### Cloudflare R2 - Why It's Perfect

| Feature | Benefit |
|---------|---------|
| **Zero egress fees** | Unlimited downloads, no bandwidth costs |
| **S3-compatible API** | Use existing tools (Laravel filesystem) |
| **Global CDN** | Fast downloads worldwide |
| **Free tier** | 10GB storage, 10M reads/month |

**R2 Pricing:**

| Tier | Storage | Class A (writes) | Class B (reads) | Egress |
|------|---------|------------------|-----------------|--------|
| **Free** | 10 GB | 1M ops | 10M ops | **$0** |
| **Paid** | $0.015/GB | $4.50/M ops | $0.36/M ops | **$0** |

**Example: 50 packages, 1000 downloads/month**
- Storage: ~500MB = Free tier ✅
- Downloads (reads): 1000 = Free tier ✅
- Egress: **$0 always**

---

### Package Submission & Distribution Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                    DEVELOPER SUBMISSION                          │
│                                                                  │
│   Option A: GitHub Repository                                    │
│   ┌─────────────────────────────────────────────────────────┐   │
│   │ 1. Developer provides GitHub repo URL                    │   │
│   │ 2. System clones repo on approval                       │   │
│   │ 3. Webhook triggers rebuild on new releases             │   │
│   └─────────────────────────────────────────────────────────┘   │
│                                                                  │
│   Option B: Direct ZIP Upload                                    │
│   ┌─────────────────────────────────────────────────────────┐   │
│   │ 1. Developer uploads ZIP via dashboard                  │   │
│   │ 2. System validates structure                           │   │
│   │ 3. Developer uploads new ZIP for updates                │   │
│   └─────────────────────────────────────────────────────────┘   │
│                                                                  │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│                    BUILD PIPELINE                                │
│                                                                  │
│  1. Validate extension.json / Extension.php                     │
│  2. Run composer install --no-dev (bundle dependencies)         │
│  3. Security scan (basic)                                       │
│  4. Create distribution ZIP (with /vendor included)             │
│  5. Generate SHA256 checksum                                    │
│  6. Upload to Cloudflare R2                                     │
│  7. (Optional) Register in Packeton for Composer users          │
│                                                                  │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│                    DISTRIBUTION                                  │
│                                                                  │
│  PRIMARY: TI Powerup Installer Extension (95% of users)         │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ 1. User enters license key in TI admin                   │   │
│  │ 2. Installer calls API to verify license                 │   │
│  │ 3. API returns signed R2 download URL                    │   │
│  │ 4. Installer downloads & extracts to extensions/         │   │
│  │ 5. Runs migrations, clears cache                         │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ALTERNATIVE: Manual Download                                    │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ - User downloads ZIP from dashboard                      │   │
│  │ - Manually uploads via FTP                               │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ALTERNATIVE: Composer (for VPS users)                           │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ - composer require tipowerup/package                     │   │
│  │ - Requires Packeton setup (optional)                     │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

### Laravel + R2 Integration

```php
// config/filesystems.php
'disks' => [
    'r2' => [
        'driver' => 's3',
        'key' => env('CLOUDFLARE_R2_ACCESS_KEY'),
        'secret' => env('CLOUDFLARE_R2_SECRET_KEY'),
        'region' => 'auto',
        'bucket' => env('CLOUDFLARE_R2_BUCKET'),
        'endpoint' => env('CLOUDFLARE_R2_ENDPOINT'),
        'use_path_style_endpoint' => false,
    ],
],

// .env
CLOUDFLARE_R2_ACCESS_KEY=your_access_key
CLOUDFLARE_R2_SECRET_KEY=your_secret_key
CLOUDFLARE_R2_BUCKET=tipowerup-packages
CLOUDFLARE_R2_ENDPOINT=https://your-account-id.r2.cloudflarestorage.com
```

```php
// app/Services/PackageService.php
use Illuminate\Support\Facades\Storage;

class PackageService
{
    public function uploadPackage(string $packageName, string $version, string $zipPath): string
    {
        $r2Path = "packages/{$packageName}/{$version}/{$packageName}-{$version}.zip";
        
        Storage::disk('r2')->put($r2Path, file_get_contents($zipPath));
        
        return $r2Path;
    }
    
    public function getSignedDownloadUrl(string $r2Path, int $expiresMinutes = 30): string
    {
        // Generate time-limited signed URL
        return Storage::disk('r2')->temporaryUrl($r2Path, now()->addMinutes($expiresMinutes));
    }
}
```

---

### Server Requirements & Pricing (Updated)

#### Minimum Setup (Launch)

| Component | Spec | Monthly Cost |
|-----------|------|--------------|
| **Hetzner CPX21** | 3 vCPU, 4GB RAM, 80GB NVMe | €4.85 |
| **Cloudflare R2** | Free tier (10GB, 10M reads) | €0 |
| **Cloudflare CDN/DNS** | Free plan | €0 |
| **Automated Backups** | 20% of server | €0.97 |
| **Domain** | .com | ~€1/mo |
| **GitHub** | Free (unlimited private repos) | €0 |
| **Total** | | **~€7/mo (~$8)** |

#### Growth Setup

| Component | Spec | Monthly Cost |
|-----------|------|--------------|
| **Hetzner CX32** | 4 vCPU, 8GB RAM, 80GB NVMe | €6.80 |
| **Cloudflare R2** | ~50GB storage | ~$0.75 |
| **Cloudflare Pro** | WAF, image optimization | $20 |
| **Backups** | 20% of server | €1.36 |
| **Domain** | .com | ~€1/mo |
| **Email (Resend)** | Transactional | ~$5/mo |
| **Total** | | **~€35/mo (~$38)** |

#### R2 vs Hetzner Volume Comparison

| | Cloudflare R2 | Hetzner Volume |
|--|---------------|----------------|
| Storage cost | $0.015/GB | €0.046/GB |
| Egress | **$0** | €1.19/TB |
| CDN included | ✅ Yes | ❌ No |
| Global distribution | ✅ Yes | ❌ Single region |
| **Winner** | **✅ R2** | |

---

### R2 Bucket Structure

```
tipowerup-packages/
├── extensions/
│   ├── loyalty-points/
│   │   ├── 1.0.0/
│   │   │   ├── loyalty-points-1.0.0.zip
│   │   │   └── checksum.sha256
│   │   └── 1.1.0/
│   │       ├── loyalty-points-1.1.0.zip
│   │       └── checksum.sha256
│   └── referral-system/
│       └── 1.0.0/
│           └── ...
│
├── themes/
│   ├── dashify/
│   │   └── 1.0.0/
│   │       └── dashify-1.0.0.zip
│   └── uberbites/
│       └── ...
│
└── installer/
    └── tipowerup-installer-latest.zip  (free, public)
```

---

### API Endpoints

```
POST /api/v1/verify-license
→ Validates license, returns signed R2 download URL

GET /api/v1/packages/{package}/latest
→ Returns latest version info

POST /api/v1/check-updates
→ Bulk check for updates across installed packages

POST /api/v1/webhook/github
→ Receives GitHub push events, triggers rebuild
```

---

### GitHub Organization Structure

```
github.com/tipowerup/
├── marketplace           # Main Laravel app
├── installer             # Free TI extension (public)
├── docs                  # Documentation (public)
│
├── ext-loyalty-points    # Private - linked by developer
├── ext-referral-system   # Private - linked by developer
│
├── theme-dashify         # Private - linked by developer
└── theme-uberbites       # Private - linked by developer
```

**Note:** Developers can either:
1. Give TI Powerup read access to their GitHub repo
2. Upload ZIP files directly (no GitHub needed)

---

## 3. Cost Comparison Summary

| Phase | Old Plan (Hetzner Volume) | New Plan (R2) | Savings |
|-------|---------------------------|---------------|---------|
| Launch | ~€10/mo | **~€7/mo** | 30% |
| Growth | ~€37/mo | **~€35/mo** | 5% |
| Scale (high downloads) | €90+ (egress costs) | **~€50/mo** | 40%+ |

**Key insight:** R2's zero egress makes it cheaper at every scale, especially when downloads increase.

---

## 4. Quick Reference

### Pick Your Colors

**Option A (Blue):**
```css
--ti-primary: #3B82F6;
--ti-primary-dark: #2563EB;
--ti-accent: #F97316;
```

**Option B (Green):**
```css
--ti-primary: #10B981;
--ti-primary-dark: #059669;
--ti-accent: #F97316;
```

### Key Services

| Service | Provider | Cost |
|---------|----------|------|
| Server | Hetzner CPX21 | €4.85/mo |
| Package Storage | Cloudflare R2 | Free-$5/mo |
| CDN/DNS/SSL | Cloudflare | Free |
| Source Code | GitHub | Free |
| Domain | Any registrar | ~€12/yr |

### Installation Priority

1. **TI Powerup Installer** (95%) - One-click from TI admin
2. **Manual ZIP** (4%) - Download & FTP upload
3. **Composer** (1%) - For advanced VPS users

### Package Submission

Developers choose:
- **GitHub:** Link repo, auto-updates on release
- **ZIP Upload:** Manual upload per version
