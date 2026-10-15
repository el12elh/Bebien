<?php
/**
 * Mini générateur de PDF (PHP pur, sans dépendance).
 * Texte + rectangles, polices Helvetica / Helvetica-Bold, A4 portrait.
 * Les ordonnées ($y) se comptent depuis le HAUT de la page, en points (1 pt = 1/72 pouce).
 */
class SimplePdf
{
    public float $width = 595.28;
    public float $height = 841.89;
    public float $margin = 36.0;
    public float $y = 36.0;              // position verticale courante (depuis le haut)

    private array $pages = [];
    private string $buffer = '';
    private bool $open = false;
    private string $footerLeft = '';
    private array $images = [];       // données JPEG des images embarquées
    private array $imageCache = [];   // chemin => infos image (ou null si illisible)

    public function __construct(string $footerLeft = '')
    {
        $this->footerLeft = $footerLeft;
    }

    public function contentWidth(): float
    {
        return $this->width - 2 * $this->margin;
    }

    public function addPage(): void
    {
        if ($this->open) {
            $this->pages[] = $this->buffer;
        }
        $this->buffer = '';
        $this->open = true;
        $this->y = $this->margin;
    }

    /** Passe à une nouvelle page si $h points ne tiennent plus. Retourne true si une page a été ajoutée. */
    public function ensureSpace(float $h): bool
    {
        if (!$this->open) {
            $this->addPage();
            return true;
        }
        if ($this->y + $h > $this->height - $this->margin - 16) {
            $this->addPage();
            return true;
        }
        return false;
    }

