<?php
/**
 * Contact details in many real-world layouts: phone numbers, addresses, ZIP codes,
 * birth dates. All people and contact details are fictional.
 * Run: php server/tests/resume_contact_test.php
 */
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../services/resume/ResumeParser.php';

$failures = 0; $passes = 0;
function check(bool $ok, string $label, $detail = null) {
    global $failures, $passes;
    if ($ok) { $passes++; echo "  ok   $label\n"; return; }
    $failures++;
    echo "  FAIL $label" . ($detail !== null ? ' :: ' . json_encode($detail, JSON_UNESCAPED_UNICODE) : '') . "\n";
}
function personal(string $text): array {
    return (new ResumeParser())->parse($text)['personal'];
}
function v(array $p, string $k) { return $p[$k]['value'] ?? null; }

echo "Phone numbers\n";
$phoneCases = [
    ["Juan Cruz\n09171234567\njuan@example.com", '09171234567'],
    ["Juan Cruz\n+63 917 123 4567", '+63 917 123 4567'],
    ["Juan Cruz\n(+63) 917-123-4567", '(+63) 917-123-4567'],
    ["Juan Cruz\n+639171234567 | juan@example.com", '+639171234567'],
    ["Juan Cruz\n0917.123.4567", '0917.123.4567'],
    ["Juan Cruz\nCP No.: 0917 123 4567", '0917 123 4567'],
    ["Juan Cruz\nCellphone Number - 0998-765-4321", '0998-765-4321'],
    ["Juan Cruz\nTel. No. (044) 456-7890", '(044) 456-7890'],
    ["Juan Cruz\nLandline: (02) 8123 4567", '(02) 8123 4567'],
    ["Juan Cruz\nContact\n0 9 1 7 1 2 3 4 5 6 7", '0 9 1 7 1 2 3 4 5 6 7'],
    ["Juan Cruz\n📞 0917-123-4567 ✉ juan@example.com", '0917-123-4567'],
    ["Jane Doe\nPhone: +1 (415) 555-0132", '+1 (415) 555-0132'],
    ["Juan Cruz\nTelephone: 456-7890", '456-7890'],
    ["Dan Gan\n9 Wall St, New York, 10005, USA\n890-555-0401 · dan@example.com", '890-555-0401'],
];
foreach ($phoneCases as [$text, $expected]) {
    $p = personal($text);
    check(v($p, 'phone') === $expected, "phone: $expected", v($p, 'phone'));
}
$p = personal("Juan Cruz\nMobile: 0917 123 4567 / 0998 765 4321\nWork Experience\nCashier | ABC Store | 2019 - 2021");
check(v($p, 'phone') === '0917 123 4567' && v($p, 'alternate_phone') === '0998 765 4321', 'two numbers → phone + alternate phone', [v($p, 'phone'), v($p, 'alternate_phone')]);
$p = personal("Juan Cruz\nSSS No.: 34-1234567-8\nTIN: 123-456-789-000\nEducation\nBS Information Technology 2014 - 2018\nAddress: Gapan City");
check(v($p, 'phone') === null, 'government ID numbers and year ranges are not phones', v($p, 'phone'));

