<?php
require 'vendor/autoload.php';

$basePath = __DIR__ . '/../landchg.tcd.gov.tw';

$projectYears = [date('Y') - 1911];
$cities = ['基隆市', '臺北市', '新北市', '桃園市', '新竹縣', '新竹市', '苗栗縣', '臺中市', '南投縣', '彰化縣', '雲林縣', '嘉義縣', '嘉義市', '臺南市', '高雄市', '屏東縣', '宜蘭縣', '花蓮縣', '臺東縣', '金門縣', '澎湖縣', '連江縣'];

use Goutte\Client;

$client = new Client();

$crawler = $client->request('GET', 'https://landchg.tcd.gov.tw/Module/RWD/Web/pub_exhibit.aspx');
$form = $crawler->filter('form')->form();

$domDocument = new \DOMDocument;
$ff = $domDocument->createElement('input');
$ff->setAttribute('name', '__EVENTTARGET');
$ff->setAttribute('value', 'ctl00$page_content$ChgPointMarker');
$formInput = new \Symfony\Component\DomCrawler\Field\InputFormField($ff);
$form->set($formInput);

$ff = $domDocument->createElement('input');
$ff->setAttribute('name', '__EVENTARGUMENT');
$ff->setAttribute('value', '');
$formInput = new \Symfony\Component\DomCrawler\Field\InputFormField($ff);
$form->set($formInput);

$ff = $domDocument->createElement('input');
$ff->setAttribute('name', '__SCROLLPOSITIONX');
$ff->setAttribute('value', '0');
$formInput = new \Symfony\Component\DomCrawler\Field\InputFormField($ff);
$form->set($formInput);

$ff = $domDocument->createElement('input');
$ff->setAttribute('name', '__SCROLLPOSITIONY');
$ff->setAttribute('value', '433');
$formInput = new \Symfony\Component\DomCrawler\Field\InputFormField($ff);
$form->set($formInput);

$ff = $domDocument->createElement('input');
$ff->setAttribute('name', 'h_lat');
$ff->setAttribute('value', '23.042379299657025');
$formInput = new \Symfony\Component\DomCrawler\Field\InputFormField($ff);
$form->set($formInput);

$ff = $domDocument->createElement('input');
$ff->setAttribute('name', 'h_lng');
$ff->setAttribute('value', '120.40802721756668');
$formInput = new \Symfony\Component\DomCrawler\Field\InputFormField($ff);
$form->set($formInput);

$ff = $domDocument->createElement('input');
$ff->setAttribute('name', 'h_zoom');
$ff->setAttribute('value', '11');
$formInput = new \Symfony\Component\DomCrawler\Field\InputFormField($ff);
$form->set($formInput);

$ff = $domDocument->createElement('input');
$ff->setAttribute('name', 'ProjectYear');
$ff->setAttribute('value', '111');
$formInput = new \Symfony\Component\DomCrawler\Field\InputFormField($ff);
$form->set($formInput);

$ff = $domDocument->createElement('input');
$ff->setAttribute('name', 'City');
$ff->setAttribute('value', '桃園市');
$formInput = new \Symfony\Component\DomCrawler\Field\InputFormField($ff);
$form->set($formInput);

$ff = $domDocument->createElement('input');
$ff->setAttribute('name', 'selectMap');
$ff->setAttribute('value', '3');
$formInput = new \Symfony\Component\DomCrawler\Field\InputFormField($ff);
$form->set($formInput);

$ff = $domDocument->createElement('input');
$ff->setAttribute('name', 'PublicImg');
$ff->setAttribute('value', '-1');
$formInput = new \Symfony\Component\DomCrawler\Field\InputFormField($ff);
$form->set($formInput);

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
        $crawler = $client->submit($form, ['City' => $city, 'ProjectYear' => $projectYear]);
        file_put_contents($targetFile, $client->getResponse()->getContent());
        $csvFile = $dataPath . '/' . $city . '.csv';
        
        // Load existing CSV data
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
                        // Existing record - keep created time and check for changes in first 6 fields
                        if (isset($existingData[$pointId]['created'])) {
                            $dataLine['created'] = $existingData[$pointId]['created'];
                        } else {
                            $dataLine['created'] = $currentTime;
                        }
                        
                        // Compare first 6 fields: 變異點編號, 權責單位, 查證結果, 變異類型, latitude, longitude
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
                            // No changes - keep existing modified timestamp
                            $dataLine['modified'] = isset($existingData[$pointId]['modified']) ? $existingData[$pointId]['modified'] : '';
                        }
                    } else {
                        // New record - add created time
                        $dataLine['created'] = $currentTime;
                        $dataLine['modified'] = '';
                    }
                    
                    $newData[$pointId] = $dataLine;
                }
            }
        }
        
        // Sort by 變異點編號 ASC
        ksort($newData);
        
        // Write to CSV file
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
