# ⚙️ Spesifikasi & Algoritma Engine Penjadwalan Otomatis (SiJaProt)
**Role:** Senior Fullstack Architect Reference  
**File Reference:** `App\Services\Scheduling\SchedulingEngine.php`  
**Tanggal:** 20 Oktober 2026

---

## 1. Alur Logika Eksekusi (Pipeline)

```
[Input Kegiatan Baru]
        │
        ▼
[1. FILTER: Protokol Aktif Saja]
        │
        ▼
[2. ELIMINASI HARD RULES]
  ├── H2: Cek Overlap Waktu Langsung
  ├── H1: Cek Gap Waktu Hybrid (Dalam Kota: 24 Jam, Luar Kota: H+1)
  └── H3: Cek Gap Full Weekend (Sabtu/Minggu sebelumnya)
        │
        ├─── [Kandidat Lolos >= N Dibutuhkan?] ───┐
        │                                        │
        ▼ (TIDAK)                                ▼ (YA)
[3. RELAKSASI BERTAHAP]                [4. SCORING SOFT RULES]
  ├── Relaksasi S4 & S3                  ├── Skor Kebutuhan Min 1x/Bulan
  ├── Relaksasi S1 & S2                  ├── Skor Keseimbangan Dibayar vs Free
  └── Relaksasi H1 Luar Kota             ├── Skor Keseimbangan Weekend
        │                                └── Skor Rotasi Istirahat Terlama
        │                                        │
        └────────────────────────────────────────┤
                                                 ▼
                                        [5. RANKING & PICK N]
                                                 │
                                                 ▼
                                        [6. SIMPAN SNAPSHOT & AUDIT]
```

---

## 2. Definisi Matematis Hard Rules

### 2.1 H1: Gap Waktu Hybrid
Diberikan kegiatan baru $K_{new}$ dengan rentang waktu $[Start_{new}, End_{new}]$:
Untuk setiap kandidat $p$ dengan kegiatan terakhir $K_{last}$ $[Start_{last}, End_{last}]$:

$$\text{Valid}_{H1} = 
\begin{cases} 
False, & \text{jika } Start_{new} < End_{last} \text{ (Overlap)} \\
Start_{new} \ge End_{last} + 24\text{ jam}, & \text{jika } K_{last}.\text{location} = \text{'dalam\_kota'} \\
Start_{new}.\text{date} \ge End_{last}.\text{date} + 2\text{ hari}, & \text{jika } K_{last}.\text{location} = \text{'luar\_kota'} \text{ (H+1 Gap)}
\end{cases}$$

### 2.2 H3: Gap Full Weekend
Jika $K_{new}$ jatuh pada hari **Sabtu**, **Minggu**, atau **Hari Libur Nasional**:
Kandidat $p$ **DIDISKUALIFIKASI** jika $p$ bertugas pada Sabtu atau Minggu pada akhir pekan sebelumnya (Rentang $Day(K_{new}) - 7 \text{ s.d. } Day(K_{new}) - 1$).

---

## 3. Rumus Scoring Soft Rules (Distribusi Keseimbangan)

Kandidat yang lolos Hard Rules akan dihitung skor kelayakannya. **Skor lebih tinggi = Lebih diprioritaskan**.

$$\text{FinalScore}(p) = W_1 \cdot S_{\text{min\_month}} + W_2 \cdot S_{\text{balance\_pay}} + W_3 \cdot S_{\text{balance\_weekend}} + W_4 \cdot S_{\text{balance\_trip}} + W_5 \cdot S_{\text{idle\_time}}$$

### Tabel Bobot ($W_i$):
| Komponen | Bobot ($W$) | Formula | Penjelasan |
| :--- | :---: | :--- | :--- |
| **$S_{\text{min\_month}}$** | **50.0** | $1 \text{ jika } TotalTugasBulanIni(p) == 0 \text{ else } 0$ | Menjamin semua wajib minimal 1x per bulan |
| **$S_{\text{balance\_pay}}$** | **15.0** | $\max(0, \text{AvgPaid}_{\text{team}} - p.\text{paid\_count})$ | Pegawai yang paling sedikit dapat tugas dibayar diprioritaskan |
| **$S_{\text{balance\_weekend}}$** | **15.0** | $\max(0, \text{AvgWeekend}_{\text{team}} - p.\text{weekend\_count})$ | Menyeimbangkan tugas luar hari kerja |
| **$S_{\text{balance\_trip}}$** | **10.0** | $\max(0, \text{AvgTrip}_{\text{team}} - p.\text{trip\_count})$ | Menyeimbangkan frekuensi dinas luar kota |
| **$S_{\text{idle\_time}}$** | **1.0** | $\min(30, \text{DaysSinceLastTask}(p))$ | Makin lama istirahat, makin tinggi prioritas rotasi |

---

## 4. Implementasi Pseudocode PHP (Laravel Service)

```php
namespace App\Services\Scheduling;

use App\Models\Kegiatan;
use App\Models\Protokol;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SchedulingEngine
{
    protected GapRuleChecker $gapChecker;
    protected DistributionScorer $scorer;

    public function __construct(GapRuleChecker $gapChecker, DistributionScorer $scorer)
    {
        $this->gapChecker = $gapChecker;
        $this->scorer = $scorer;
    }

    public function assignProtokol(Kegiatan $kegiatan): Collection
    {
        $requiredCount = $kegiatan->jumlah_protokol;
        $activeProtokols = Protokol::where('status', 'active')->get();

        // 1. Filter Hard Rules
        $eligible = $activeProtokols->filter(function ($protokol) use ($kegiatan) {
            return $this->gapChecker->passesAllHardRules($protokol, $kegiatan);
        });

        // 2. Relaksasi jika kandidat kurang
        if ($eligible->count() < $requiredCount) {
            $eligible = $this->applyRelaxationPipeline($activeProtokols, $kegiatan, $requiredCount);
        }

        // 3. Scoring Soft Rules
        $scored = $eligible->map(function ($protokol) use ($kegiatan) {
            $scoreData = $this->scorer->calculateScore($protokol, $kegiatan);
            return [
                'protokol' => $protokol,
                'score' => $scoreData['score'],
                'reason' => $scoreData['reason'],
            ];
        });

        // 4. Sort Descending by Score & Take Top N
        return $scored->sortByDesc('score')->take($requiredCount);
    }

    protected function applyRelaxationPipeline(Collection $pool, Kegiatan $kegiatan, int $needed): Collection
    {
        // Tahap 1: Relaksasi rotasi & S3
        // Tahap 2: Relaksasi gap jam luar kota (jika darurat)
        return $this->gapChecker->filterWithRelaxedRules($pool, $kegiatan, $needed);
    }
}
```
