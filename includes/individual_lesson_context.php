<?php
declare(strict_types=1);

const INDIVIDUAL_LESSON_CONTEXT_LESSON='individual_lesson';
const INDIVIDUAL_LESSON_CONTEXT_VELODROME='public_velodrome';

function individualLessonContextAvailable(PDO $pdo):bool
{
    if((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){
        $statement=$pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='individualni_lekce' AND COLUMN_NAME='booking_context' LIMIT 1");
        $statement->execute();
        return(bool)$statement->fetchColumn();
    }
    foreach($pdo->query('PRAGMA table_info(individualni_lekce)')->fetchAll(PDO::FETCH_ASSOC)as$row)if((string)$row['name']==='booking_context')return true;
    return false;
}

function individualLessonContextCondition(PDO $pdo,string $alias,string $context):string
{
    if(preg_match('/^[a-z][a-z0-9_]*$/D',$alias)!==1)throw new InvalidArgumentException('Neplatný SQL alias lekce.');
    if(!in_array($context,[INDIVIDUAL_LESSON_CONTEXT_LESSON,INDIVIDUAL_LESSON_CONTEXT_VELODROME],true))throw new InvalidArgumentException('Neplatný kontext lekce.');
    return individualLessonContextAvailable($pdo)?$alias.".booking_context='".$context."'":'1=1';
}
