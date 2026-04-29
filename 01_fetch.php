<?php
$basePath = __DIR__ . '/../landchg.tcd.gov.tw';

$projectYears = ['111', '110', '109', '108', '107', '106', '105', '104', '103', '102', '101', '100', '99', '98', '97', '96', '95', '94', '93'];
$cities = ['基隆市', '臺北市', '新北市', '桃園市', '新竹縣', '新竹市', '苗栗縣', '臺中市', '南投縣', '彰化縣', '雲林縣', '嘉義縣', '嘉義市', '臺南市', '高雄市', '屏東縣', '宜蘭縣', '花蓮縣', '臺東縣', '金門縣', '澎湖縣', '連江縣'];

$baseUrl = 'https://landchg.nlma.gov.tw/Module/RWD/Web/pub_exhibit.aspx';

$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/landchg_cookies.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/landchg_cookies.txt');

curl_setopt($ch, CURLOPT_URL, $baseUrl);
$initialHtml = curl_exec($ch);

$yearPool = [];
foreach ($projectYears as $projectYear) {
    $rawPath = $basePath . '/raw/' . $projectYear;
    if (!file_exists($rawPath)) {
        mkdir($rawPath, 0777, true);
    }
    $dataPath = $basePath . '/docs/csv/points/' . $projectYear;
    if (!file_exists($dataPath)) {
        mkdir($dataPath, 0777, true);
    }
    foreach ($cities as $city) {
        $targetFile = $rawPath . '/' . $city . '.html';
        if (!file_exists($targetFile)) {
            $viewState = '';
            $eventValidation = '';
            $viewStateGenerator = '';
            $sourceHtml = $initialHtml;

            if (preg_match('/id="__VIEWSTATE" value="([^"]*)"/', $sourceHtml, $m)) {
                $viewState = $m[1];
            }
            if (preg_match('/id="__EVENTVALIDATION" value="([^"]*)"/', $sourceHtml, $m)) {
                $eventValidation = $m[1];
            }
            if (preg_match('/id="__VIEWSTATEGENERATOR" value="([^"]*)"/', $sourceHtml, $m)) {
                $viewStateGenerator = $m[1];
            }

            $postData = http_build_query([
                '__VIEWSTATE' => $viewState,
                '__VIEWSTATEGENERATOR' => $viewStateGenerator,
                '__EVENTVALIDATION' => $eventValidation,
                'ctl00$page_content$ProjectYear' => $projectYear,
                'ctl00$page_content$City' => $city,
                'ctl00$page_content$btnSearch' => '查詢',
            ]);

            curl_setopt($ch, CURLOPT_URL, $baseUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            $responseHtml = curl_exec($ch);

            file_put_contents($targetFile, $responseHtml);
            $initialHtml = $responseHtml;
            echo "{$targetFile}\n";
        }
        $fh = fopen($dataPath . '/' . $city . '.csv', 'w');
        $headerDone = false;

        $content = file_get_contents($targetFile);

        $pos = strpos($content, 'function markerBind');
        if (false !== $pos) {
            $posEnd = strpos($content, '</script>', $pos);
            $part = substr($content, $pos, $posEnd - $pos);
            $lines = explode(');', $part);
            foreach ($lines as $line) {
                $line = str_replace('\'', '', $line);
                $parts = explode(',', $line);
                if (count($parts) === 4) {
                    $dataLine = [];
                    $parts2 = explode('<br/>', $parts[2]);
                    foreach ($parts2 as $part) {
                        $parts3 = explode('：', $part);
                        $dataLine[$parts3[0]] = $parts3[1];
                    }
                    if (!isset($dataLine['變異類型'])) {
                        $dataLine['變異類型'] = '';
                    } else {
                        if (!isset($yearPool[$dataLine['變異類型']])) {
                            $yearPool[$dataLine['變異類型']] = [];
                        }
                        if (!isset($yearPool[$dataLine['變異類型']][$projectYear])) {
                            $yearPool[$dataLine['變異類型']][$projectYear] = [];
                            foreach ($cities as $key) {
                                $yearPool[$dataLine['變異類型']][$projectYear][$key] = 0;
                            }
                        }
                        ++$yearPool[$dataLine['變異類型']][$projectYear][$city];
                    }

                    $dataLine['latitude'] = trim(substr($parts[0], strrpos($parts[0], '(') + 1));
                    $dataLine['longitude'] = trim($parts[1]);
                    if (false === $headerDone) {
                        $headerDone = true;
                        fputcsv($fh, array_keys($dataLine));
                    }
                    fputcsv($fh, $dataLine);
                }
            }
        }
    }
}

curl_close($ch);

$sumPath = $basePath . '/data/csv/summary';
if (!file_exists($sumPath)) {
    mkdir($sumPath, 0777, true);
}
foreach ($yearPool as $type => $lv1) {
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
