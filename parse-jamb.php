<?php

$data = json_decode(file_get_contents('jamb-result.json'), true);

$html = $data['browserHtml'] ?? '';

if (!$html) {
    die("No browser HTML returned\n");
}

$dom = new DOMDocument();

libxml_use_internal_errors(true);
$dom->loadHTML($html);
libxml_clear_errors();

$xpath = new DOMXPath($dom);

$name = trim($xpath->evaluate(
    'string(//*[@id="dvdisplay"]//*[contains(@class,"profile-info")]/h1)'
));

$institution = trim($xpath->evaluate(
    'string(//*[@id="dvdisplay"]//*[contains(@class,"profile-nav")]//li[1]//span)'
));

$programme = trim($xpath->evaluate(
    'string(//*[@id="dvdisplay"]//*[contains(@class,"profile-nav")]//li[2]//span)'
));

$status = trim($xpath->evaluate(
    'string(//*[@id="dvdisplay"]//*[contains(@class,"profile-info")]//strong[contains(normalize-space(.),"Status")]/following-sibling::*[1])'
));

$verified = str_contains(
    strtolower($status),
    'congratulations'
);

$result = [
    'verified' => $verified,
    'name' => $name,
    'institution' => $institution,
    'programme' => $programme,
    'status' => $status
];

echo json_encode(
    $result,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
) . PHP_EOL;
