<?php
/**
 * One-time backfill: updates the `topic` column on existing quiz_questions
 * by re-reading the HTML source files and matching by question text.
 *
 * Run: php run_backfill_topics.php
 */
declare(strict_types=1);
set_time_limit(0);
ini_set('memory_limit', '256M');

require_once __DIR__ . '/includes/db.php';

$db = getDb();

/* ── Prefix → short topic name ── */
$TOPIC_NAMES = [
    // Math
    'berij'       => 'बेरीज-वजाबाकी',
    'vajabaki'    => 'बेरीज-वजाबाकी',
    'vaja'        => 'बेरीज-वजाबाकी',
    'guna'        => 'गुणाकार',
    'bhaga'       => 'भागाकार',
    'apurnank'    => 'अपूर्णांक',
    'mapan'       => 'मोजमाप',
    'shekde'      => 'शेकडेवारी',
    'nafa'        => 'नफा-तोटा',
    'saral'       => 'साधी समीकरणे',
    'saralvyaj'   => 'साधे व्याज',
    'area'        => 'क्षेत्रफळ',
    'parimiti'    => 'परिमिती',
    'trikon'      => 'त्रिकोण',
    'vartul'      => 'वर्तुळ',
    'chouras'     => 'चौरस-आयत',
    'trimiti'     => '3D आकार',
    'kon'         => 'कोन',
    'samantar'    => 'समांतर रेषा',
    'numbers'     => 'संख्याज्ञान',
    'pattern'     => 'पॅटर्न',
    'calendar'    => 'कॅलेंडर',
    'Numerical'   => 'संख्यात्मक',
    'gatsankhya'  => 'गट संख्या',
    // Marathi
    'aaklan'      => 'आकलन उतारा',
    'akshar'      => 'अक्षर क्रम',
    'alankarik'   => 'अलंकारिक शब्द',
    'bhinnarth'   => 'भिन्नार्थी शब्द',
    'contracted'  => 'संक्षिप्त रूप',
    'ghar'        => 'घर-दर्शक शब्द',
    'ling'        => 'लिंग-वचन',
    'mhani'       => 'म्हणी',
    'nam'         => 'नाम',
    'samanarthi'  => 'समानार्थी शब्द',
    'sanvad'      => 'संवाद लेखन',
    'shabdhsamuh' => 'शब्दसमूह',
    'shuddh'      => 'शुद्धलेखन',
    'utara'       => 'उतारा आकलन',
    'vakprachar'  => 'वाक्प्रचार',
    'varn'        => 'वर्णविचार',
    'virudharthi' => 'विरुद्धार्थी शब्द',
    'kavita'      => 'कविता',
    'related'     => 'संबंधित शब्द',
    'sam'         => 'समानार्थी शब्द',
    'kal'         => 'काळ',
    'viram'       => 'विरामचिन्हे',
    'viramchinhe' => 'विरामचिन्हे',
    'gk'          => 'सामान्यज्ञान',
    'shabdhkodhi' => 'शब्दकोडी',
    // English
    'opposite'    => 'Opposites',
    'punctuation' => 'Punctuation',
    'rhyming'     => 'Rhyming Words',
    'Homophones'  => 'Homophones',
    'smallword'   => 'Small Words',
    'wformation'  => 'Word Formation',
    'wordregister'=> 'Word Register',
    'describing'  => 'Describing Words',
    'greeting'    => 'Greetings',
    'seeking'     => 'Seeking Clarification',
    'request'     => 'Requests & Responses',
    'creative'    => 'Creative Writing',
    'chitralekh'  => 'Picture Reading',
    'batmya'      => 'Comprehension',
    'dictionary'  => 'Dictionary Skills',
    'nameof'      => 'Names of Things',
    // Intelligence
    'mirror'      => 'Mirror Image',
    'vargi'       => 'Odd One Out',
    'smalika'     => 'Number Series',
    'cmalika'     => 'Figure Series',
    'diagram'     => 'Diagrams',
    'gats'        => 'Number Patterns',
    'gatw'        => 'Word Patterns',
    'water'       => 'Water Reflection',
    'bhashik'     => 'Verbal Reasoning',
    'sankhya'     => 'Number Series',
    'samsabandh'  => 'Relationships (Analogy)',
    'tarksangati' => 'Logical Sequence',
    'chuk'        => 'Odd One Out',
    'vargikaran'  => 'Classification',
];

