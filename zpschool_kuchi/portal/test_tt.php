<?php
require_once __DIR__ . '/api/common/timetable_helper.php';

$res = getClassTimetableFromTachan(6);
echo "Class 6 Timetable:\n";
echo "Teacher: " . $res['teacher_name'] . "\n";
echo "Class: " . $res['class_name'] . "\n";
echo "Source: " . $res['source'] . "\n\n";

echo "Monday:\n";
print_r($res['timetable']['सोमवार'] ?? []);

echo "\nFormatted Table Row 0:\n";
print_r($res['data'][0] ?? []);
