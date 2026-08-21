<?php

namespace App\Support;

class Pdf
{
    public static function loadView(string $view, array $data = []): PdfDocument
    {
        return new PdfDocument(view($view, $data)->render());
    }
}
