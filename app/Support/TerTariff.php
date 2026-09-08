<?php

namespace App\Support;

/**
 * Tarif Efektif Rata-rata (TER) bulanan PPh 21 — PP 58/2023.
 *
 * Tabel ini adalah **data regulasi**, bukan kebijakan perusahaan: nilainya
 * ditranskrip dari Lampiran PP 58/2023 dan hanya boleh berubah bila
 * peraturannya berubah. Sebelum dipakai menghitung gaji sungguhan, cocokkan
 * sekali dengan lampiran resminya — salah satu angka batas saja sudah cukup
 * untuk membuat potongan pajak seluruh karyawan pada band itu meleset.
 *
 * Cakupan: TER bulanan untuk masa pajak Januari–November. Masa Desember
 * memakai perhitungan tahunan Pasal 17 dikurangi PPh yang sudah dipotong —
 * itu belum ditangani di sini (lihat IMPLEMENTATION_STATUS.md §4).
 */
class TerTariff
{
    public const CATEGORY_A = 'A';
    public const CATEGORY_B = 'B';
    public const CATEGORY_C = 'C';

    public const DEFAULT_PTKP = 'TK/0';

    /**
     * Pemetaan status PTKP ke kategori TER (PP 58/2023 Pasal 3).
     *
     * A → PTKP 54.000.000 & 58.500.000
     * B → PTKP 63.000.000 & 67.500.000
     * C → PTKP 72.000.000
     *
     * @var array<string, string>
     */
    public const PTKP_CATEGORY = [
        'TK/0' => self::CATEGORY_A,
        'TK/1' => self::CATEGORY_A,
        'K/0' => self::CATEGORY_A,
        'TK/2' => self::CATEGORY_B,
        'TK/3' => self::CATEGORY_B,
        'K/1' => self::CATEGORY_B,
        'K/2' => self::CATEGORY_B,
        'K/3' => self::CATEGORY_C,
    ];

    /**
     * Keterangan status PTKP untuk dropdown HR dan slip gaji.
     *
     * @var array<string, string>
     */
    public const PTKP_LABEL = [
        'TK/0' => 'TK/0 — Tidak kawin, tanpa tanggungan',
        'TK/1' => 'TK/1 — Tidak kawin, 1 tanggungan',
        'TK/2' => 'TK/2 — Tidak kawin, 2 tanggungan',
        'TK/3' => 'TK/3 — Tidak kawin, 3 tanggungan',
        'K/0' => 'K/0 — Kawin, tanpa tanggungan',
        'K/1' => 'K/1 — Kawin, 1 tanggungan',
        'K/2' => 'K/2 — Kawin, 2 tanggungan',
        'K/3' => 'K/3 — Kawin, 3 tanggungan',
    ];

    /**
     * Nilai PTKP setahun per status, dipakai sebagai keterangan pada slip.
     *
     * @var array<string, int>
     */
    public const PTKP_ANNUAL = [
        'TK/0' => 54_000_000,
        'TK/1' => 58_500_000,
        'TK/2' => 63_000_000,
        'TK/3' => 67_500_000,
        'K/0' => 58_500_000,
        'K/1' => 63_000_000,
        'K/2' => 67_500_000,
        'K/3' => 72_000_000,
    ];