echo "Addresses\n";
$addressCases = [
    // [resume text, expected location, expected city, expected province]
    ["Juan Cruz\nAddress: Purok 3, Brgy. San Isidro, Cabanatuan City, Nueva Ecija", 'Purok 3, Brgy. San Isidro, Cabanatuan City, Nueva Ecija', 'Cabanatuan City', 'Nueva Ecija'],
    ["Juan Cruz\nBrgy. Bagong Sikat Science City of Muñoz Nueva Ecija", 'Brgy. Bagong Sikat Science City of Muñoz Nueva Ecija', 'Science City of Muñoz', 'Nueva Ecija'],
    ["Juan Cruz\nHome Address:\nBlk 5 Lot 12 Phase 2, Villa Verde Subd.\nSan Jose City, Nueva Ecija 3121", 'Blk 5 Lot 12 Phase 2, Villa Verde Subd., San Jose City, Nueva Ecija 3121', 'San Jose City', 'Nueva Ecija'],
    ["Maria Santos\n123 Rizal St., Brgy. Poblacion, Talavera, Nueva Ecija\nmaria@example.com", '123 Rizal St., Brgy. Poblacion, Talavera, Nueva Ecija', 'Talavera', 'Nueva Ecija'],
    ["Maria Santos\nmaria@example.com | 0917 123 4567 | Gapan City, Nueva Ecija", 'Gapan City, Nueva Ecija', 'Gapan City', 'Nueva Ecija'],
    ["Maria Santos\nQuezon City, Metro Manila 1100", 'Quezon City, Metro Manila 1100', 'Quezon City', 'Metro Manila'],
    ["Maria Santos\nLocation | Palayan City", 'Palayan City', 'Palayan City', null],
    ["Maria Santos\nPresent Address     #45 Mabini Street, Barangay 2, Guimba, Nueva Ecija", '#45 Mabini Street, Barangay 2, Guimba, Nueva Ecija', 'Guimba', 'Nueva Ecija'],
    ["Maria Santos\nObjective\nTo work as a cashier.\nPERSONAL DATA\nPermanent Address: Sitio Malinis, Brgy. Sta. Rosa, Jaen, Nueva Ecija\nBirthday: 05/03/1996", 'Sitio Malinis, Brgy. Sta. Rosa, Jaen, Nueva Ecija', 'Jaen', 'Nueva Ecija'],
    ["Ana Lim\nUnit 4B, Sunrise Tower, Ortigas Ave.,\nPasig City 1605", 'Unit 4B, Sunrise Tower, Ortigas Ave., Pasig City 1605', 'Pasig City', null],
    ["Dan Gan\nFront-End Developer\n9 Wall St, New York, 10005, USA\n890-555-0401 · dan@example.com", '9 Wall St, New York, 10005, USA', null, null],
];
foreach ($addressCases as [$text, $location, $city, $province]) {
    $p = personal($text);
    check(v($p, 'location') === $location, "address: $location", v($p, 'location'));
    check(v($p, 'city') === $city, "  city: " . ($city ?? 'none'), v($p, 'city'));
    if ($province !== null) check(v($p, 'province') === $province, "  province: $province", v($p, 'province'));
}
$p = personal("Juan Cruz\nHome Address:\nBlk 5 Lot 12 Phase 2, Villa Verde Subd.\nSan Jose City, Nueva Ecija 3121");
check(v($p, 'zip_code') === '3121', 'ZIP code from address', v($p, 'zip_code'));
$p = personal("Ana Lim\n1234 Rizal Avenue, Manila");
check(v($p, 'zip_code') === null, 'house number is not a ZIP code', v($p, 'zip_code'));
$p = personal("Carlo Reyes\ncarlo@example.com\nWork Experience\nSales Associate, SM Supermarket, Cabanatuan City\n2019 - 2021");
check(v($p, 'location') === null, 'employer address inside experience is not the home address', v($p, 'location'));
$p = personal("Carlo Reyes\nElectrician\ncarlo@example.com");
check(v($p, 'location') === null, 'no address invented when none is written');

echo "Emails and dates\n";
check(v(personal("Juan Cruz\njuan.cruz @ gmail.com"), 'email') === 'juan.cruz@gmail.com', 'email with spaces around @');
check(v(personal("Juan Cruz\njuan.cruz[at]yahoo[dot]com"), 'email') === 'juan.cruz@yahoo.com', 'obfuscated email');
$dateCases = [["Birthday: 05/03/1996", '1996-05-03'], ["Date of Birth: 23/04/1998", '1998-04-23'], ["Birthdate | 1997-11-02", '1997-11-02'], ["Date of Birth\nMarch 5 1996", '1996-03-05'], ["Birthday: 5th of March, 1996", '1996-03-05'], ["Birthday: Mar. 5, 1996", '1996-03-05']];
foreach ($dateCases as [$text, $expected]) {
    check(v(personal("Juan Cruz\n$text"), 'birth_date') === $expected, "birth date: $text", v(personal("Juan Cruz\n$text"), 'birth_date'));
}

echo "\n$passes passed, $failures failed\n";
exit($failures > 0 ? 1 : 0);
