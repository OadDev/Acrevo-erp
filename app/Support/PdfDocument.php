<?php

namespace App\Support;

use Illuminate\Http\Response;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\HeaderUtils;

class PdfDocument
{
    private Mpdf $mpdf;

    public function __construct(string $html)
    {
        // mPDF's HTML parser leans on PCRE, and the default 1MB
        // pcre.backtrack_limit is easy to exceed once a work order has
        // enough entries across enough sections (the full multi-section
        // export, e.g. inside the ZIP download) - past that, WriteHTML()
        // throws instead of rendering. Raise it well above anything this
        // app's PDFs realistically produce.
        ini_set('pcre.backtrack_limit', '10000000');
        ini_set('pcre.recursion_limit', '10000000');

        $this->mpdf = new Mpdf([
            'format' => 'A4',
            'default_font' => 'dejavusans',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'tempDir' => storage_path('app/mpdf'),
        ]);

        $this->mpdf->WriteHTML($html);
    }

    public function output(): string
    {
        return $this->mpdf->Output('', Destination::STRING_RETURN);
    }

    public function download(string $filename = 'document.pdf'): Response
    {
        $output = $this->output();

        return new Response($output, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $filename, $this->fallbackName($filename)),
            'Content-Length' => strlen($output),
        ]);
    }

    private function fallbackName(string $filename): string
    {
        $filename = str_replace('%', '', $filename);

        return preg_replace('/[^\x20-\x7e]/', '', $filename) ?: 'document.pdf';
    }
}
