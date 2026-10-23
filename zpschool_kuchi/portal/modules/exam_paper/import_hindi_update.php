<?php
/**
 * Hindi Update Importer - Web UI + Engine
 * Reads update_hindi_2026.sql and imports into SQLite exam_paper database.
 */
require_once __DIR__ . '/includes/functions.php';
if (PHP_SAPI !== 'cli') { $access = require_module_access('que'); }

/* -- VALUE PARSER ------------------------------------------------- */
function parse_mysql_values(string $vs): array {
    $rows=[]; $len=strlen($vs); $i=0;
    while ($i<$len) {
        while ($i<$len && in_array($vs[$i],[' ',"\n","\r","\t",','],true)) $i++;
        if ($i>=$len || $vs[$i]!=='(') break;
        $i++; $row=[];
        while ($i<$len && $vs[$i]!==')') {
            while ($i<$len && in_array($vs[$i],[',',' ',"\n","\r","\t"],true)) $i++;
            if ($i>=$len || $vs[$i]===')') break;
            if ($vs[$i]==="'") {
                $i++; $str='';
                while ($i<$len) {
                    if ($vs[$i]==='\\' && ($i+1)<$len) {
                        $c=$vs[$i+1];
                        $str.=($c==="'")?("'"):(($c==='\\')?('\\'):(($c==='n')?("\n"):(($c==='r')?("\r"):(($c==='t')?("\t"):$c))));
                        $i+=2;
                    } elseif ($vs[$i]==="'" && ($i+1)<$len && $vs[$i+1]==="'") { $str.="'"; $i+=2; }
                    elseif ($vs[$i]==="'") { $i++; break; }
                    else { $str.=$vs[$i++]; }
                }
                $row[]=$str;
            } elseif (strtoupper(substr($vs,$i,4))==='NULL') { $row[]=null; $i+=4; }
            else {
                $n=''; while ($i<$len&&$vs[$i]!==','&&$vs[$i]!==')') $n.=$vs[$i++];
                $n=trim($n); $row[]=is_numeric($n)?(strpos($n,'.')!==false?(float)$n:(int)$n):$n;
            }
        }
        if ($i<$len&&$vs[$i]===')') $i++;
        if ($row) $rows[]=$row;
    }
    return $rows;
}

function extract_inserts(string $sql, string $tbl): array {
    $all=[];
    $pat = '/^INSERT\\s+INTO\\s+`?' . preg_quote($tbl,'/') . '`?\\s+VALUES\\s+(.+);?\\s*$/m';
    if (preg_match_all($pat,$sql,$m))
        foreach ($m[1] as $vs) foreach (parse_mysql_values($vs) as $r) $all[]=$r;
    return $all;
}

