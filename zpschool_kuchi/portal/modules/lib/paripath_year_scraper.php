<?php
declare(strict_types=1);

/**
 * शालेय परिपाठ संपूर्ण वर्ष डेटा सिंक व स्क्रॅपर (Whole Year Paripath Scraper & Seeder)
 * curl_multi द्वारे अतिवेगवान बॅच स्क्रॅपिंग आणि परिपूर्ण ३६५ दिवसांचा बॅकअप डेटाबेस.
 */

require_once __DIR__ . '/paripath_db.php';
require_once __DIR__ . '/paripath_year_bank.php';

class ParipathYearScraper
{
    private PDO $db;
    private int $concurrency = 12;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?: getParipathDB();
    }

    /**
     * Run full-year scrape and population (Academic Year 2026-27 & Calendar Year 2026)
     */
    public function syncWholeYear(string $startDate = '2026-01-01', string $endDate = '2027-05-31', callable $onProgress = null): array
    {
        $stats = [
            'dinvishesh_days' => 0,
            'dinvishesh_events' => 0,
            'panchang_remote' => 0,
            'panchang_calculated' => 0,
            'suvichar_remote' => 0,
            'suvichar_seeded' => 0,
            'subhashit_remote' => 0,
            'subhashit_seeded' => 0,
            'custom_notes_seeded' => 0
        ];

        // 1. Scrape all 365 days of Dinvishesh (Month 1..12, Day 1..31)
        if ($onProgress) $onProgress("पायरी १/४: सर्व ३६५ दिवसांचे दिनविशेष (paripath.in) वरून स्क्रॅप करत आहे...");
        $dinStats = $this->scrapeAllDinvishesh($onProgress);
        $stats['dinvishesh_days'] = $dinStats['days'];
        $stats['dinvishesh_events'] = $dinStats['events'];

        // 2. Generate date range for Panchang, Suvichar, Subhashit, Custom Notes
        $cur = new DateTime($startDate);
        $last = new DateTime($endDate);
        $dateList = [];
        while ($cur <= $last) {
            $dateList[] = $cur->format('Y-m-d');
            $cur->modify('+1 day');
        }

        if ($onProgress) $onProgress("पायरी २/४: एकूण " . count($dateList) . " दिवसांचे पंचांग, सुविचार व सुभाषित प्रक्रिया सुरू...");

        // Process dates in concurrent chunks
        $chunks = array_chunk($dateList, $this->concurrency);
        $totalChunks = count($chunks);

        foreach ($chunks as $chunkIndex => $chunk) {
            $chunkStats = $this->processDateChunk($chunk);
            $stats['panchang_remote'] += $chunkStats['panchang_remote'];
            $stats['panchang_calculated'] += $chunkStats['panchang_calculated'];
            $stats['suvichar_remote'] += $chunkStats['suvichar_remote'];
            $stats['suvichar_seeded'] += $chunkStats['suvichar_seeded'];
            $stats['subhashit_remote'] += $chunkStats['subhashit_remote'];
            $stats['subhashit_seeded'] += $chunkStats['subhashit_seeded'];
            $stats['custom_notes_seeded'] += $chunkStats['custom_notes_seeded'];

            if ($onProgress) {
                $pct = round((($chunkIndex + 1) / $totalChunks) * 100);
                $onProgress("तपशील: " . ($chunkIndex + 1) . "/{$totalChunks} बॅच पूर्ण ({$pct}%) — " . end($chunk));
            }
        }

        return $stats;
    }

    /**
     * Scrape Dinvishesh for all 12 months (365 calendar days)
     */
    public function scrapeAllDinvishesh(?callable $onProgress = null): array
    {
        $daysInMonth = [
            1 => 31, 2 => 29, 3 => 31, 4 => 30, 5 => 31, 6 => 30,
            7 => 31, 8 => 31, 9 => 30, 10 => 31, 11 => 30, 12 => 31
        ];

        $mdPairs = [];
        for ($m = 1; $m <= 12; $m++) {
            for ($d = 1; $d <= $daysInMonth[$m]; $d++) {
                $mdPairs[] = ['m' => $m, 'd' => $d];
            }
        }

        $chunks = array_chunk($mdPairs, $this->concurrency);
        $totalDays = count($mdPairs);
        $totalEvents = 0;
        $processedDays = 0;

        foreach ($chunks as $chunk) {
            $urls = [];
            foreach ($chunk as $pair) {
                $key = "{$pair['m']}_{$pair['d']}";
                $urls[$key] = "https://paripath.in/api/din-vishesh?month={$pair['m']}&day={$pair['d']}";
            }

            $responses = $this->multiHttpGet($urls);

            $this->db->beginTransaction();
            try {
                $ins = $this->db->prepare('
                    INSERT INTO dinvishesh (
                        event_date, event_year, category, title_mr, title_en, short_info_mr,
                        description_mr, primary_image, month, day, is_featured
                    ) VALUES (
                        :event_date, :event_year, :category, :title_mr, :title_en, :short_info_mr,
                        :description_mr, :primary_image, :month, :day, :is_featured
                    )
                ');

                foreach ($chunk as $pair) {
                    $key = "{$pair['m']}_{$pair['d']}";
                    $m = $pair['m'];
                    $d = $pair['d'];
                    $sampleDate = sprintf('2026-%02d-%02d', $m, $d);

                    $raw = $responses[$key] ?? null;
                    if ($raw) {
                        $json = json_decode($raw, true);
                        if (!empty($json['success'])) {
                            $events = $json['events'] ?? ($json['data']['events'] ?? ($json['data'] ?? []));
                            if (is_array($events) && !empty($events)) {
                                // Clear existing for this month & day
                                $this->db->prepare('DELETE FROM dinvishesh WHERE month = ? AND day = ?')->execute([$m, $d]);

                                foreach ($events as $ev) {
                                    $ins->execute([
                                        ':event_date' => $sampleDate,
                                        ':event_year' => !empty($ev['event_year']) ? (int)$ev['event_year'] : null,
                                        ':category' => $ev['category'] ?? 'historical_event',
                                        ':title_mr' => $ev['title_mr'] ?? '',
                                        ':title_en' => $ev['title_en'] ?? null,
                                        ':short_info_mr' => $ev['short_info_mr'] ?? null,
                                        ':description_mr' => $ev['description_mr'] ?? null,
                                        ':primary_image' => $ev['primary_image'] ?? null,
                                        ':month' => $m,
                                        ':day' => $d,
                                        ':is_featured' => !empty($ev['is_featured']) ? 1 : 0
                                    ]);
                                    $totalEvents++;
                                }
                                $processedDays++;
                            }
                        }
                    }
                }
                $this->db->commit();
            } catch (Throwable $e) {
                $this->db->rollBack();
            }

            if ($onProgress) {
                $onProgress("दिनविशेष प्रगती: {$processedDays}/{$totalDays} दिवस स्क्रॅप झाले ({$totalEvents} ऐतिहासिक नोंदी)...");
            }
        }

        return ['days' => $processedDays, 'events' => $totalEvents];
    }

    /**
     * Process a batch of dates for Panchang, Suvichar, Subhashit, Custom Notes
     */
    private function processDateChunk(array $dates): array
    {
        $urls = [];
        foreach ($dates as $d) {
            $urls["p_{$d}"] = "https://paripath.in/api/panchang?date={$d}";
            $urls["suv_{$d}"] = "https://paripath.in/api/suvichar?date={$d}";
            $urls["sub_{$d}"] = "https://paripath.in/api/subhashit?date={$d}";
        }

        $responses = $this->multiHttpGet($urls);

        $chunkStats = [
            'panchang_remote' => 0,
            'panchang_calculated' => 0,
            'suvichar_remote' => 0,
            'suvichar_seeded' => 0,
            'subhashit_remote' => 0,
            'subhashit_seeded' => 0,
            'custom_notes_seeded' => 0
        ];

        $this->db->beginTransaction();
        try {
            $insPan = $this->db->prepare('
                INSERT INTO panchang (
                    date, location, hindu_month, paksha, tithi, nakshatra, yoga, karana,
                    var, ritu, sunrise, sunset, moonrise, moonset, rahu_kaal, festivals, vrat,
                    is_festival, raw_json, updated_at
                ) VALUES (
                    :date, :location, :hindu_month, :paksha, :tithi, :nakshatra, :yoga, :karana,
                    :var, :ritu, :sunrise, :sunset, :moonrise, :moonset, :rahu_kaal, :festivals, :vrat,
                    :is_festival, :raw_json, CURRENT_TIMESTAMP
                ) ON CONFLICT(date) DO UPDATE SET
                    location = excluded.location,
                    hindu_month = excluded.hindu_month,
                    paksha = excluded.paksha,
                    tithi = excluded.tithi,
                    nakshatra = excluded.nakshatra,
                    yoga = excluded.yoga,
                    karana = excluded.karana,
                    var = excluded.var,
                    ritu = excluded.ritu,
                    sunrise = excluded.sunrise,
                    sunset = excluded.sunset,
                    moonrise = excluded.moonrise,
                    moonset = excluded.moonset,
                    rahu_kaal = excluded.rahu_kaal,
                    festivals = excluded.festivals,
                    vrat = excluded.vrat,
                    is_festival = excluded.is_festival,
                    raw_json = excluded.raw_json,
                    updated_at = CURRENT_TIMESTAMP
            ');

            $insSuv = $this->db->prepare('
                INSERT INTO suvichar (display_date, text_mr, text_en, author, meaning_mr, explanation_mr)
                VALUES (:display_date, :text_mr, :text_en, :author, :meaning_mr, :explanation_mr)
                ON CONFLICT(display_date) DO UPDATE SET
                    text_mr = excluded.text_mr,
                    text_en = excluded.text_en,
                    author = excluded.author,
                    meaning_mr = excluded.meaning_mr,
                    explanation_mr = excluded.explanation_mr
            ');

            $insSub = $this->db->prepare('
                INSERT INTO subhashit (
                    display_date, granth, chapter, verse, text_sa, text_mr, text_en,
                    meaning_mr, explanation_mr, usage_mr
                ) VALUES (
                    :display_date, :granth, :chapter, :verse, :text_sa, :text_mr, :text_en,
                    :meaning_mr, :explanation_mr, :usage_mr
                ) ON CONFLICT(display_date) DO UPDATE SET
                    granth = excluded.granth,
                    chapter = excluded.chapter,
                    verse = excluded.verse,
                    text_sa = excluded.text_sa,
                    text_mr = excluded.text_mr,
                    text_en = excluded.text_en,
                    meaning_mr = excluded.meaning_mr,
                    explanation_mr = excluded.explanation_mr,
                    usage_mr = excluded.usage_mr
            ');

            $insNote = $this->db->prepare('
                INSERT INTO custom_notes (date, announcement, word_of_day, word_meaning, gk_question, gk_answer, updated_at)
                VALUES (:date, :announcement, :word_of_day, :word_meaning, :gk_question, :gk_answer, CURRENT_TIMESTAMP)
                ON CONFLICT(date) DO UPDATE SET
                    word_of_day = CASE WHEN custom_notes.word_of_day = "" OR custom_notes.word_of_day IS NULL THEN excluded.word_of_day ELSE custom_notes.word_of_day END,
                    word_meaning = CASE WHEN custom_notes.word_meaning = "" OR custom_notes.word_meaning IS NULL THEN excluded.word_meaning ELSE custom_notes.word_meaning END,
                    gk_question = CASE WHEN custom_notes.gk_question = "" OR custom_notes.gk_question IS NULL THEN excluded.gk_question ELSE custom_notes.gk_question END,
                    gk_answer = CASE WHEN custom_notes.gk_answer = "" OR custom_notes.gk_answer IS NULL THEN excluded.gk_answer ELSE custom_notes.gk_answer END
            ');

            foreach ($dates as $d) {
                $dayOfYear = (int)date('z', strtotime($d));

                // 1. Panchang
                $pRaw = $responses["p_{$d}"] ?? null;
                $pJson = $pRaw ? json_decode($pRaw, true) : null;
                if ($pJson && !empty($pJson['success']) && !empty($pJson['data']['tithi'])) {
                    $p = $pJson['data'];
                    $festivals = is_array($p['festivals'] ?? null) ? json_encode($p['festivals'], JSON_UNESCAPED_UNICODE) : (string)($p['festivals'] ?? '');
                    $vrat = is_array($p['vrat'] ?? null) ? json_encode($p['vrat'], JSON_UNESCAPED_UNICODE) : (string)($p['vrat'] ?? '');

                    $insPan->execute([
                        ':date' => $d,
                        ':location' => $p['location'] ?? 'महाराष्ट्र',
                        ':hindu_month' => $p['hindu_month'] ?? null,
                        ':paksha' => $p['paksha'] ?? null,
                        ':tithi' => $p['tithi'] ?? null,
                        ':nakshatra' => $p['nakshatra'] ?? null,
                        ':yoga' => $p['yoga'] ?? null,
                        ':karana' => $p['karana'] ?? null,
                        ':var' => $p['var'] ?? paripath_marathi_day($d),
                        ':ritu' => $p['ritu'] ?? null,
                        ':sunrise' => $p['sunrise'] ?? null,
                        ':sunset' => $p['sunset'] ?? null,
                        ':moonrise' => $p['moonrise'] ?? null,
                        ':moonset' => $p['moonset'] ?? null,
                        ':rahu_kaal' => $p['rahu_kaal'] ?? null,
                        ':festivals' => $festivals,
                        ':vrat' => $vrat,
                        ':is_festival' => !empty($p['is_festival']) ? 1 : 0,
                        ':raw_json' => $pRaw
                    ]);
                    $chunkStats['panchang_remote']++;
                } else {
                    // Use verified Maharashtra calendar calculation
                    $calcP = ParipathYearBank::getPanchangForDate($d);
                    $insPan->execute([
                        ':date' => $d,
                        ':location' => $calcP['location'],
                        ':hindu_month' => $calcP['hindu_month'],
                        ':paksha' => $calcP['paksha'],
                        ':tithi' => $calcP['tithi'],
                        ':nakshatra' => $calcP['nakshatra'],
                        ':yoga' => $calcP['yoga'],
                        ':karana' => $calcP['karana'],
                        ':var' => $calcP['var'],
                        ':ritu' => $calcP['ritu'],
                        ':sunrise' => $calcP['sunrise'],
                        ':sunset' => $calcP['sunset'],
                        ':moonrise' => $calcP['moonrise'],
                        ':moonset' => $calcP['moonset'],
                        ':rahu_kaal' => $calcP['rahu_kaal'],
                        ':festivals' => $calcP['festivals'],
                        ':vrat' => $calcP['vrat'],
                        ':is_festival' => $calcP['is_festival'],
                        ':raw_json' => json_encode($calcP, JSON_UNESCAPED_UNICODE)
                    ]);
                    $chunkStats['panchang_calculated']++;
                }

                // 2. Suvichar
                $suvRaw = $responses["suv_{$d}"] ?? null;
                $suvJson = $suvRaw ? json_decode($suvRaw, true) : null;
                if ($suvJson && !empty($suvJson['success']) && !empty($suvJson['data']['text_mr'])) {
                    $s = $suvJson['data'];
                    $insSuv->execute([
                        ':display_date' => $d,
                        ':text_mr' => $s['text_mr'] ?? ($s['text'] ?? ''),
                        ':text_en' => $s['text_en'] ?? null,
                        ':author' => !empty($s['author']) ? $s['author'] : null,
                        ':meaning_mr' => $s['meaning_mr'] ?? null,
                        ':explanation_mr' => $s['explanation_mr'] ?? null
                    ]);
                    $chunkStats['suvichar_remote']++;
                } else {
                    $s = ParipathYearBank::getSuvicharForDay($dayOfYear, $d);
                    $insSuv->execute([
                        ':display_date' => $d,
                        ':text_mr' => $s['text_mr'],
                        ':text_en' => $s['text_en'],
                        ':author' => $s['author'],
                        ':meaning_mr' => $s['meaning_mr'],
                        ':explanation_mr' => $s['explanation_mr']
                    ]);
                    $chunkStats['suvichar_seeded']++;
                }

                // 3. Subhashit
                $subRaw = $responses["sub_{$d}"] ?? null;
                $subJson = $subRaw ? json_decode($subRaw, true) : null;
                if ($subJson && !empty($subJson['success']) && !empty($subJson['data'])) {
                    $sb = is_array($subJson['data']) && isset($subJson['data'][0]) ? $subJson['data'][0] : $subJson['data'];
                    $granthName = is_array($sb['granth'] ?? null) ? ($sb['granth']['name_mr'] ?? 'ज्ञानेश्वरी') : (string)($sb['granth'] ?? '');

                    $insSub->execute([
                        ':display_date' => $d,
                        ':granth' => $granthName ?: 'ज्ञानेश्वरी',
                        ':chapter' => !empty($sb['chapter']) ? (int)$sb['chapter'] : null,
                        ':verse' => !empty($sb['verse']) ? (int)$sb['verse'] : null,
                        ':text_sa' => $sb['text_sa'] ?? '',
                        ':text_mr' => $sb['text_mr'] ?? null,
                        ':text_en' => $sb['text_en'] ?? null,
                        ':meaning_mr' => $sb['meaning_mr'] ?? null,
                        ':explanation_mr' => $sb['explanation_mr'] ?? null,
                        ':usage_mr' => $sb['usage_mr'] ?? null
                    ]);
                    $chunkStats['subhashit_remote']++;
                } else {
                    $sb = ParipathYearBank::getSubhashitForDay($dayOfYear, $d);
                    $insSub->execute([
                        ':display_date' => $d,
                        ':granth' => $sb['granth'],
                        ':chapter' => $sb['chapter'],
                        ':verse' => $sb['verse'],
                        ':text_sa' => $sb['text_sa'],
                        ':text_mr' => $sb['text_mr'],
                        ':text_en' => $sb['text_en'],
                        ':meaning_mr' => $sb['meaning_mr'],
                        ':explanation_mr' => $sb['explanation_mr'],
                        ':usage_mr' => $sb['usage_mr']
                    ]);
                    $chunkStats['subhashit_seeded']++;
                }

                // 4. Custom Notes (GK & Word of day)
                $note = ParipathYearBank::getCustomNotesForDay($dayOfYear, $d);
                $insNote->execute([
                    ':date' => $d,
                    ':announcement' => '',
                    ':word_of_day' => $note['word_of_day'],
                    ':word_meaning' => $note['word_meaning'],
                    ':gk_question' => $note['gk_question'],
                    ':gk_answer' => $note['gk_answer']
                ]);
                $chunkStats['custom_notes_seeded']++;
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
        }

        return $chunkStats;
    }

    /**
     * Fast non-blocking parallel HTTP GET using curl_multi
     */
    private function multiHttpGet(array $urls): array
    {
        $mh = curl_multi_init();
        $handles = [];

        foreach ($urls as $k => $url) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'ParipathSchoolHub/1.0');
            curl_multi_add_handle($mh, $ch);
            $handles[$k] = $ch;
        }

        $running = null;
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh, 0.05);
            }
        } while ($running > 0 && $status === CURLM_OK);

        $results = [];
        foreach ($handles as $k => $ch) {
            $body = curl_multi_getcontent($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            if ($code === 200 && is_string($body) && trim($body) !== '') {
                $results[$k] = $body;
            } else {
                $results[$k] = null;
            }
        }
        curl_multi_close($mh);

        return $results;
    }
}
