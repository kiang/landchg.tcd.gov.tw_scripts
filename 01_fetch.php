<?php
require 'vendor/autoload.php';

$basePath = __DIR__ . '/../landchg.tcd.gov.tw';

$projectYears = ['111', '110', '109', '108', '107', '106', '105', '104', '103', '102', '101', '100', '99', '98', '97', '96', '95', '94', '93'];
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
            $crawler = $client->submit($form, ['City' => $city, 'ProjectYear' => $projectYear]);
            file_put_contents($targetFile, $client->getResponse()->getContent());
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
