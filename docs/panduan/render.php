<?php
require __DIR__.'/../../vendor/autoload.php';
use Dompdf\Dompdf; use Dompdf\Options;
$dir = __DIR__;
$o = new Options(); $o->set('isRemoteEnabled', false); $o->set('isPhpEnabled', false); $o->setDefaultFont('DejaVu Sans');
$o->setChroot($dir);
$pdf = new Dompdf($o);
$pdf->loadHtml(file_get_contents("$dir/panduan-penggunaan.html"), 'UTF-8');
$pdf->setPaper('a4', 'portrait');
$pdf->render();
$canvas = $pdf->getCanvas();
$font = $pdf->getFontMetrics()->getFont('DejaVu Sans');
$canvas->page_script(function ($n, $total, $c, $fm) use ($font) {
    if ($n === 1) return;
    $c->text(51, 810, 'Panduan Penggunaan HRIS & ATS', $font, 7.5, [0.45,0.5,0.58]);
    $c->text(510, 810, "Hal. $n / $total", $font, 7.5, [0.45,0.5,0.58]);
});
file_put_contents(__DIR__.'/../Panduan-Penggunaan-HRIS.pdf', $pdf->output());
echo "ok\n";