/* ── Extract questions from HTML (reuse helpers from seed script) ── */
function ef(string $block, string $key): string {
    if (!preg_match('/' . preg_quote($key, '/') . '\s*:\s*(?:"((?:[^"\\\\]|\\\\.)*)"|\'((?:[^\'\\\\]|\\\\.)*)\')/', $block, $m)) return '';
    return stripslashes($m[1] !== '' ? $m[1] : ($m[2] ?? ''));
}
function eaf(string $block, string $key): array {
    if (!preg_match('/' . preg_quote($key, '/') . '\s*:\s*\[([^\]]*)\]/s', $block, $m)) return [];
    preg_match_all('/(?:"((?:[^"\\\\]|\\\\.)*)"|\'((?:[^\'\\\\]|\\\\.)*)\')/', $m[1], $it);
    return array_map(fn($i,$v) => stripslashes($v !== '' ? $v : ($it[2][$i] ?? '')), array_keys($it[1]), $it[1]);
}
function splitBlocks(string $js): array {
    $js = preg_replace('/\/\/[^\n]*/', '', $js);
    $js = preg_replace('/\/\*.*?\*\//s', '', $js);
    $js = preg_replace('/`[^`]*`/s', '""', $js);
    $objs=[]; $depth=0; $start=null; $len=strlen($js); $inStr=false; $sc='';
    for($i=0;$i<$len;$i++){
        $c=$js[$i];
        if($inStr){if($c==='\\'){$i++;continue;}if($c===$sc)$inStr=false;continue;}
        if($c==='"'||$c==="'"){$inStr=true;$sc=$c;continue;}
        if($c==='{'){if($depth===0)$start=$i;$depth++;}
        elseif($c==='}'){$depth--;if($depth===0&&$start!==null){$objs[]=substr($js,$start,$i-$start+1);$start=null;}}
    }
    return $objs;
}
function extractQTexts(string $html): array {
    if (!preg_match('/(?:const\s+)?(?:questions\d*|riddles)\s*=\s*\[/s', $html, $m, PREG_OFFSET_CAPTURE)) return [];
    $pos = $m[0][1] + strlen($m[0][0]) - 1;
    $depth=0;$len=strlen($html);$arrStart=null;$inStr=false;$sc='';$jsArr='';
    for($i=$pos;$i<$len;$i++){
        $c=$html[$i];
        if($inStr){if($c==='\\'){$i++;continue;}if($c===$sc)$inStr=false;continue;}
        if($c==='`'){$i++;while($i<$len&&$html[$i]!=='`'){if($html[$i]==='\\')$i++;$i++;}continue;}
        if($c==='"'||$c==="'"){$inStr=true;$sc=$c;continue;}
        if($c==='['){$depth++;if($depth===1)$arrStart=$i;}
        elseif($c===']'){$depth--;if($depth===0){$jsArr=substr($html,$arrStart,$i-$arrStart+1);break;}}
    }
    if(!$jsArr) return [];
    $texts=[];
    foreach(splitBlocks($jsArr) as $block){
        $q=ef($block,'question_mr')?:ef($block,'question_en')?:ef($block,'question')?:ef($block,'riddle');
        if($q) $texts[]=trim($q);
    }
    return $texts;
}

/* ── Main loop ── */
$htmlDir = '/tmp/zipdata/assets/';
$allFiles = glob($htmlDir . '*.html');
sort($allFiles);

$updateStmt = $db->prepare('UPDATE quiz_questions SET topic = ? WHERE question = ? AND topic = \'\'');

$updated = 0;
$processed = 0;

foreach ($allFiles as $filePath) {
    $base = basename($filePath, '.html');

    // Identify topic from prefix
    $topic = null;
    foreach ($TOPIC_NAMES as $prefix => $name) {
        if (stripos($base, $prefix) === 0 && preg_match('/\d/', $base)) {
            $topic = $name;
            break;
        }
    }
    if (!$topic) continue;

    $html   = file_get_contents($filePath);
    $texts  = extractQTexts($html);
    $processed++;

    foreach ($texts as $qText) {
        if (!$qText) continue;
        $updateStmt->execute([$topic, $qText]);
        $updated += $updateStmt->rowCount();
    }
}

echo "Processed {$processed} files\n";
echo "Updated {$updated} questions with topics\n\n";

// Show topic distribution
$rows = $db->query(
    "SELECT subject, topic, COUNT(*) as cnt FROM quiz_questions WHERE topic != '' GROUP BY subject, topic ORDER BY subject, cnt DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$lastSub = '';
foreach ($rows as $r) {
    if ($r['subject'] !== $lastSub) { echo "\n=== {$r['subject']} ===\n"; $lastSub = $r['subject']; }
    echo "  {$r['topic']}: {$r['cnt']}\n";
}
$blank = $db->query("SELECT COUNT(*) FROM quiz_questions WHERE topic = ''")->fetchColumn();
echo "\nStill blank: {$blank}\n";
