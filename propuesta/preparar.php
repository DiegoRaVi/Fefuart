<?php
// Reduce unas cuantas piezas a un tamano que quepa en el lienzo.
$origen = 'C:/xampp/htdocs/Fefuart/app/Client/img/fefuart-img/';
$destinos = [
    'hero.jpg'    => ['Live-art/10.jpg', 1100],
    'retratos.jpg'=> ['Live-art/13.jpg', 700],
    'ramo.jpg'    => ['Ramos/ramo.jpg', 700],
    'letras.jpg'  => ['Letras-infantiles/Imagen de WhatsApp 2024-07-08 a las 11.44.50_a5dd12eb.jpg', 700],
    'mesa.jpg'    => ['Live-art/1.jpg', 700],
    'acuarela.jpg'=> ['Live-art/12.jpg', 500],
];

foreach ($destinos as $nombre => [$rel, $lado]) {
    $ruta = $origen.$rel;
    if (! file_exists($ruta)) { echo "falta $rel\n"; continue; }

    $img = @imagecreatefromjpeg($ruta);
    if (! $img) { echo "no abre $rel\n"; continue; }

    $w = imagesx($img); $h = imagesy($img);
    $escala = $lado / max($w, $h);
    $nw = (int) round($w * $escala); $nh = (int) round($h * $escala);

    $dst = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($dst, $img, 0,0,0,0, $nw,$nh, $w,$h);
    imagejpeg($dst, $nombre, 68);
    imagedestroy($dst); imagedestroy($img);

    printf("%-14s %sx%s  %.0f KB\n", $nombre, $nw, $nh, filesize($nombre)/1024);
}
