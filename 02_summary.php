<?php
require 'vendor/autoload.php';

$basePath = __DIR__ . '/../landchg.tcd.gov.tw';
$projectYears = [];
$y = date('Y') - 1911;
for ($i = $y; $i > 92; $i--) {
    $projectYears[] = $i;
}
$cities = ['基隆市', '臺北市', '新北市', '桃園市', '新竹縣', '新竹市', '苗栗縣', '臺中市', '南投縣', '彰化縣', '雲林縣', '嘉義縣', '嘉義市', '臺南市', '高雄市', '屏東縣', '宜蘭縣', '花蓮縣', '臺東縣', '金門縣', '澎湖縣', '連江縣'];

$pool = [];
foreach (glob($basePath . '/docs/csv/points/*/*.csv') as $csvFile) {
    $p = pathinfo($csvFile);
    $parts = explode('/', $p['dirname']);
    $k = array_pop($parts);
    if ($k !== 'summary') {
        $fh = fopen($csvFile, 'r');
        $head = fgetcsv($fh, 2048);
        while ($line = fgetcsv($fh, 2048)) {
            $data = array_combine($head, $line);
            $data['變異類型'] = trim($data['變異類型']);
            if (empty($data['變異類型'])) {
                continue;
            }
            if (!isset($pool[$data['變異類型']])) {
                $pool[$data['變異類型']] = [];
                foreach ($projectYears as $projectYear) {
                    $pool[$data['變異類型']][$projectYear] = [];
                    foreach ($cities as $city) {
                        $pool[$data['變異類型']][$projectYear][$city] = 0;
                    }
                }
            }
            if (isset($pool[$data['變異類型']][$k][$data['權責單位']])) {
                ++$pool[$data['變異類型']][$k][$data['權責單位']];
            }
        }
    }
}

$sumPath = $basePath . '/docs/csv/summary';
if (!file_exists($sumPath)) {
    mkdir($sumPath, 0777, true);
}
foreach ($pool as $type => $lv1) {
    $headerDone = false;
    $fh = fopen($sumPath . '/' . $type . '.csv', 'w');
    foreach ($lv1 as $y => $line) {
        if (false === $headerDone) {
            $headerDone = true;
            fputcsv($fh, array_merge(['year'], array_keys($line)));
        }
        fputcsv($fh, array_merge([$y], $line));
    }
}