/* -- IMPORT RUNNER ------------------------------------------------- */
function run_import(?string $f): array {
    $log=[]; $counts=[];
    if (!$f||!file_exists($f)) return ['ok'=>false,'error'=>'SQL file not found: '.$f,'log'=>[]];
    $sql=file_get_contents($f);
    if ($sql===false || $sql==='') return ['ok'=>false,'error'=>'Cannot read file.','log'=>[]];
    try {
        $books=array_values(array_filter(extract_inserts($sql,'ep_books'),static fn($r)=>(int)$r[0]===64));
        $bookChapters=array_values(array_filter(extract_inserts($sql,'ep_book_chapters'),static fn($r)=>(int)$r[1]===64));
        $bookQuestions=array_values(array_filter(extract_inserts($sql,'ep_book_questions'),static fn($r)=>(int)$r[1]===64));
        $topicPacks=array_values(array_filter(extract_inserts($sql,'ep_topic_packs'),static fn($r)=>(int)$r[1]===64));
        $subjects=array_values(array_filter(extract_inserts($sql,'ep_subjects'),static fn($r)=>str_contains((string)$r[2],'हिंदी')||stripos((string)$r[2],'hindi')!==false));
        if (!$books || !$bookChapters || !$bookQuestions || !$subjects) {
            throw new RuntimeException('The SQL file does not contain the expected Hindi textbook data.');
        }
        $subjectIds=array_fill_keys(array_map(static fn($r)=>(int)$r[0],$subjects),true);
        $templates=array_values(array_filter(extract_inserts($sql,'ep_templates'),static fn($r)=>isset($subjectIds[(int)$r[1]])));
        $templateIds=array_fill_keys(array_map(static fn($r)=>(int)$r[0],$templates),true);
        $chapters=array_values(array_filter(extract_inserts($sql,'ep_chapters'),static fn($r)=>isset($subjectIds[(int)$r[1]])));
        $chapterIds=array_fill_keys(array_map(static fn($r)=>(int)$r[0],$chapters),true);
        $templateQuestions=array_values(array_filter(extract_inserts($sql,'ep_template_questions'),static fn($r)=>isset($templateIds[(int)$r[1]])));
        $questions=array_values(array_filter(extract_inserts($sql,'ep_questions'),static fn($r)=>isset($chapterIds[(int)$r[1]])));
        if (!$templates || !$chapters || !$templateQuestions || !$questions) {
            throw new RuntimeException('The SQL file is missing Hindi paper-builder templates, chapters, or questions.');
        }

        $db=ep_db();
        $db->beginTransaction();
        $deleteIds=static function(PDO $db,string $table,string $column,array $ids):void {
            if (!$ids) return;
            $idList=array_map('intval',array_keys($ids));
            $db->prepare("DELETE FROM {$table} WHERE {$column} IN (".implode(',',array_fill(0,count($idList),'?')).')')->execute($idList);
        };
        $deleteIds($db,'ep_questions','chapter_id',$chapterIds);
        $deleteIds($db,'ep_template_questions','template_id',$templateIds);
        $deleteIds($db,'ep_templates','subject_id',$subjectIds);
        $deleteIds($db,'ep_chapters','subject_id',$subjectIds);
        $deleteIds($db,'ep_subjects','subject_id',$subjectIds);
        $db->exec('DELETE FROM ep_book_questions WHERE book_id=64');
        $db->exec('DELETE FROM ep_book_chapters WHERE book_id=64');
        $db->exec('DELETE FROM ep_topic_packs WHERE book_id=64');
        $db->exec('DELETE FROM ep_books WHERE book_id=64');
        $log[]='Replaced Hindi textbook rows and Hindi bridge rows only; all other subjects and saved papers were preserved.';
        // ep_books (MySQL 11 cols -> SQLite 7)
        $s=$db->prepare('INSERT OR REPLACE INTO ep_books (book_id,standard,subject,medium,title,file,pages) VALUES (?,?,?,?,?,?,?)');
        foreach ($books as $r) $s->execute([$r[0],$r[1],$r[2],$r[3],$r[4],$r[5],$r[6]]);
        $counts['ep_books']=count($books); $log[]='ep_books: '.count($books);
        // ep_book_chapters (MySQL 8 cols -> SQLite 6)
        $s=$db->prepare('INSERT OR REPLACE INTO ep_book_chapters (chapter_id,book_id,chapter_no,title,start_page,end_page) VALUES (?,?,?,?,?,?)');
        foreach ($bookChapters as $r) $s->execute([$r[0],$r[1],$r[2],$r[3],$r[4]??null,$r[5]??null]);
        $counts['ep_book_chapters']=count($bookChapters); $log[]='ep_book_chapters: '.count($bookChapters);
        // ep_book_questions (MySQL 23 cols -> SQLite 18)
        $s=$db->prepare('INSERT OR REPLACE INTO ep_book_questions
            (bq_id,book_id,chapter_id,standard,subject,lang,page,block,instruction,qtype,
             text,item_no,needs_figure,page_image,options_json,answer,ai_cleaned,figure_image)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($bookQuestions as $r)
            $s->execute([$r[0],$r[1],$r[2],$r[3],$r[4],$r[5],$r[6],$r[7],$r[8],$r[9],
                         $r[10],$r[11],$r[12],$r[13],$r[14],$r[16]??null,$r[17]??0,$r[18]??null]);
        $counts['ep_book_questions']=count($bookQuestions); $log[]='ep_book_questions: '.count($bookQuestions);
        // ep_topic_packs (9 cols)
        $s=$db->prepare('INSERT OR REPLACE INTO ep_topic_packs (chapter_id,book_id,standard,subject,lang,title,notes_json,quiz_json,model) VALUES (?,?,?,?,?,?,?,?,?)');
        foreach ($topicPacks as $r) $s->execute([$r[0],$r[1],$r[2],$r[3],$r[4],$r[5],$r[6],$r[7],$r[8]??null]);
        $counts['ep_topic_packs']=count($topicPacks); $log[]='ep_topic_packs: '.count($topicPacks);
        // Hindi ep_subjects only
        $s=$db->prepare('INSERT OR REPLACE INTO ep_subjects (subject_id,standard_id,name) VALUES (?,?,?)');
        foreach ($subjects as $r) $s->execute([$r[0],$r[1],$r[2]]);
        $counts['ep_subjects']=count($subjects); $log[]='ep_subjects: '.count($subjects);
        // ep_templates
        $s=$db->prepare('INSERT OR REPLACE INTO ep_templates (template_id,subject_id,title,total_marks,description) VALUES (?,?,?,?,?)');
        foreach ($templates as $r) $s->execute([$r[0],$r[1],$r[2],$r[3],$r[4]??null]);
        $counts['ep_templates']=count($templates); $log[]='ep_templates: '.count($templates);
        // ep_template_questions
        $s=$db->prepare('INSERT OR REPLACE INTO ep_template_questions (tq_id,template_id,question_title,marks,question_type,question_number,no_of_questions,sort_order) VALUES (?,?,?,?,?,?,?,?)');
        foreach ($templateQuestions as $r) $s->execute([$r[0],$r[1],$r[2],$r[3],$r[4],$r[5],$r[6],$r[7]]);
        $counts['ep_template_questions']=count($templateQuestions); $log[]='ep_template_questions: '.count($templateQuestions);
        // ep_chapters
        $s=$db->prepare('INSERT OR REPLACE INTO ep_chapters (chapter_id,subject_id,name,sort_order) VALUES (?,?,?,?)');
        foreach ($chapters as $r) $s->execute([$r[0],$r[1],$r[2],$r[3]??0]);
        $counts['ep_chapters']=count($chapters); $log[]='ep_chapters: '.count($chapters);
        // Hindi bridge questions only
        $s=$db->prepare('INSERT OR REPLACE INTO ep_questions
            (question_id,chapter_id,question_type,question_number,marks,difficulty,source,
             passage,markup,markup2,markup3,answer_markup,answer_markup2,
             has_image,has_image2,has_answer_image,sub_questions) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($questions as $r)
            $s->execute([$r[0],$r[1],$r[2],$r[3],$r[4],$r[5],$r[6],
                         $r[7],$r[8],$r[9],$r[10],$r[11],$r[12],$r[13],$r[14],$r[15],$r[16]??null]);
        $counts['ep_questions']=count($questions); $log[]='ep_questions: '.count($questions);
        $db->commit(); $db->exec('PRAGMA foreign_keys=ON;'); $log[]='Done.';
        return ['ok'=>true,'counts'=>$counts,'log'=>$log];
    } catch (Throwable $e) {
        try { ep_db()->rollBack(); } catch (Throwable $re) {}
        return ['ok'=>false,'error'=>$e->getMessage(),'log'=>$log];
    }
}

/* -- HTML OUTPUT --------------------------------------------------- */
$sqlFile = null;
$qpDataDir = dirname(__DIR__, 3) . '/QP data';
$candidates = [
    __DIR__ . '/data/hindi_update_2026.sql',
    $qpDataDir . '/hindi_update_extracted/hindi_update/update_hindi_2026.sql',
    $qpDataDir . '/hindi_update_extracted2/hindi_update/update_hindi_2026.sql',
];
foreach ($candidates as $c) {
    if (file_exists($c)) { $sqlFile = realpath($c); break; }
}
$epPage = 'settings'; $epTitle = 'Hindi Update Import'; $epScripts = [];
$result = null;
if ($_SERVER['REQUEST_METHOD']==='POST' && !empty($_POST['confirm']))
    $result = run_import($sqlFile);
require __DIR__ . '/includes/header.php';
?>
<div class="container-fluid py-3" style="max-width:860px">
<h3 class="mb-1"><i class="bi bi-translate me-2 text-danger"></i>Hindi Update Import</h3>
<p class="text-muted small mb-3">
  Imports <strong>हिंदी सुलभभारती</strong> (Std&nbsp;6) textbook bank + bridge subjects for
  <b>Create Paper</b> &amp; <b>Assessment Builder</b> dropdowns. Safe to re-run.
</p>
<?php if ($result): ?>
  <?php if ($result['ok']): ?>
  <div class="alert alert-success">
    <h5 class="mb-2"><i class="bi bi-check-circle-fill me-2"></i>Import successful!</h5>
    <ul class="mb-0 small">
      <?php foreach ($result['counts'] as $k=>$v): ?>
        <li><strong><?=h($k)?>:</strong> <?=(int)$v?> rows</li>
      <?php endforeach ?>
    </ul>
    <?php if (!empty($result['log'])): ?>
    <details class="mt-2"><summary class="small text-muted">Full log</summary>
      <pre class="small mt-1" style="max-height:200px;overflow:auto"><?=h(implode("\n",$result['log']))?></pre>
    </details>
    <?php endif ?>
  </div>
  <div class="d-flex gap-2 mt-2">
    <a href="create_paper.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Open Create Paper</a>
    <a href="assessment_paper.php" class="btn btn-outline-primary"><i class="bi bi-journal-check me-1"></i>Assessment Builder</a>
  </div>
  <?php else: ?>
  <div class="alert alert-danger">
    <h5 class="mb-1"><i class="bi bi-x-circle-fill me-2"></i>Import failed</h5>
    <pre class="small mb-0"><?=h($result['error'])?></pre>
    <?php if (!empty($result['log'])): ?>
    <details class="mt-2"><summary class="small">Log so far</summary>
      <pre class="small mt-1" style="max-height:200px;overflow:auto"><?=h(implode("\n",$result['log']))?></pre>
    </details>
    <?php endif ?>
  </div>
  <?php endif ?>

<?php elseif (!$sqlFile): ?>
  <div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <strong>SQL file not found.</strong> Extract <code>hindi_update.zip</code> from the QP data folder first.<br>
    <small class="text-muted">Expected at: <code><?=h(__DIR__.'/data/hindi_update_2026.sql')?></code></small>
  </div>
<?php else: ?>
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <p class="mb-1"><strong>File:</strong> <code><?=h($sqlFile)?></code> (<?=number_format(filesize($sqlFile)/1024,1)?>&nbsp;KB)</p>
      <p class="mb-1 text-muted small mt-2">This will import / update:</p>
      <ul class="small mb-3">
        <li>📚 <strong>ep_books</strong> – Book 64: हिंदी सुलभभारती Std&nbsp;6</li>
        <li>📑 <strong>ep_book_chapters</strong> – 18 chapters (सैर … वह देश कौन-सा है?)</li>
        <li>❓ <strong>ep_book_questions</strong> – Textbook questions for Assessment builder</li>
        <li>🗂 <strong>ep_topic_packs</strong> – Chapter notes + quizzes</li>
        <li>📋 <strong>ep_subjects</strong> – Hindi textbook subjects only for Create Paper <b>dropdown</b></li>
        <li>📝 <strong>ep_templates</strong> – Ready 40-mark paper templates</li>
        <li>🔢 <strong>ep_template_questions</strong> – Template sections</li>
        <li>📂 <strong>ep_chapters</strong> – Bridge chapters for Create Paper</li>
        <li>❓ <strong>ep_questions</strong> – Hindi bridge questions only</li>
      </ul>
      <div class="alert alert-info py-2 small mb-3">
        <i class="bi bi-info-circle me-1"></i>
        Only Hindi textbook and Hindi bridge rows are replaced. Other subjects, question-bank data, and saved papers are preserved.
      </div>
      <form method="post">
        <input type="hidden" name="confirm" value="1">
        <button type="submit" class="btn btn-danger btn-lg">
          <i class="bi bi-cloud-upload me-1"></i> Import Hindi Data Now
        </button>
        <a href="settings.php" class="btn btn-outline-secondary ms-2">Cancel</a>
      </form>
    </div>
  </div>
<?php endif ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
