<?php
declare(strict_types=1);

/**
 * शालेय परिपाठ इंजिन (School Assembly Engine)
 * स्वतंत्र SQLite डेटाबेस (data/paripath.sqlite) सह हायब्रीड कॅश-फर्स्ट सिंक.
 */

require_once __DIR__ . '/paripath_db.php';
require_once __DIR__ . '/paripath_seed_prayers.php';
require_once __DIR__ . '/paripath_year_bank.php';

// Always make sure prayers are seeded
paripath_seed_default_prayers();

/**
 * Convert English digits to Marathi Devanagari digits
 */
function paripath_marathi_digits($str): string
{
    $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $mr = ['०', '१', '२', '३', '४', '५', '६', '७', '८', '९'];
    return str_replace($en, $mr, (string)$str);
}

/**
 * Format YYYY-MM-DD to Marathi date (उदा. २१ सप्टेंबर २०२६)
 */
function paripath_marathi_date(string $date): string
{
    $months = [
        1 => 'जानेवारी', 2 => 'फेब्रुवारी', 3 => 'मार्च', 4 => 'एप्रिल',
        5 => 'मे', 6 => 'जून', 7 => 'जुलै', 8 => 'ऑगस्ट',
        9 => 'सप्टेंबर', 10 => 'ऑक्टोबर', 11 => 'नोव्हेंबर', 12 => 'डिसेंबर'
    ];
    $ts = strtotime($date);
    if (!$ts) return $date;

    $d = (int)date('j', $ts);
    $m = (int)date('n', $ts);
    $y = (int)date('Y', $ts);

    return paripath_marathi_digits($d) . ' ' . ($months[$m] ?? '') . ' ' . paripath_marathi_digits($y);
}

/**
 * Get Marathi Day Name (उदा. सोमवार)
 */
function paripath_marathi_day(string $date): string
{
    $days = [
        'Sunday' => 'रविवार',
        'Monday' => 'सोमवार',
        'Tuesday' => 'मंगळवार',
        'Wednesday' => 'बुधवार',
        'Thursday' => 'गुरुवार',
        'Friday' => 'शुक्रवार',
        'Saturday' => 'शनिवार'
    ];
    $ts = strtotime($date);
    $enDay = date('l', $ts ?: time());
    return $days[$enDay] ?? $enDay;
}

/**
 * HTTP GET helper with cURL and timeout
 */
function paripath_http_get(string $url, int $timeout = 3): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ParipathSchoolHub/1.0');
        $result = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code === 200 && is_string($result) && trim($result) !== '') {
            return $result;
        }
    }

    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeout,
            'header' => "User-Agent: ParipathSchoolHub/1.0\r\n"
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    $res = @file_get_contents($url, false, $ctx);
    return is_string($res) ? $res : null;
}

/**
 * Get Panchang for specific date.
 * Cache-first from SQLite, auto-fetches from paripath.in/api/panchang if missing.
 */
function paripath_get_panchang(string $date, bool $allowFetch = true): array
{
    $db = getParipathDB();
    $stmt = $db->prepare('SELECT * FROM panchang WHERE date = ?');
    $stmt->execute([$date]);
    $row = $stmt->fetch();

    if ($row && !empty($row['tithi'])) {
        if (!empty($row['festivals']) && is_string($row['festivals']) && str_starts_with($row['festivals'], '[')) {
            $row['festivals_list'] = json_decode($row['festivals'], true) ?: [];
        } else {
            $row['festivals_list'] = array_filter(array_map('trim', explode(',', (string)($row['festivals'] ?? ''))));
        }
        return $row;
    }

    if ($allowFetch) {
        $synced = paripath_sync_date($date);
        if ($synced) {
            $stmt->execute([$date]);
            $row = $stmt->fetch();
            if ($row) {
                if (!empty($row['festivals']) && is_string($row['festivals']) && str_starts_with($row['festivals'], '[')) {
                    $row['festivals_list'] = json_decode($row['festivals'], true) ?: [];
                } else {
                    $row['festivals_list'] = array_filter(array_map('trim', explode(',', (string)($row['festivals'] ?? ''))));
                }
                return $row;
            }
        }
    }

    // Fallback: calculate accurate Maharashtra Hindu Panchang
    return ParipathYearBank::getPanchangForDate($date);
}

/**
 * Get Dinvishesh events for specific date.
 */
