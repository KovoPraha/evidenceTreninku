<?php
declare(strict_types=1);
// Isolated temporary session only: no application connection, no database or HTTP writes.
require_once dirname(__DIR__, 2).'/includes/training_submission.php';
$directory = sys_get_temp_dir().'/training-submission-session-'.bin2hex(random_bytes(8));
mkdir($directory,0700);
session_save_path($directory);
session_id(bin2hex(random_bytes(16)));
try {
    session_start();
    $_SESSION=['trener_id'=>10];
    $key=trainingSubmissionIssue($_SESSION,10,0);
    trainingSubmissionBegin($_SESSION,$key,10,0);
    trainingSubmissionPersistPending($key,10,0);
    session_write_close();
    session_start();
    if(trainingSubmissionEntry($_SESSION,$key,10,0)['state']!=='pending')throw new RuntimeException('Pending not persisted');
    try {trainingSubmissionBegin($_SESSION,$key,10,0);throw new LogicException('Replay accepted');}catch(RuntimeException $e){if($e instanceof LogicException)throw $e;}
    trainingSubmissionComplete($_SESSION,$key,42,'index.php');
    session_write_close();
    session_start();
    $result=trainingSubmissionEntry($_SESSION,$key,10,0);
    if($result['state']!=='completed'||$result['training_id']!==42||$result['redirect']!=='index.php')throw new RuntimeException('Completed not persisted');
    echo "PASS: pending persisted across request boundaries; pending retry denied; completed replay preserved.\n";
} finally {
    if(session_status()===PHP_SESSION_ACTIVE)session_destroy();
    rmdir($directory);
}
