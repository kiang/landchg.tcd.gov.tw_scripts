<?php
$basePath = __DIR__ . '/../landchg.tcd.gov.tw';

$projectYears = [date('Y') - 1911];
$cities = ['基隆市', '臺北市', '新北市', '桃園市', '新竹縣', '新竹市', '苗栗縣', '臺中市', '南投縣', '彰化縣', '雲林縣', '嘉義縣', '嘉義市', '臺南市', '高雄市', '屏東縣', '宜蘭縣', '花蓮縣', '臺東縣', '金門縣', '澎湖縣', '連江縣'];

$baseUrl = 'https://landchg.tcd.gov.tw/Module/RWD/Web/pub_exhibit.aspx';

$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/landchg_cookies.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/landchg_cookies.txt');

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

        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_URL, $baseUrl);
        $sourceHtml = curl_exec($ch);

        $viewState = '';
        $eventValidation = '';
        $viewStateGenerator = '';

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

        $csvFile = $dataPath . '/' . $city . '.csv';

        $existingData = [];
        if (file_exists($csvFile)) {
            $fh = fopen($csvFile, 'r');
            $headers = fgetcsv($fh);
            while (($row = fgetcsv($fh)) !== false) {
                if (count($row) === count($headers)) {
                    $record = array_combine($headers, $row);
                    $existingData[$record['變異點編號']] = $record;
                }
            }
            fclose($fh);
        }

        $newData = [];
        $timezone = new DateTimeZone('Asia/Taipei');
        $currentTime = (new DateTime('now', $timezone))->format('Y-m-d H:i:s');

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
                    }

                    $dataLine['latitude'] = trim(substr($parts[0], strrpos($parts[0], '(') + 1));
                    $dataLine['longitude'] = trim($parts[1]);

                    $pointId = $dataLine['變異點編號'];
                    if (isset($existingData[$pointId])) {
                        if (isset($existingData[$pointId]['created'])) {
                            $dataLine['created'] = $existingData[$pointId]['created'];
                        } else {
                            $dataLine['created'] = $currentTime;
                        }

                        $fieldsToCompare = ['變異點編號', '權責單位', '查證結果', '變異類型', 'latitude', 'longitude'];
                        $hasChanges = false;

                        foreach ($fieldsToCompare as $field) {
                            $newValue = isset($dataLine[$field]) ? $dataLine[$field] : '';
                            $oldValue = isset($existingData[$pointId][$field]) ? $existingData[$pointId][$field] : '';
                            if ($newValue !== $oldValue) {
                                $hasChanges = true;
                                break;
                            }
                        }

                        if ($hasChanges) {
                            $dataLine['modified'] = $currentTime;
                        } else {
                            $dataLine['modified'] = isset($existingData[$pointId]['modified']) ? $existingData[$pointId]['modified'] : '';
                        }
                    } else {
                        $dataLine['created'] = $currentTime;
                        $dataLine['modified'] = '';
                    }

                    $newData[$pointId] = $dataLine;
                }
            }
        }

        ksort($newData);

        $fh = fopen($csvFile, 'w');
        if (!empty($newData)) {
            $firstRecord = reset($newData);
            fputcsv($fh, array_keys($firstRecord));
            foreach ($newData as $record) {
                fputcsv($fh, $record);
            }
        }
        fclose($fh);
    }
}

curl_close($ch);