function paripath_get_dinvishesh(string $date, bool $allowFetch = true): array
{
    $db = getParipathDB();
    $stmt = $db->prepare('SELECT * FROM dinvishesh WHERE event_date = ? ORDER BY is_featured DESC, event_year ASC, id ASC');
    $stmt->execute([$date]);
    $rows = $stmt->fetchAll();

    if (!empty($rows)) {
        return $rows;
    }

    $month = (int)date('n', strtotime($date));
    $day = (int)date('j', strtotime($date));

    // Also check month and day match in case year differs
    $stmt = $db->prepare('SELECT * FROM dinvishesh WHERE month = ? AND day = ? ORDER BY is_featured DESC, event_year ASC, id ASC');
    $stmt->execute([$month, $day]);
    $rows = $stmt->fetchAll();

    if (!empty($rows)) {
        return $rows;
    }

    if ($allowFetch) {
        paripath_sync_date($date);
        $stmt = $db->prepare('SELECT * FROM dinvishesh WHERE event_date = ? OR (month = ? AND day = ?) ORDER BY is_featured DESC, event_year ASC, id ASC');
        $stmt->execute([$date, $month, $day]);
        $rows = $stmt->fetchAll();
        if (!empty($rows)) {
            return $rows;
        }
    }

    return [];
}

/**
 * Get Suvichar for date
 */
function paripath_get_suvichar(string $date, bool $allowFetch = true): array
{
    $db = getParipathDB();
    $stmt = $db->prepare('SELECT * FROM suvichar WHERE display_date = ?');
    $stmt->execute([$date]);
    $row = $stmt->fetch();

    if ($row) {
        return $row;
    }

    if ($allowFetch) {
        paripath_sync_date($date);
        $stmt->execute([$date]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }
    }

    $dayOfYear = (int)date('z', strtotime($date));
    return ParipathYearBank::getSuvicharForDay($dayOfYear, $date);
}

/**
 * Get Subhashit for date
 */
function paripath_get_subhashit(string $date, bool $allowFetch = true): array
{
    $db = getParipathDB();
    $stmt = $db->prepare('SELECT * FROM subhashit WHERE display_date = ?');
    $stmt->execute([$date]);
    $row = $stmt->fetch();

    if ($row) {
        return $row;
    }

    if ($allowFetch) {
        paripath_sync_date($date);
        $stmt->execute([$date]);
        $row = $stmt->fetch();
        if ($row) {
            return $row;
        }
    }

    $dayOfYear = (int)date('z', strtotime($date));
    return ParipathYearBank::getSubhashitForDay($dayOfYear, $date);
}

/**
 * Custom blackboard notes for date (announcement, word of day, GK)
 */
function paripath_get_custom_notes(string $date): array
{
    $db = getParipathDB();
    $stmt = $db->prepare('SELECT * FROM custom_notes WHERE date = ?');
    $stmt->execute([$date]);
    $row = $stmt->fetch();

    if ($row) {
        return $row;
    }

    $dayOfYear = (int)date('z', strtotime($date));
    return ParipathYearBank::getCustomNotesForDay($dayOfYear, $date);
}

