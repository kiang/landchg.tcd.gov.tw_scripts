<?php

$basePath = __DIR__ . '/../landchg.tcd.gov.tw';
$config = require __DIR__ . '/config.php';
$conn = new PDO('pgsql:host=localhost;dbname=' . $config['db'], $config['user'], $config['password']);

$oFh = [];
foreach (glob($basePath . '/docs/csv/points/*/*.csv') as $csvFile) {
    $p = pathinfo($csvFile);
    $parts = explode('/', $p['dirname']);
    $year = array_pop($parts);
    $fh = fopen($csvFile, 'r');
    $head = fgetcsv($fh, 4096);
    while ($line = fgetcsv($fh, 4096)) {
        $data = array_combine($head, $line);
        $lnglat = "{$data['longitude']} {$data['latitude']}";
        if (empty($data['longitude'])) {
            continue;
        }
        $sql = "SELECT villcode FROM {$config['table']} AS cunli WHERE ST_Intersects('SRID=4326;POINT({$lnglat})'::geometry, cunli.geom)";
        $rs = $conn->query($sql);
        if ($rs) {
            $row = $rs->fetch(PDO::FETCH_ASSOC);
        }
        if (!empty($row['villcode'])) {
            if (!isset($oFh[$year])) {
                $oFh[$year] = fopen($basePath . "/docs/csv/cunli_lnglat/{$year}.csv", 'w');
                fputcsv($oFh[$year], ['villcode', '經度', '緯度']);
            }
            fputcsv($oFh[$year], [$row['villcode'], $data['longitude'], $data['latitude']]);
        }
    }
}