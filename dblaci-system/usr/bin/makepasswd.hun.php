#!/usr/bin/php
<?php
//ékezettelenítés
//nem marad nem ismert karakter
function dbf_deaccent($s)
{
    $mintak = array("á", "Á", "é", "É", "í", "Í", "ó", "Ó", "ö", "Ö", "ő", "Ő", "ú", "Ú", "ü", "Ü", "ű", "Ű");
    $csere = array("a", "A", "e", "E", "i", "i", "o", "O", "o", "O", "o", "O", "u", "U", "u", "U", "u", "U");
    $s = str_replace($mintak, $csere, $s);
    $s = preg_replace("/[^a-zA-Z0-9_,\.:\ \|\^\-!\?\(\)]/", "_", $s);
    return $s;
}

define('SZOTARFILE', '/usr/share/dblaci-utils/szotar.txt');
if (!file_exists(SZOTARFILE)) die("Nincs meg a szótár!\n");

$szolista = file(SZOTARFILE);

$indexek = array_rand($szolista, 2);
$szavak = [];

$szamhelye = rand(0, 2);

if ($szamhelye == 0) $szavak[] = rand(1, 99);
$szavak[] = dbf_deaccent(trim($szolista[$indexek[0]]));
if ($szamhelye == 1) $szavak[] = rand(1, 99);
$szavak[] = dbf_deaccent(trim($szolista[$indexek[1]]));
if ($szamhelye == 2) $szavak[] = rand(1, 99);

echo implode('', $szavak) . "\n";