function paripath_save_custom_notes(string $date, array $data): bool
{
    $db = getParipathDB();
    $stmt = $db->prepare('
        INSERT INTO custom_notes (date, announcement, word_of_day, word_meaning, gk_question, gk_answer, updated_at)
        VALUES (:date, :announcement, :word_of_day, :word_meaning, :gk_question, :gk_answer, CURRENT_TIMESTAMP)
        ON CONFLICT(date) DO UPDATE SET
            announcement = excluded.announcement,
            word_of_day = excluded.word_of_day,
            word_meaning = excluded.word_meaning,
            gk_question = excluded.gk_question,
            gk_answer = excluded.gk_answer,
            updated_at = CURRENT_TIMESTAMP
    ');
    return $stmt->execute([
        ':date' => $date,
        ':announcement' => $data['announcement'] ?? '',
        ':word_of_day' => $data['word_of_day'] ?? '',
        ':word_meaning' => $data['word_meaning'] ?? '',
        ':gk_question' => $data['gk_question'] ?? '',
        ':gk_answer' => $data['gk_answer'] ?? ''
    ]);
}

/**
 * Get All Prayers
 */
function paripath_get_prayers(?string $category = null): array
{
    $db = getParipathDB();
    if ($category) {
        $stmt = $db->prepare('SELECT * FROM prayers WHERE category = ? AND is_active = 1 ORDER BY display_order ASC');
        $stmt->execute([$category]);
        return $stmt->fetchAll();
    }
    return $db->query('SELECT * FROM prayers WHERE is_active = 1 ORDER BY display_order ASC, id ASC')->fetchAll();
}

/**
 * Fetch and sync date from paripath.in/api
 */
function paripath_sync_date(string $date): bool
{
    static $attempted = [];
    if (isset($attempted[$date])) {
        return $attempted[$date];
    }
    $attempted[$date] = false;

    $db = getParipathDB();
    $ts = strtotime($date);
    if (!$ts) return false;

    $month = (int)date('n', $ts);
    $day = (int)date('j', $ts);
    $dayOfYear = (int)date('z', $ts);

    $successAny = false;

    // 1. Panchang
    $panchangJson = paripath_http_get("https://paripath.in/api/panchang?date={$date}");
    if ($panchangJson) {
        $data = json_decode($panchangJson, true);
        if (!empty($data['success']) && !empty($data['data'])) {
            $p = $data['data'];
            $festivals = is_array($p['festivals'] ?? null) ? json_encode($p['festivals'], JSON_UNESCAPED_UNICODE) : (string)($p['festivals'] ?? '');
            $vrat = is_array($p['vrat'] ?? null) ? json_encode($p['vrat'], JSON_UNESCAPED_UNICODE) : (string)($p['vrat'] ?? '');
            
            $stmt = $db->prepare('
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

            $stmt->execute([
                ':date' => $date,
                ':location' => $p['location'] ?? 'महाराष्ट्र',
                ':hindu_month' => $p['hindu_month'] ?? null,
                ':paksha' => $p['paksha'] ?? null,
                ':tithi' => $p['tithi'] ?? null,
                ':nakshatra' => $p['nakshatra'] ?? null,
                ':yoga' => $p['yoga'] ?? null,
                ':karana' => $p['karana'] ?? null,
                ':var' => $p['var'] ?? paripath_marathi_day($date),
                ':ritu' => $p['ritu'] ?? null,
                ':sunrise' => $p['sunrise'] ?? null,
                ':sunset' => $p['sunset'] ?? null,
                ':moonrise' => $p['moonrise'] ?? null,
                ':moonset' => $p['moonset'] ?? null,
                ':rahu_kaal' => $p['rahu_kaal'] ?? null,
                ':festivals' => $festivals,
                ':vrat' => $vrat,
                ':is_festival' => !empty($p['is_festival']) ? 1 : 0,
                ':raw_json' => $panchangJson
            ]);
            $successAny = true;
        }
    }

    // If panchang not saved from network, save fallback from YearBank to SQLite
    $chkP = $db->prepare('SELECT date FROM panchang WHERE date = ?');
    $chkP->execute([$date]);
    if (!$chkP->fetch()) {
        $p = ParipathYearBank::getPanchangForDate($date);
        $festivals = is_array($p['festivals'] ?? null) ? json_encode($p['festivals'], JSON_UNESCAPED_UNICODE) : (string)($p['festivals'] ?? '');
        $stmt = $db->prepare('
            INSERT INTO panchang (
                date, location, hindu_month, paksha, tithi, nakshatra, yoga, karana,
                var, ritu, sunrise, sunset, moonrise, moonset, rahu_kaal, festivals, vrat,
                is_festival, updated_at
            ) VALUES (
                :date, :location, :hindu_month, :paksha, :tithi, :nakshatra, :yoga, :karana,
                :var, :ritu, :sunrise, :sunset, :moonrise, :moonset, :rahu_kaal, :festivals, :vrat,
                :is_festival, CURRENT_TIMESTAMP
            ) ON CONFLICT(date) DO NOTHING
        ');
        $stmt->execute([
            ':date' => $date,
            ':location' => $p['location'] ?? 'महाराष्ट्र',
            ':hindu_month' => $p['hindu_month'] ?? null,
            ':paksha' => $p['paksha'] ?? null,
            ':tithi' => $p['tithi'] ?? null,
            ':nakshatra' => $p['nakshatra'] ?? null,
            ':yoga' => $p['yoga'] ?? null,
            ':karana' => $p['karana'] ?? null,
            ':var' => $p['var'] ?? paripath_marathi_day($date),
            ':ritu' => $p['ritu'] ?? null,
            ':sunrise' => $p['sunrise'] ?? '06:24 AM',
            ':sunset' => $p['sunset'] ?? '06:33 PM',
            ':moonrise' => $p['moonrise'] ?? null,
            ':moonset' => $p['moonset'] ?? null,
            ':rahu_kaal' => $p['rahu_kaal'] ?? null,
            ':festivals' => $festivals,
            ':vrat' => (string)($p['vrat'] ?? ''),
            ':is_festival' => !empty($p['is_festival']) ? 1 : 0
        ]);
        $successAny = true;
    }

    // 2. Dinvishesh
    $dinJson = paripath_http_get("https://paripath.in/api/din-vishesh?date={$date}");
    if ($dinJson) {
        $data = json_decode($dinJson, true);
        $events = $data['events'] ?? ($data['data']['events'] ?? ($data['data'] ?? []));
        if (!empty($data['success']) && is_array($events) && !empty($events)) {
            // Delete prior events for this specific date to avoid duplicates
            $db->prepare('DELETE FROM dinvishesh WHERE event_date = ?')->execute([$date]);

            $ins = $db->prepare('
                INSERT INTO dinvishesh (
                    event_date, event_year, category, title_mr, title_en, short_info_mr,
                    description_mr, primary_image, month, day, is_featured
                ) VALUES (
                    :event_date, :event_year, :category, :title_mr, :title_en, :short_info_mr,
                    :description_mr, :primary_image, :month, :day, :is_featured
                )
            ');

            foreach ($events as $ev) {
                $ins->execute([
                    ':event_date' => $date,
                    ':event_year' => !empty($ev['event_year']) ? (int)$ev['event_year'] : null,
                    ':category' => $ev['category'] ?? 'historical_event',
                    ':title_mr' => $ev['title_mr'] ?? '',
                    ':title_en' => $ev['title_en'] ?? null,
                    ':short_info_mr' => $ev['short_info_mr'] ?? null,
                    ':description_mr' => $ev['description_mr'] ?? null,
                    ':primary_image' => $ev['primary_image'] ?? null,
                    ':month' => $month,
                    ':day' => $day,
                    ':is_featured' => !empty($ev['is_featured']) ? 1 : 0
                ]);
            }
            $successAny = true;
        }
    }

    // 3. Suvichar (Fallback save ensures SQLite always has suvichar for this date)
    $suvJson = paripath_http_get("https://paripath.in/api/suvichar?date={$date}");
    $suvSaved = false;
    if ($suvJson) {
        $data = json_decode($suvJson, true);
        if (!empty($data['success']) && !empty($data['data'])) {
            $s = $data['data'];
            $insSuv = $db->prepare('
                INSERT INTO suvichar (display_date, text_mr, text_en, author, meaning_mr, explanation_mr)
                VALUES (:display_date, :text_mr, :text_en, :author, :meaning_mr, :explanation_mr)
                ON CONFLICT(display_date) DO UPDATE SET
                    text_mr = excluded.text_mr,
                    text_en = excluded.text_en,
                    author = excluded.author,
                    meaning_mr = excluded.meaning_mr,
                    explanation_mr = excluded.explanation_mr
            ');
            $insSuv->execute([
                ':display_date' => $date,
                ':text_mr' => $s['text_mr'] ?? ($s['text'] ?? ''),
                ':text_en' => $s['text_en'] ?? null,
                ':author' => !empty($s['author']) ? $s['author'] : null,
                ':meaning_mr' => $s['meaning_mr'] ?? null,
                ':explanation_mr' => $s['explanation_mr'] ?? null
            ]);
            $suvSaved = true;
            $successAny = true;
        }
    }

    if (!$suvSaved) {
        $chkS = $db->prepare('SELECT id FROM suvichar WHERE display_date = ?');
        $chkS->execute([$date]);
        if (!$chkS->fetch()) {
            $s = ParipathYearBank::getSuvicharForDay($dayOfYear, $date);
            $insSuv = $db->prepare('
                INSERT INTO suvichar (display_date, text_mr, text_en, author, meaning_mr, explanation_mr)
                VALUES (:display_date, :text_mr, :text_en, :author, :meaning_mr, :explanation_mr)
                ON CONFLICT(display_date) DO UPDATE SET
                    text_mr = excluded.text_mr,
                    text_en = excluded.text_en,
                    author = excluded.author,
                    meaning_mr = excluded.meaning_mr,
                    explanation_mr = excluded.explanation_mr
            ');
            $insSuv->execute([
                ':display_date' => $date,
                ':text_mr' => $s['text_mr'] ?? '',
                ':text_en' => $s['text_en'] ?? null,
                ':author' => !empty($s['author']) ? $s['author'] : null,
                ':meaning_mr' => $s['meaning_mr'] ?? null,
                ':explanation_mr' => $s['explanation_mr'] ?? null
            ]);
            $successAny = true;
        }
    }

    // 4. Subhashit
    $subJson = paripath_http_get("https://paripath.in/api/subhashit?date={$date}");
    $subSaved = false;
    if ($subJson) {
        $data = json_decode($subJson, true);
        if (!empty($data['success']) && !empty($data['data'])) {
            $sb = is_array($data['data']) && isset($data['data'][0]) ? $data['data'][0] : $data['data'];
            $granthName = is_array($sb['granth'] ?? null) ? ($sb['granth']['name_mr'] ?? 'ज्ञानेश्वरी') : (string)($sb['granth'] ?? '');

            $insSub = $db->prepare('
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
            $insSub->execute([
                ':display_date' => $date,
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
            $subSaved = true;
            $successAny = true;
        }
    }

    if (!$subSaved) {
        $chkSub = $db->prepare('SELECT id FROM subhashit WHERE display_date = ?');
        $chkSub->execute([$date]);
        if (!$chkSub->fetch()) {
            $sb = ParipathYearBank::getSubhashitForDay($dayOfYear, $date);
            $insSub = $db->prepare('
                INSERT INTO subhashit (
                    display_date, granth, chapter, verse, text_sa, text_mr, text_en,
                    meaning_mr, explanation_mr, usage_mr
                ) VALUES (
                    :display_date, :granth, :chapter, :verse, :text_sa, :text_mr, :text_en,
                    :meaning_mr, :explanation_mr, :usage_mr
                ) ON CONFLICT(display_date) DO NOTHING
            ');
            $insSub->execute([
                ':display_date' => $date,
                ':granth' => $sb['granth'] ?? 'ज्ञानेश्वरी',
                ':chapter' => !empty($sb['chapter']) ? (int)$sb['chapter'] : null,
                ':verse' => !empty($sb['verse']) ? (int)$sb['verse'] : null,
                ':text_sa' => $sb['text_sa'] ?? '',
                ':text_mr' => $sb['text_mr'] ?? null,
                ':text_en' => $sb['text_en'] ?? null,
                ':meaning_mr' => $sb['meaning_mr'] ?? null,
                ':explanation_mr' => $sb['explanation_mr'] ?? null,
                ':usage_mr' => $sb['usage_mr'] ?? null
            ]);
            $successAny = true;
        }
    }

    $attempted[$date] = $successAny;
    return $successAny;
}

/**
 * Bulk scrape a date range into SQLite
 */
function paripath_scrape_range(string $startDate, string $endDate): array
{
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $end->modify('+1 day');
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);

    $results = [
        'total' => 0,
        'success' => 0,
        'failed' => 0,
        'dates' => []
    ];

    foreach ($period as $dt) {
        $dateStr = $dt->format('Y-m-d');
        $results['total']++;
        $ok = paripath_sync_date($dateStr);
        if ($ok) {
            $results['success']++;
            $results['dates'][$dateStr] = 'synced';
        } else {
            $results['failed']++;
            $results['dates'][$dateStr] = 'failed';
        }
        // Polite delay to avoid hammering server
        usleep(150000); // 150ms
    }

    return $results;
}

/**
 * Assembly package for a day
 */
function paripath_get_full_assembly(string $date): array
{
    return [
        'date' => $date,
        'marathi_date' => paripath_marathi_date($date),
        'marathi_day' => paripath_marathi_day($date),
        'panchang' => paripath_get_panchang($date),
        'dinvishesh' => paripath_get_dinvishesh($date),
        'suvichar' => paripath_get_suvichar($date),
        'subhashit' => paripath_get_subhashit($date),
        'custom_notes' => paripath_get_custom_notes($date),
        'prayers' => paripath_get_prayers()
    ];
}