    /** Largeur approximative d'un texte (métriques Helvetica simplifiées). */
    public function textWidth(string $s, float $size, bool $bold = false): float
    {
        $s = $this->toLatin($s);
        $units = 0.0;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $c = $s[$i];
            if (strpos("ijl.,;:'|!", $c) !== false) {
                $units += 0.25;
            } elseif (strpos('ft() -/[]', $c) !== false) {
                $units += 0.32;
            } elseif ($c === 'r') {
                $units += 0.35;
            } elseif (strpos('mwMW', $c) !== false) {
                $units += 0.82;
            } elseif (ctype_upper($c)) {
                $units += 0.68;
            } elseif (ctype_digit($c)) {
                $units += 0.56;
            } else {
                $units += 0.52;
            }
        }
        return $units * $size * ($bold ? 1.06 : 1.0);
    }

    /** Tronque un texte avec « ... » pour qu'il tienne dans $maxW. */
    public function fit(string $s, float $maxW, float $size, bool $bold = false): string
    {
        if ($this->textWidth($s, $size, $bold) <= $maxW) {
            return $s;
        }
        while ($s !== '' && $this->textWidth($s . '...', $size, $bold) > $maxW) {
            $s = function_exists('mb_substr') ? mb_substr($s, 0, mb_strlen($s) - 1) : substr($s, 0, -1);
        }
        return $s . '...';
    }

    public function text(float $x, float $yTop, string $s, float $size = 10, bool $bold = false, array $color = [0, 0, 0]): void
    {
        $this->buffer .= $this->textOps($x, $yTop, $s, $size, $bold, $color);
    }

    public function rect(float $x, float $yTop, float $w, float $h, ?array $fill = null, ?array $stroke = null): void
    {
        $y = $this->height - $yTop - $h;
        $ops = '';
        if ($fill !== null) {
            $ops .= $this->color($fill, false) . sprintf('%.2F %.2F %.2F %.2F re f', $x, $y, $w, $h) . "\n";
        }
        if ($stroke !== null) {
            $ops .= $this->color($stroke, true) . '0.5 w ' . sprintf('%.2F %.2F %.2F %.2F re S', $x, $y, $w, $h) . "\n";
        }
        $this->buffer .= $ops;
    }

    /**
     * Charge une image (jpg, png, gif, webp) pour l'embarquer. Nécessite l'extension GD ;
     * retourne null si l'image est absente, illisible (ex. SVG) ou si GD n'est pas disponible.
     */
    public function loadImage(string $file): ?array
    {
        if (array_key_exists($file, $this->imageCache)) {
            return $this->imageCache[$file];
        }
        $this->imageCache[$file] = null;
        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'svg') {
            return $this->imageCache[$file] = $this->loadSvg($file);
        }
        if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg') || !is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        $src = $raw === false ? false : @imagecreatefromstring($raw);
        if (!$src) {
            return null;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        if ($w < 1 || $h < 1) {
            imagedestroy($src);
            return null;
        }
        // Réduction à 120 px max (suffisant pour un logo de 20 pt) + fond blanc (transparence)
        $scale = min(1, 120 / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start();
        imagejpeg($dst, null, 85);
        $jpeg = (string) ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);
        if ($jpeg === '') {
            return null;
        }
        $this->images[] = ['data' => $jpeg, 'w' => $nw, 'h' => $nh];
        return $this->imageCache[$file] = ['n' => count($this->images), 'w' => $nw, 'h' => $nh];
    }

    /**
     * Tentative de conversion d'un SVG en JPEG via l'extension Imagick (si installée).
     * Retourne null en cas d'échec : le PDF est alors généré sans ce logo.
     */
    private function loadSvg(string $file): ?array
    {
        if (!class_exists('Imagick') || !is_file($file)) {
            return null;
        }
        try {
            $im = new Imagick();
            $im->setBackgroundColor(new ImagickPixel('white'));
            $im->setResolution(150, 150);
            $im->readImage($file);
            $im->setImageBackgroundColor('white');
            $im = $im->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            $im->thumbnailImage(120, 120, true);
            $im->setImageFormat('jpeg');
            $im->setImageCompressionQuality(85);
            $w = $im->getImageWidth();
            $h = $im->getImageHeight();
            $jpeg = $im->getImageBlob();
            $im->clear();
            if ($w < 1 || $h < 1 || $jpeg === '') {
                return null;
            }
            $this->images[] = ['data' => $jpeg, 'w' => $w, 'h' => $h];
            return ['n' => count($this->images), 'w' => $w, 'h' => $h];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Place une image dans la boîte $maxW x $maxH en conservant ses proportions. */
    public function image(array $img, float $x, float $yTop, float $maxW, float $maxH): void
    {
        $ratio = min($maxW / $img['w'], $maxH / $img['h']);
        $w = $img['w'] * $ratio;
        $h = $img['h'] * $ratio;
        $px = $x + ($maxW - $w) / 2;
        $py = $this->height - $yTop - $maxH + ($maxH - $h) / 2;
        $this->buffer .= sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /Im%d Do Q\n", $w, $h, $px, $py, $img['n']);
    }

    /**
     * Dessine une ligne de tableau à la position courante puis descend de $h.
     * $opts : bold, size, fill (rgb), color (rgb texte), align (tableau 'L'|'C'|'R' par colonne)
     */
    public function row(array $cells, array $widths, float $h = 15, array $opts = []): void
    {
        $size = $opts['size'] ?? 9;
        $bold = $opts['bold'] ?? false;
        $fill = $opts['fill'] ?? null;
        $color = $opts['color'] ?? [0, 0, 0];
        $align = $opts['align'] ?? [];
        $fills = $opts['fills'] ?? [];        // couleur de fond par colonne
        $images = $opts['images'] ?? [];      // image par colonne (voir loadImage)
        $indent = $opts['indent'] ?? [];      // décalage du texte par colonne (place du logo)
        $cards = $opts['cards'] ?? [];        // petit carton coloré dessiné dans la cellule
        $x = $this->margin;

        foreach ($cells as $i => $cell) {
            $w = $widths[$i];
            $this->rect($x, $this->y, $w, $h, $fills[$i] ?? $fill, [0.72, 0.78, 0.88]);
            $pad = (float) ($indent[$i] ?? 0);
            if (isset($cards[$i])) {
                $this->rect($x + ($w - 8) / 2, $this->y + ($h - 11) / 2, 8, 11, $cards[$i], [0.55, 0.55, 0.55]);
            }
            if (!empty($images[$i])) {
                $this->image($images[$i], $x + 3, $this->y + 2, $pad - 3, $h - 4);
            }
            $txt = $this->fit((string) $cell, $w - 8 - $pad, $size, $bold);
            $tw = $this->textWidth($txt, $size, $bold);
            $a = $align[$i] ?? 'L';
            $tx = $x + 4 + $pad;
            if ($a === 'C') {
                $tx = $x + ($w - $tw) / 2;
            } elseif ($a === 'R') {
                $tx = $x + $w - 4 - $tw;
            }
            $this->text($tx, $this->y + ($h + $size * 0.7) / 2 - 1, $txt, $size, $bold, $color);
            $x += $w;
        }
        $this->y += $h;
    }

    public function output(): string
    {
        if ($this->open) {
            $this->pages[] = $this->buffer;
            $this->buffer = '';
            $this->open = false;
        }
        if (!$this->pages) {
            $this->pages[] = '';
        }

        $n = count($this->pages);
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        for ($i = 0; $i < $n; $i++) {
            $kids[] = (5 + 2 * $i) . ' 0 R';
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $n . ' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        foreach ($this->pages as $i => $content) {
            $footer = $this->textOps($this->margin, $this->height - 24, $this->footerLeft, 8, false, [0.45, 0.5, 0.6]);
            $label = 'Page ' . ($i + 1) . ' / ' . $n;
            $footer .= $this->textOps($this->width - $this->margin - $this->textWidth($label, 8), $this->height - 24, $label, 8, false, [0.45, 0.5, 0.6]);
            $content .= $footer;

            $pageNo = 5 + 2 * $i;
            $objects[$pageNo] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . sprintf('%.2F %.2F', $this->width, $this->height) . '] '
                . '/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . ($pageNo + 1) . ' 0 R >>';
            $objects[$pageNo + 1] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
        }

        // Images (JPEG) : numéros d'objets placés après les pages
        $xobjects = '';
        $imageBase = 5 + 2 * $n;
        foreach ($this->images as $k => $img) {
            $xobjects .= '/Im' . ($k + 1) . ' ' . ($imageBase + $k) . ' 0 R ';
            $objects[$imageBase + $k] = '<< /Type /XObject /Subtype /Image /Width ' . $img['w'] . ' /Height ' . $img['h']
                . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($img['data']) . " >>\nstream\n"
                . $img['data'] . "\nendstream";
        }
        if ($xobjects !== '') {
            foreach ($this->pages as $i => $unused) {
                $objects[5 + 2 * $i] = str_replace('>> >> /Contents', '>> /XObject << ' . $xobjects . '>> >> /Contents', $objects[5 + 2 * $i]);
            }
        }

        ksort($objects);
        $out = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($out);
            $out .= $num . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($out);
        $count = count($objects) + 1;
        $out .= "xref\n0 " . $count . "\n";
        $out .= "0000000000 65535 f \n";
        foreach ($offsets as $off) {
            $out .= sprintf("%010d 00000 n \n", $off);
        }
        $out .= "trailer\n<< /Size " . $count . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        return $out;
    }

    // ---------------------------------------------------------------- interne

    private function textOps(float $x, float $yTop, string $s, float $size, bool $bold, array $color): string
    {
        $y = $this->height - $yTop;
        return $this->color($color, false)
            . 'BT /' . ($bold ? 'F2' : 'F1') . ' ' . sprintf('%.2F', $size) . ' Tf '
            . sprintf('%.2F %.2F', $x, $y) . ' Td (' . $this->escape($this->toLatin($s)) . ") Tj ET\n";
    }

    private function color(array $c, bool $stroke): string
    {
        return sprintf('%.3F %.3F %.3F %s ', $c[0], $c[1], $c[2], $stroke ? 'RG' : 'rg');
    }

    private function escape(string $s): string
    {
        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $s);
    }

    /** UTF-8 -> Windows-1252 (accents, tirets, ×...). */
    private function toLatin(string $s): string
    {
        if (function_exists('mb_convert_encoding')) {
            $r = @mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
            if (is_string($r)) {
                return $r;
            }
        }
        if (function_exists('iconv')) {
            $r = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
            if (is_string($r)) {
                return $r;
            }
        }
        return preg_replace('/[^\x20-\x7E]/', '?', $s);
    }
}