    /**
     * Bracket per kategori: [batas atas penghasilan bruto bulanan, tarif].
     *
     * Batas atas bersifat **inklusif** — penghasilan tepat di angka batas
     * masih memakai tarif baris itu, sesuai pembacaan "s.d." pada lampiran.
     * Baris terakhir memakai PHP_INT_MAX sebagai penutup.
     *
     * @var array<string, list<array{0: int, 1: float}>>
     */
    private const BRACKETS = [
        self::CATEGORY_A => [
            [5_400_000, 0.0000], [5_650_000, 0.0025], [5_950_000, 0.0050],
            [6_300_000, 0.0075], [6_750_000, 0.0100], [7_500_000, 0.0125],
            [8_550_000, 0.0150], [9_650_000, 0.0175], [10_050_000, 0.0200],
            [10_350_000, 0.0225], [10_700_000, 0.0250], [11_050_000, 0.0300],
            [11_600_000, 0.0350], [12_500_000, 0.0400], [13_750_000, 0.0500],
            [15_100_000, 0.0600], [16_950_000, 0.0700], [19_750_000, 0.0800],
            [24_150_000, 0.0900], [26_450_000, 0.1000], [28_000_000, 0.1100],
            [30_050_000, 0.1200], [32_400_000, 0.1300], [35_400_000, 0.1400],
            [39_100_000, 0.1500], [43_850_000, 0.1600], [47_800_000, 0.1700],
            [51_400_000, 0.1800], [56_300_000, 0.1900], [62_200_000, 0.2000],
            [68_600_000, 0.2100], [77_500_000, 0.2200], [89_000_000, 0.2300],
            [103_000_000, 0.2400], [125_000_000, 0.2500], [157_000_000, 0.2600],
            [206_000_000, 0.2700], [337_000_000, 0.2800], [454_000_000, 0.2900],
            [550_000_000, 0.3000], [695_000_000, 0.3100], [910_000_000, 0.3200],
            [1_400_000_000, 0.3300], [PHP_INT_MAX, 0.3400],
        ],
        self::CATEGORY_B => [
            [6_200_000, 0.0000], [6_500_000, 0.0025], [6_850_000, 0.0050],
            [7_300_000, 0.0075], [9_200_000, 0.0100], [10_750_000, 0.0150],
            [11_250_000, 0.0200], [11_600_000, 0.0250], [12_600_000, 0.0300],
            [13_600_000, 0.0400], [14_950_000, 0.0500], [16_400_000, 0.0600],
            [18_450_000, 0.0700], [21_850_000, 0.0800], [26_000_000, 0.0900],
            [27_700_000, 0.1000], [29_350_000, 0.1100], [31_450_000, 0.1200],
            [33_950_000, 0.1300], [37_100_000, 0.1400], [41_100_000, 0.1500],
            [45_800_000, 0.1600], [49_500_000, 0.1700], [53_800_000, 0.1800],
            [58_500_000, 0.1900], [64_000_000, 0.2000], [71_000_000, 0.2100],
            [80_000_000, 0.2200], [93_000_000, 0.2300], [109_000_000, 0.2400],
            [129_000_000, 0.2500], [163_000_000, 0.2600], [211_000_000, 0.2700],
            [374_000_000, 0.2800], [459_000_000, 0.2900], [555_000_000, 0.3000],
            [704_000_000, 0.3100], [957_000_000, 0.3200], [1_405_000_000, 0.3300],
            [PHP_INT_MAX, 0.3400],
        ],
        self::CATEGORY_C => [
            [6_600_000, 0.0000], [6_950_000, 0.0025], [7_350_000, 0.0050],
            [7_800_000, 0.0075], [8_850_000, 0.0100], [9_800_000, 0.0125],
            [10_950_000, 0.0150], [11_200_000, 0.0175], [12_050_000, 0.0200],
            [12_950_000, 0.0300], [14_150_000, 0.0400], [15_550_000, 0.0500],
            [17_050_000, 0.0600], [19_500_000, 0.0700], [22_700_000, 0.0800],
            [26_600_000, 0.0900], [28_100_000, 0.1000], [30_100_000, 0.1100],
            [32_600_000, 0.1200], [35_400_000, 0.1300], [38_900_000, 0.1400],
            [43_000_000, 0.1500], [47_400_000, 0.1600], [51_200_000, 0.1700],
            [55_800_000, 0.1800], [60_400_000, 0.1900], [66_700_000, 0.2000],
            [74_500_000, 0.2100], [83_200_000, 0.2200], [95_600_000, 0.2300],
            [110_000_000, 0.2400], [134_000_000, 0.2500], [169_000_000, 0.2600],
            [221_000_000, 0.2700], [390_000_000, 0.2800], [463_000_000, 0.2900],
            [561_000_000, 0.3000], [709_000_000, 0.3100], [965_000_000, 0.3200],
            [1_419_000_000, 0.3300], [PHP_INT_MAX, 0.3400],
        ],
    ];

    /**
     * Kategori TER untuk sebuah status PTKP. Status kosong atau tidak dikenal
     * jatuh ke TER A — kategori dengan PTKP terendah, jadi potongannya paling
     * besar. Sengaja begitu: kurang potong berarti karyawan menanggung
     * kekurangan bayar di SPT tahunan, lebih potong dapat direstitusi.
     */
    public static function categoryFor(?string $ptkpStatus): string
    {
        return self::PTKP_CATEGORY[$ptkpStatus] ?? self::CATEGORY_A;
    }

    /**
     * Tarif efektif untuk penghasilan bruto bulanan pada suatu status PTKP.
     */
    public static function rateFor(float $monthlyGross, ?string $ptkpStatus): float
    {
        $brackets = self::BRACKETS[self::categoryFor($ptkpStatus)];

        foreach ($brackets as [$ceiling, $rate]) {
            if ($monthlyGross <= $ceiling) {
                return $rate;
            }
        }

        return end($brackets)[1];
    }

    /**
     * Bracket mentah suatu kategori — dipakai test untuk memeriksa keutuhan
     * tabel, dan tersedia bila suatu saat perlu dicetak sebagai referensi.
     *
     * @return list<array{0: int, 1: float}>
     */
    public static function brackets(string $category): array
    {
        return self::BRACKETS[$category] ?? [];
    }

    /**
     * Daftar status PTKP untuk dropdown form karyawan.
     *
     * @return list<array{value: string, label: string, category: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (string $code) => [
                'value' => $code,
                'label' => self::PTKP_LABEL[$code],
                'category' => self::categoryFor($code),
            ],
            array_keys(self::PTKP_LABEL),
        );
    }
}
