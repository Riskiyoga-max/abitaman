<?php
declare(strict_types=1);

/**
 * Generator diagram Bab 3 ABI Taman.
 *
 * Menghasilkan berkas Draw.io native (XML tidak terkompresi) dan SVG sementara.
 * SVG kemudian dikonversi ke PNG oleh skrip pemanggil menggunakan ImageMagick.
 *
 * Sumber model:
 * - README.md (aktor, fitur, dan alur status)
 * - database/refactor_schema.sql (tabel dan foreign key)
 * - implementasi halaman/API aplikasi ABI Taman
 */

final class Diagram
{
    private array $items = [];
    private int $nextId = 2;

    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly int $width,
        public readonly int $height,
    ) {
    }

    public function rect(float $x, float $y, float $w, float $h, string $text, array $options = []): string
    {
        return $this->add('rect', compact('x', 'y', 'w', 'h', 'text') + $options);
    }

    public function ellipse(float $x, float $y, float $w, float $h, string $text, array $options = []): string
    {
        return $this->add('ellipse', compact('x', 'y', 'w', 'h', 'text') + $options);
    }

    public function diamond(float $x, float $y, float $w, float $h, string $text, array $options = []): string
    {
        return $this->add('diamond', compact('x', 'y', 'w', 'h', 'text') + $options);
    }

    public function circle(float $x, float $y, float $size, string $text = '', array $options = []): string
    {
        return $this->add('ellipse', ['x' => $x, 'y' => $y, 'w' => $size, 'h' => $size, 'text' => $text] + $options);
    }

    public function actor(float $x, float $y, string $text, array $options = []): string
    {
        return $this->add('actor', ['x' => $x, 'y' => $y, 'w' => 70, 'h' => 105, 'text' => $text] + $options);
    }

    public function text(float $x, float $y, float $w, float $h, string $text, array $options = []): string
    {
        return $this->add('text', compact('x', 'y', 'w', 'h', 'text') + $options);
    }

    public function lane(float $x, float $y, float $w, float $h, string $text, array $options = []): string
    {
        return $this->add('lane', compact('x', 'y', 'w', 'h', 'text') + $options);
    }

    public function table(float $x, float $y, float $w, string $name, array $fields, array $options = []): string
    {
        $lineHeight = (float) ($options['lineHeight'] ?? 19);
        $headerHeight = (float) ($options['headerHeight'] ?? 34);
        $h = $headerHeight + 10 + (count($fields) * $lineHeight);
        return $this->add('table', compact('x', 'y', 'w', 'h', 'name', 'fields') + $options);
    }

    public function edge(float $x1, float $y1, float $x2, float $y2, string $label = '', array $options = []): string
    {
        return $this->add('edge', compact('x1', 'y1', 'x2', 'y2', 'label') + $options);
    }

    public function polyline(array $points, string $label = '', array $options = []): string
    {
        return $this->add('edge', compact('points', 'label') + $options);
    }

    private function add(string $type, array $data): string
    {
        $id = (string) $this->nextId++;
        $this->items[] = ['id' => $id, 'type' => $type] + $data;
        return $id;
    }

    public function write(string $drawioPath, string $svgPath): void
    {
        file_put_contents($drawioPath, $this->toDrawio());
        file_put_contents($svgPath, $this->toSvg());
    }

    private function toDrawio(): string
    {
        $cells = [
            '    <mxCell id="0"/>',
            '    <mxCell id="1" parent="0"/>',
        ];

        foreach ($this->items as $item) {
            $id = $item['id'];
            $type = $item['type'];
            if ($type === 'edge') {
                $points = $item['points'] ?? [
                    [$item['x1'], $item['y1']],
                    [$item['x2'], $item['y2']],
                ];
                $label = $item['label'] ?? '';
                $style = 'edgeStyle=orthogonalEdgeStyle;rounded=0;orthogonalLoop=1;jettySize=auto;html=1;'
                    . 'strokeColor=' . ($item['stroke'] ?? '#334155') . ';strokeWidth=' . ($item['strokeWidth'] ?? 1.5) . ';'
                    . (($item['dashed'] ?? false) ? 'dashed=1;' : '')
                    . (($item['arrow'] ?? true) ? 'endArrow=block;endFill=1;' : 'endArrow=none;')
                    . (($item['startArrow'] ?? false) ? 'startArrow=block;startFill=1;' : '');
                $geometry = '      <mxGeometry relative="1" as="geometry">'
                    . '<mxPoint x="' . $points[0][0] . '" y="' . $points[0][1] . '" as="sourcePoint"/>'
                    . '<mxPoint x="' . $points[count($points) - 1][0] . '" y="' . $points[count($points) - 1][1] . '" as="targetPoint"/>';
                if (count($points) > 2) {
                    $geometry .= '<Array as="points">';
                    foreach (array_slice($points, 1, -1) as [$px, $py]) {
                        $geometry .= '<mxPoint x="' . $px . '" y="' . $py . '"/>';
                    }
                    $geometry .= '</Array>';
                }
                $geometry .= '</mxGeometry>';
                $cells[] = '    <mxCell id="' . $id . '" value="' . self::xml($label) . '" style="' . self::xml($style)
                    . '" edge="1" parent="1">' . $geometry . '</mxCell>';
                continue;
            }

            $value = '';
            $style = 'html=1;whiteSpace=wrap;';
            $x = $item['x'];
            $y = $item['y'];
            $w = $item['w'];
            $h = $item['h'];
            $fill = $item['fill'] ?? '#ffffff';
            $stroke = $item['stroke'] ?? '#334155';
            $fontColor = $item['fontColor'] ?? '#0f172a';
            $fontSize = $item['fontSize'] ?? 13;
            $fontStyle = ($item['bold'] ?? false) ? 1 : 0;

            switch ($type) {
                case 'rect':
                    $value = self::htmlText($item['text']);
                    $style .= 'rounded=' . (($item['rounded'] ?? true) ? '1' : '0') . ';arcSize=12;'
                        . 'fillColor=' . $fill . ';strokeColor=' . $stroke . ';strokeWidth=' . ($item['strokeWidth'] ?? 1.5) . ';'
                        . 'fontColor=' . $fontColor . ';fontSize=' . $fontSize . ';fontStyle=' . $fontStyle . ';'
                        . 'align=' . ($item['align'] ?? 'center') . ';verticalAlign=middle;spacing=8;';
                    break;
                case 'ellipse':
                    $value = self::htmlText($item['text']);
                    $style .= 'ellipse;aspect=fixed=0;fillColor=' . $fill . ';strokeColor=' . $stroke . ';'
                        . 'strokeWidth=' . ($item['strokeWidth'] ?? 1.5) . ';fontColor=' . $fontColor . ';'
                        . 'fontSize=' . $fontSize . ';fontStyle=' . $fontStyle . ';align=center;verticalAlign=middle;';
                    break;
                case 'diamond':
                    $value = self::htmlText($item['text']);
                    $style .= 'rhombus;fillColor=' . $fill . ';strokeColor=' . $stroke . ';'
                        . 'strokeWidth=' . ($item['strokeWidth'] ?? 1.5) . ';fontColor=' . $fontColor . ';'
                        . 'fontSize=' . $fontSize . ';fontStyle=' . $fontStyle . ';align=center;verticalAlign=middle;';
                    break;
                case 'actor':
                    $value = self::htmlText($item['text']);
                    $style .= 'shape=umlActor;verticalLabelPosition=bottom;verticalAlign=top;html=1;'
                        . 'fillColor=' . $fill . ';strokeColor=' . $stroke . ';fontColor=' . $fontColor . ';'
                        . 'fontSize=' . $fontSize . ';fontStyle=1;';
                    break;
                case 'text':
                    $value = self::htmlText($item['text']);
                    $style .= 'text;strokeColor=none;fillColor=none;align=' . ($item['align'] ?? 'center') . ';'
                        . 'verticalAlign=middle;fontColor=' . $fontColor . ';fontSize=' . $fontSize . ';fontStyle=' . $fontStyle . ';';
                    break;
                case 'lane':
                    $value = self::htmlText($item['text']);
                    $style .= 'swimlane;horizontal=1;startSize=' . ($item['headerHeight'] ?? 36) . ';rounded=0;'
                        . 'fillColor=' . ($item['headerFill'] ?? '#e2e8f0') . ';swimlaneFillColor=' . $fill . ';'
                        . 'strokeColor=' . $stroke . ';strokeWidth=1.5;fontColor=' . $fontColor . ';'
                        . 'fontSize=' . $fontSize . ';fontStyle=1;';
                    break;
                case 'table':
                    $fieldHtml = $item['fields'];
                    $value = '<div style="font-weight:700;text-align:center;">' . $item['name'] . '</div>'
                        . '<hr style="border:none;border-top:1px solid ' . $stroke . ';margin:6px -4px 5px;">'
                        . '<div style="text-align:left;line-height:1.45;">' . implode('<br>', $fieldHtml) . '</div>';
                    $style .= 'rounded=1;arcSize=8;fillColor=' . $fill . ';strokeColor=' . $stroke . ';'
                        . 'strokeWidth=' . ($item['strokeWidth'] ?? 1.5) . ';fontColor=' . $fontColor . ';'
                        . 'fontSize=' . $fontSize . ';align=left;verticalAlign=top;spacing=8;';
                    break;
            }

            $cells[] = '    <mxCell id="' . $id . '" value="' . self::xml($value) . '" style="' . self::xml($style)
                . '" vertex="1" parent="1"><mxGeometry x="' . $x . '" y="' . $y . '" width="' . $w
                . '" height="' . $h . '" as="geometry"/></mxCell>';
        }

        $diagramId = substr(hash('sha256', $this->slug), 0, 12);
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<mxfile host="app.diagrams.net" modified="2026-07-27T00:00:00.000Z" agent="Codex" version="24.7.17">' . "\n"
            . '  <diagram id="' . $diagramId . '" name="Page-1">' . "\n"
            . '    <mxGraphModel dx="1200" dy="800" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" '
            . 'arrows="1" fold="1" page="1" pageScale="1" pageWidth="' . $this->width . '" pageHeight="' . $this->height
            . '" math="0" shadow="0">' . "\n"
            . "  <root>\n" . implode("\n", $cells) . "\n  </root>\n"
            . "    </mxGraphModel>\n  </diagram>\n</mxfile>\n";
    }

    private function toSvg(): string
    {
        $svg = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<svg xmlns="http://www.w3.org/2000/svg" width="' . $this->width . '" height="' . $this->height
                . '" viewBox="0 0 ' . $this->width . ' ' . $this->height . '">',
            '<defs>',
            '<marker id="arrow" markerWidth="10" markerHeight="8" refX="9" refY="4" orient="auto" markerUnits="strokeWidth">'
                . '<path d="M0,0 L10,4 L0,8 z" fill="#334155"/></marker>',
            '<marker id="arrowBlue" markerWidth="10" markerHeight="8" refX="9" refY="4" orient="auto" markerUnits="strokeWidth">'
                . '<path d="M0,0 L10,4 L0,8 z" fill="#2563eb"/></marker>',
            '<marker id="arrowGreen" markerWidth="10" markerHeight="8" refX="9" refY="4" orient="auto" markerUnits="strokeWidth">'
                . '<path d="M0,0 L10,4 L0,8 z" fill="#15803d"/></marker>',
            '<filter id="shadow" x="-20%" y="-20%" width="140%" height="140%">'
                . '<feDropShadow dx="0" dy="2" stdDeviation="3" flood-color="#0f172a" flood-opacity=".12"/></filter>',
            '</defs>',
            '<rect width="100%" height="100%" fill="#ffffff"/>',
        ];

        foreach ($this->items as $item) {
            $type = $item['type'];
            if ($type === 'edge') {
                $points = $item['points'] ?? [
                    [$item['x1'], $item['y1']],
                    [$item['x2'], $item['y2']],
                ];
                $stroke = $item['stroke'] ?? '#334155';
                $pointString = implode(' ', array_map(static fn(array $p): string => $p[0] . ',' . $p[1], $points));
                $dash = ($item['dashed'] ?? false) ? ' stroke-dasharray="8 6"' : '';
                $marker = '';
                if ($item['arrow'] ?? true) {
                    $markerId = $stroke === '#2563eb' ? 'arrowBlue' : ($stroke === '#15803d' ? 'arrowGreen' : 'arrow');
                    $marker = ' marker-end="url(#' . $markerId . ')"';
                }
                $svg[] = '<polyline points="' . $pointString . '" fill="none" stroke="' . self::xml($stroke)
                    . '" stroke-width="' . ($item['strokeWidth'] ?? 1.7) . '"' . $dash . $marker . '/>';
                if (($item['label'] ?? '') !== '') {
                    $mid = $points[(int) floor((count($points) - 1) / 2)];
                    $labelX = (float) ($item['labelX'] ?? $mid[0]);
                    $labelY = (float) ($item['labelY'] ?? ($mid[1] - 8));
                    $labelW = (float) ($item['labelW'] ?? 150);
                    $svg[] = '<rect x="' . ($labelX - $labelW / 2) . '" y="' . ($labelY - 13)
                        . '" width="' . $labelW . '" height="20" rx="4" fill="#ffffff" opacity=".94"/>';
                    $svg[] = self::svgText($labelX - $labelW / 2, $labelY - 11, $labelW, 18, $item['label'], [
                        'fontSize' => $item['fontSize'] ?? 11,
                        'align' => 'center',
                    ]);
                }
                continue;
            }

            $x = (float) $item['x'];
            $y = (float) $item['y'];
            $w = (float) $item['w'];
            $h = (float) $item['h'];
            $fill = $item['fill'] ?? '#ffffff';
            $stroke = $item['stroke'] ?? '#334155';
            $sw = $item['strokeWidth'] ?? 1.5;
            $shadow = ($item['shadow'] ?? false) ? ' filter="url(#shadow)"' : '';

            switch ($type) {
                case 'rect':
                    $rx = ($item['rounded'] ?? true) ? min(12, $h / 5) : 0;
                    $svg[] = '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h
                        . '" rx="' . $rx . '" fill="' . self::xml($fill) . '" stroke="' . self::xml($stroke)
                        . '" stroke-width="' . $sw . '"' . $shadow . '/>';
                    $svg[] = self::svgText($x + 6, $y + 4, $w - 12, $h - 8, $item['text'], $item);
                    break;
                case 'ellipse':
                    $svg[] = '<ellipse cx="' . ($x + $w / 2) . '" cy="' . ($y + $h / 2) . '" rx="' . ($w / 2)
                        . '" ry="' . ($h / 2) . '" fill="' . self::xml($fill) . '" stroke="' . self::xml($stroke)
                        . '" stroke-width="' . $sw . '"' . $shadow . '/>';
                    $svg[] = self::svgText($x + 5, $y + 4, $w - 10, $h - 8, $item['text'], $item);
                    break;
                case 'diamond':
                    $svg[] = '<polygon points="' . ($x + $w / 2) . ',' . $y . ' ' . ($x + $w) . ',' . ($y + $h / 2)
                        . ' ' . ($x + $w / 2) . ',' . ($y + $h) . ' ' . $x . ',' . ($y + $h / 2)
                        . '" fill="' . self::xml($fill) . '" stroke="' . self::xml($stroke)
                        . '" stroke-width="' . $sw . '"' . $shadow . '/>';
                    $svg[] = self::svgText($x + $w * .18, $y + $h * .18, $w * .64, $h * .64, $item['text'], $item);
                    break;
                case 'actor':
                    $cx = $x + $w / 2;
                    $svg[] = '<circle cx="' . $cx . '" cy="' . ($y + 16) . '" r="12" fill="#ffffff" stroke="' . self::xml($stroke) . '" stroke-width="2"/>';
                    $svg[] = '<line x1="' . $cx . '" y1="' . ($y + 28) . '" x2="' . $cx . '" y2="' . ($y + 64) . '" stroke="' . self::xml($stroke) . '" stroke-width="2"/>';
                    $svg[] = '<line x1="' . ($cx - 22) . '" y1="' . ($y + 42) . '" x2="' . ($cx + 22) . '" y2="' . ($y + 42) . '" stroke="' . self::xml($stroke) . '" stroke-width="2"/>';
                    $svg[] = '<line x1="' . $cx . '" y1="' . ($y + 64) . '" x2="' . ($cx - 19) . '" y2="' . ($y + 87) . '" stroke="' . self::xml($stroke) . '" stroke-width="2"/>';
                    $svg[] = '<line x1="' . $cx . '" y1="' . ($y + 64) . '" x2="' . ($cx + 19) . '" y2="' . ($y + 87) . '" stroke="' . self::xml($stroke) . '" stroke-width="2"/>';
                    $svg[] = self::svgText($x - 20, $y + 88, $w + 40, 28, $item['text'], $item + ['bold' => true]);
                    break;
                case 'text':
                    $svg[] = self::svgText($x, $y, $w, $h, $item['text'], $item);
                    break;
                case 'lane':
                    $hh = (float) ($item['headerHeight'] ?? 36);
                    $svg[] = '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h
                        . '" fill="' . self::xml($fill) . '" stroke="' . self::xml($stroke) . '" stroke-width="1.5"/>';
                    $svg[] = '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $hh
                        . '" fill="' . self::xml($item['headerFill'] ?? '#e2e8f0') . '" stroke="' . self::xml($stroke)
                        . '" stroke-width="1.5"/>';
                    $svg[] = self::svgText($x + 4, $y + 3, $w - 8, $hh - 6, $item['text'], $item + ['bold' => true]);
                    break;
                case 'table':
                    $headerHeight = (float) ($item['headerHeight'] ?? 34);
                    $lineHeight = (float) ($item['lineHeight'] ?? 19);
                    $svg[] = '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h
                        . '" rx="7" fill="' . self::xml($fill) . '" stroke="' . self::xml($stroke)
                        . '" stroke-width="' . $sw . '"' . $shadow . '/>';
                    $svg[] = '<path d="M' . ($x + 7) . ',' . $y . ' H' . ($x + $w - 7)
                        . ' Q' . ($x + $w) . ',' . $y . ' ' . ($x + $w) . ',' . ($y + 7)
                        . ' V' . ($y + $headerHeight) . ' H' . $x . ' V' . ($y + 7)
                        . ' Q' . $x . ',' . $y . ' ' . ($x + 7) . ',' . $y . ' Z" fill="'
                        . self::xml($item['headerFill'] ?? '#dbeafe') . '"/>';
                    $svg[] = '<line x1="' . $x . '" y1="' . ($y + $headerHeight) . '" x2="' . ($x + $w)
                        . '" y2="' . ($y + $headerHeight) . '" stroke="' . self::xml($stroke) . '" stroke-width="1"/>';
                    $svg[] = self::svgText($x + 5, $y + 3, $w - 10, $headerHeight - 6, $item['name'], $item + ['bold' => true]);
                    foreach ($item['fields'] as $index => $field) {
                        $svg[] = self::svgText(
                            $x + 9,
                            $y + $headerHeight + 5 + ($index * $lineHeight),
                            $w - 18,
                            $lineHeight,
                            $field,
                            $item + ['align' => 'left', 'fontSize' => $item['fontSize'] ?? 11]
                        );
                    }
                    break;
            }
        }

        $svg[] = '</svg>';
        return implode("\n", $svg) . "\n";
    }

    private static function svgText(float $x, float $y, float $w, float $h, string $text, array $options): string
    {
        $fontSize = (float) ($options['fontSize'] ?? 13);
        $bold = (bool) ($options['bold'] ?? false);
        $italic = (bool) ($options['italic'] ?? false);
        $align = $options['align'] ?? 'center';
        $color = $options['fontColor'] ?? '#0f172a';
        $maxChars = max(4, (int) floor($w / max(5.5, $fontSize * .56)));
        $lines = [];
        foreach (explode("\n", $text) as $paragraph) {
            $wrapped = wordwrap(trim($paragraph), $maxChars, "\n", true);
            foreach (explode("\n", $wrapped) as $line) {
                $lines[] = $line;
            }
        }
        $lineHeight = $fontSize * 1.22;
        $totalHeight = count($lines) * $lineHeight;
        $startY = $y + max($fontSize, ($h - $totalHeight) / 2 + $fontSize * .85);
        $anchor = $align === 'left' ? 'start' : ($align === 'right' ? 'end' : 'middle');
        $tx = $align === 'left' ? $x : ($align === 'right' ? $x + $w : $x + $w / 2);
        $out = '<text x="' . $tx . '" y="' . $startY . '" text-anchor="' . $anchor
            . '" font-family="Arial, Liberation Sans, sans-serif" font-size="' . $fontSize
            . '" fill="' . self::xml($color) . '" font-weight="' . ($bold ? '700' : '400')
            . '" font-style="' . ($italic ? 'italic' : 'normal') . '">';
        foreach ($lines as $index => $line) {
            $out .= '<tspan x="' . $tx . '" dy="' . ($index === 0 ? 0 : $lineHeight) . '">'
                . self::xml($line) . '</tspan>';
        }
        return $out . '</text>';
    }

    private static function htmlText(string $text): string
    {
        return str_replace("\n", '<br>', $text);
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}

function addTitle(Diagram $d, string $subtitle = ''): void
{
    $d->text(34, 18, $d->width - 68, 38, $d->title, [
        'fontSize' => 23,
        'bold' => true,
        'fontColor' => '#0f172a',
    ]);
    if ($subtitle !== '') {
        $d->text(34, 56, $d->width - 68, 28, $subtitle, [
            'fontSize' => 12,
            'fontColor' => '#475569',
        ]);
    }
}

function addFooter(Diagram $d): void
{
    $d->text(24, $d->height - 30, $d->width - 48, 18, 'Sistem Informasi Manajemen Jasa Taman — ABI Taman', [
        'fontSize' => 10,
        'fontColor' => '#64748b',
        'align' => 'right',
    ]);
}

function proposedSystem(): Diagram
{
    $d = new Diagram('01-analisis-sistem-usulan', 'Analisis Sistem Usulan', 1720, 1260);
    addTitle($d, 'Alur digital pengelolaan jasa taman dari booking hingga laporan manajemen');
    $laneY = 100;
    $laneH = 1090;
    $laneW = 330;
    $xs = [35, 365, 695, 1025, 1355];
    $names = ['Customer', 'Sistem', 'Staff', 'Pekerja Lapangan', 'Boss'];
    $fills = ['#f8fafc', '#eff6ff', '#f8fafc', '#f0fdf4', '#fff7ed'];
    $headers = ['#dbeafe', '#bfdbfe', '#e2e8f0', '#bbf7d0', '#fed7aa'];
    foreach ($xs as $i => $x) {
        $d->lane($x, $laneY, $laneW, $laneH, $names[$i], [
            'fill' => $fills[$i],
            'headerFill' => $headers[$i],
            'fontSize' => 14,
        ]);
    }

    $d->circle(175, 155, 30, '', ['fill' => '#0f172a', 'stroke' => '#0f172a']);
    $d->rect(90, 220, 200, 68, "Login dan memilih\nlayanan", ['fill' => '#ffffff', 'shadow' => true]);
    $d->rect(90, 330, 200, 78, "Mengisi booking,\nlokasi, jadwal, dan foto", ['fill' => '#ffffff', 'shadow' => true]);
    $d->rect(420, 330, 220, 78, "Validasi dan menyimpan\nbooking", ['fill' => '#dbeafe', 'stroke' => '#2563eb']);
    $d->rect(750, 330, 220, 78, "Konfirmasi booking dan\njadwalkan survei", ['fill' => '#ffffff', 'shadow' => true]);
    $d->rect(1080, 455, 220, 78, "Melaksanakan survei dan\nunggah foto kondisi", ['fill' => '#dcfce7', 'stroke' => '#15803d']);
    $d->rect(420, 455, 220, 78, "Menyimpan hasil survei\ndan memperbarui status", ['fill' => '#dbeafe', 'stroke' => '#2563eb']);
    $d->rect(1080, 580, 220, 78, "Menyusun estimasi biaya\nmaterial dan tenaga kerja", ['fill' => '#dcfce7', 'stroke' => '#15803d']);
    $d->rect(750, 580, 220, 78, "Review dan mengirim\nestimasi", ['fill' => '#ffffff', 'shadow' => true]);
    $d->diamond(130, 565, 120, 100, "Setujui\nestimasi?", ['fill' => '#fef3c7', 'stroke' => '#d97706']);
    $d->rect(90, 710, 200, 68, "Menyetujui estimasi", ['fill' => '#ffffff', 'shadow' => true]);
    $d->rect(750, 710, 220, 68, "Memulai pekerjaan", ['fill' => '#ffffff', 'shadow' => true]);
    $d->rect(1080, 710, 220, 78, "Melaksanakan pekerjaan dan\nmemperbarui progres", ['fill' => '#dcfce7', 'stroke' => '#15803d']);
    $d->rect(420, 835, 220, 68, "Menampilkan progres\nkepada customer", ['fill' => '#dbeafe', 'stroke' => '#2563eb']);
    $d->rect(1080, 835, 220, 68, "Mengirim data final\npekerjaan", ['fill' => '#dcfce7', 'stroke' => '#15803d']);
    $d->rect(750, 835, 220, 68, "Membuat dan mengirim\ninvoice final", ['fill' => '#ffffff', 'shadow' => true]);
    $d->rect(90, 960, 200, 68, "Upload bukti\npembayaran", ['fill' => '#ffffff', 'shadow' => true]);
    $d->rect(750, 960, 220, 68, "Verifikasi pembayaran\ndan selesaikan order", ['fill' => '#ffffff', 'shadow' => true]);
    $d->rect(1410, 960, 220, 68, "Monitoring KPI dan\nmencetak laporan", ['fill' => '#ffedd5', 'stroke' => '#ea580c']);
    $d->circle(1510, 1090, 34, '', ['fill' => '#0f172a', 'stroke' => '#ef4444', 'strokeWidth' => 3]);
    $d->circle(1517, 1097, 20, '', ['fill' => '#0f172a', 'stroke' => '#0f172a']);

    $edges = [
        [[190, 185], [190, 220], ''],
        [[190, 288], [190, 330], ''],
        [[290, 369], [420, 369], 'Kirim data'],
        [[640, 369], [750, 369], 'Notifikasi'],
        [[860, 408], [860, 435], [1190, 435], [1190, 455], 'Penugasan'],
        [[1080, 494], [640, 494], 'Data survei'],
        [[530, 533], [530, 555], [1190, 555], [1190, 580], 'Data tersimpan'],
        [[1080, 619], [970, 619], 'Estimasi draft'],
        [[750, 619], [310, 619], [250, 615], 'Kirim estimasi'],
        [[190, 665], [190, 710], 'Ya'],
        [[130, 615], [70, 615], [70, 619], [750, 619], 'Tidak / revisi'],
        [[290, 744], [750, 744], 'Persetujuan'],
        [[970, 744], [1080, 744], 'Instruksi kerja'],
        [[1190, 788], [1190, 810], [640, 810], [640, 869], 'Update progres'],
        [[1080, 869], [970, 869], 'Pekerjaan selesai'],
        [[750, 869], [310, 869], [310, 994], [290, 994], 'Invoice'],
        [[290, 994], [750, 994], 'Bukti bayar'],
        [[970, 994], [1410, 994], 'Data transaksi'],
        [[1520, 1028], [1520, 1090], ''],
    ];
    foreach ($edges as $edge) {
        $points = $edge;
        $label = array_pop($points);
        $d->polyline($points, $label, ['stroke' => '#334155', 'labelW' => 120]);
    }
    addFooter($d);
    return $d;
}

function architecture(): Diagram
{
    $d = new Diagram('02-arsitektur-sistem', 'Perancangan Arsitektur Sistem', 1500, 1050);
    addTitle($d, 'Arsitektur tiga lapis aplikasi web ABI Taman');

    $d->rect(45, 100, 1410, 245, 'CLIENT LAYER', [
        'rounded' => false, 'fill' => '#f8fafc', 'stroke' => '#64748b',
        'align' => 'left', 'fontSize' => 14, 'bold' => true,
    ]);
    $clients = [
        [105, 'Customer', 'Booking, persetujuan,\npembayaran, ulasan'],
        [445, 'Staff', 'Operasional, master data,\ninvoice, verifikasi'],
        [785, 'Pekerja Lapangan', 'Survei, estimasi,\nprogres pekerjaan'],
        [1125, 'Boss', 'Monitoring KPI,\nlaporan dan ekspor'],
    ];
    foreach ($clients as [$x, $name, $desc]) {
        $d->rect($x, 165, 255, 130, "$name\n\n$desc", [
            'fill' => '#ffffff', 'stroke' => '#2563eb', 'shadow' => true, 'bold' => true,
        ]);
    }

    $d->ellipse(590, 390, 320, 115, "INTERNET / HTTPS\nBrowser desktop dan mobile", [
        'fill' => '#dbeafe', 'stroke' => '#2563eb', 'bold' => true, 'shadow' => true,
    ]);

    $d->rect(45, 550, 1410, 420, 'SERVER LAYER', [
        'rounded' => false, 'fill' => '#f8fafc', 'stroke' => '#64748b',
        'align' => 'left', 'fontSize' => 14, 'bold' => true,
    ]);
    $d->rect(110, 630, 350, 255, "Presentation Layer\n\nPHP Views + Bootstrap 5\nVanilla JavaScript\nSession + CSRF + RBAC", [
        'fill' => '#ffffff', 'stroke' => '#2563eb', 'shadow' => true, 'bold' => true,
    ]);
    $d->rect(575, 630, 350, 255, "Application Layer\n\nPHP Native Controllers/API\nBooking Workflow\nEstimator & Validasi Bisnis", [
        'fill' => '#eff6ff', 'stroke' => '#2563eb', 'shadow' => true, 'bold' => true,
    ]);
    $d->rect(1040, 630, 350, 255, "Data Layer\n\nMySQL / MariaDB\n23 tabel relasional\nFile upload foto & bukti bayar", [
        'fill' => '#ecfdf5', 'stroke' => '#15803d', 'shadow' => true, 'bold' => true,
    ]);
    $d->polyline([[750, 505], [750, 570], [285, 570], [285, 630]], 'Request / response', ['stroke' => '#2563eb', 'labelW' => 150]);
    $d->polyline([[460, 758], [575, 758]], 'HTTP internal', ['stroke' => '#2563eb', 'labelW' => 110]);
    $d->polyline([[925, 758], [1040, 758]], 'PDO / file I/O', ['stroke' => '#15803d', 'labelW' => 110]);
    $d->polyline([[1040, 820], [925, 820]], 'Data', ['stroke' => '#15803d', 'dashed' => true, 'labelW' => 70]);
    $d->polyline([[575, 820], [460, 820]], 'View / JSON', ['stroke' => '#2563eb', 'dashed' => true, 'labelW' => 95]);
    addFooter($d);
    return $d;
}

function useCase(): Diagram
{
    $d = new Diagram('03-use-case-diagram', 'Use Case Diagram', 1850, 1540);
    addTitle($d, 'Hak akses fungsional Sistem Informasi Manajemen Jasa Taman ABI Taman');
    $d->rect(260, 105, 1330, 1360, 'SISTEM INFORMASI ABI TAMAN', [
        'rounded' => false, 'fill' => '#f8fafc', 'stroke' => '#334155',
        'align' => 'left', 'fontSize' => 14, 'bold' => true,
    ]);

    $d->actor(60, 265, 'Customer');
    $d->actor(60, 945, 'Staff');
    $d->actor(1695, 350, 'Pekerja Lapangan');
    $d->actor(1695, 1040, 'Boss');

    $shared = [
        [790, 145, 'Login'],
        [790, 225, 'Kelola Profil'],
        [790, 305, 'Logout'],
    ];
    foreach ($shared as [$x, $y, $label]) {
        $d->ellipse($x, $y, 250, 58, $label, ['fill' => '#ffffff', 'stroke' => '#2563eb']);
    }

    $customer = [
        [350, 420, 'Melihat Katalog Layanan'],
        [350, 510, 'Membuat Booking'],
        [350, 600, 'Memantau Status Pesanan'],
        [350, 690, 'Menyetujui / Menolak Estimasi'],
        [350, 780, 'Upload Bukti Pembayaran'],
        [350, 870, 'Melihat / Cetak Invoice'],
        [350, 960, 'Memberikan Ulasan'],
    ];
    foreach ($customer as [$x, $y, $label]) {
        $d->ellipse($x, $y, 310, 62, $label, ['fill' => '#eff6ff', 'stroke' => '#2563eb']);
    }

    $staff = [
        [765, 420, 'Konfirmasi Booking'],
        [765, 510, 'Menjadwalkan Survei'],
        [765, 600, 'Review / Kirim Estimasi'],
        [765, 690, 'Memulai Pengerjaan'],
        [765, 780, 'Review / Kirim Invoice'],
        [765, 870, 'Verifikasi Pembayaran'],
        [765, 960, 'Kelola Master Data'],
        [765, 1050, 'Kelola Pengguna'],
        [765, 1140, 'Melihat Laporan & Log'],
    ];
    foreach ($staff as [$x, $y, $label]) {
        $d->ellipse($x, $y, 320, 62, $label, ['fill' => '#ffffff', 'stroke' => '#475569']);
    }

    $field = [
        [1190, 420, 'Melihat Tugas'],
        [1190, 510, 'Melaksanakan Survei'],
        [1190, 600, 'Menyusun Estimasi'],
        [1190, 690, 'Update Progres Pekerjaan'],
        [1190, 780, 'Membuat Revisi Estimasi'],
        [1190, 870, 'Mengirim Data Final'],
    ];
    foreach ($field as [$x, $y, $label]) {
        $d->ellipse($x, $y, 310, 62, $label, ['fill' => '#f0fdf4', 'stroke' => '#15803d']);
    }

    $boss = [
        [1190, 1010, 'Melihat Dashboard KPI'],
        [1190, 1100, 'Monitoring Operasional'],
        [1190, 1190, 'Melihat Laporan Pendapatan'],
        [1190, 1280, 'Ekspor CSV / Cetak Laporan'],
    ];
    foreach ($boss as [$x, $y, $label]) {
        $d->ellipse($x, $y, 310, 62, $label, ['fill' => '#fff7ed', 'stroke' => '#ea580c']);
    }

    foreach ([[130, 300], [130, 980], [1765, 385], [1765, 1075]] as $actorPoint) {
        foreach ([[915, 174], [915, 254], [915, 334]] as $target) {
            $d->edge($actorPoint[0], $actorPoint[1], $target[0], $target[1], '', ['arrow' => false, 'stroke' => '#94a3b8']);
        }
    }
    foreach ($customer as [$x, $y]) {
        $d->edge(130, 300, $x, $y + 31, '', ['arrow' => false, 'stroke' => '#2563eb']);
    }
    foreach ($staff as [$x, $y]) {
        $d->edge(130, 980, $x, $y + 31, '', ['arrow' => false, 'stroke' => '#475569']);
    }
    foreach ($field as [$x, $y]) {
        $d->edge(1765, 385, $x + 310, $y + 31, '', ['arrow' => false, 'stroke' => '#15803d']);
    }
    foreach ($boss as [$x, $y]) {
        $d->edge(1765, 1075, $x + 310, $y + 31, '', ['arrow' => false, 'stroke' => '#ea580c']);
    }
    addFooter($d);
    return $d;
}

function activityLogin(): Diagram
{
    $d = new Diagram('04-activity-diagram-login', 'Activity Diagram Login', 1050, 1000);
    addTitle($d, 'Validasi autentikasi dan pengalihan dashboard berdasarkan role');
    $d->lane(55, 100, 450, 820, 'Pengguna', ['fill' => '#f8fafc', 'headerFill' => '#dbeafe']);
    $d->lane(505, 100, 490, 820, 'Sistem', ['fill' => '#eff6ff', 'headerFill' => '#bfdbfe']);
    $d->circle(250, 155, 30, '', ['fill' => '#0f172a', 'stroke' => '#0f172a']);
    $d->rect(150, 225, 230, 68, "Membuka halaman\nlogin", ['fill' => '#ffffff', 'shadow' => true]);
    $d->rect(600, 225, 300, 68, "Menampilkan formulir\nlogin", ['fill' => '#dbeafe', 'stroke' => '#2563eb']);
    $d->rect(150, 345, 230, 78, "Mengisi email dan\npassword", ['fill' => '#ffffff', 'shadow' => true]);
    $d->rect(150, 485, 230, 62, "Klik tombol Login", ['fill' => '#ffffff', 'shadow' => true]);
    $d->rect(600, 470, 300, 78, "Memeriksa CSRF, akun aktif,\ndan password", ['fill' => '#dbeafe', 'stroke' => '#2563eb']);
    $d->diamond(665, 610, 170, 110, "Data\nvalid?", ['fill' => '#fef3c7', 'stroke' => '#d97706']);
    $d->rect(560, 775, 190, 68, "Membuat session dan\nmencatat login", ['fill' => '#dcfce7', 'stroke' => '#15803d']);
    $d->rect(785, 775, 170, 68, "Menampilkan pesan\ngagal login", ['fill' => '#fee2e2', 'stroke' => '#dc2626']);
    $d->rect(150, 775, 230, 68, "Melihat dashboard\nsesuai role", ['fill' => '#ffffff', 'shadow' => true]);
    $d->circle(250, 870, 34, '', ['fill' => '#0f172a', 'stroke' => '#ef4444', 'strokeWidth' => 3]);
    $d->circle(257, 877, 20, '', ['fill' => '#0f172a', 'stroke' => '#0f172a']);

    $d->polyline([[265, 185], [265, 225]]);
    $d->polyline([[380, 259], [600, 259]], 'Request halaman', ['labelW' => 115]);
    $d->polyline([[600, 280], [450, 280], [450, 384], [380, 384]], 'Form login', ['dashed' => true, 'labelW' => 95]);
    $d->polyline([[265, 423], [265, 485]]);
    $d->polyline([[380, 516], [600, 509]], 'Kredensial', ['labelW' => 85]);
    $d->polyline([[750, 548], [750, 610]]);
    $d->polyline([[705, 720], [655, 775]], 'Ya', ['labelW' => 45]);
    $d->polyline([[795, 665], [870, 665], [870, 775]], 'Tidak', ['labelW' => 55]);
    $d->polyline([[560, 809], [380, 809]], 'Redirect', ['labelW' => 80]);
    $d->polyline([[870, 843], [870, 890], [470, 890], [470, 384], [380, 384]], 'Ulangi login', ['labelW' => 95]);
    $d->polyline([[265, 843], [265, 870]]);
    addFooter($d);
    return $d;
}

function activityBooking(): Diagram
{
    $d = new Diagram('05-activity-diagram-proses-booking', 'Activity Diagram Proses Booking Jasa Taman', 1640, 1510);
    addTitle($d, 'Alur utama booking, survei, estimasi, pengerjaan, pembayaran, dan ulasan');
    $laneY = 105;
    $laneH = 1330;
    $xs = [35, 430, 825, 1220];
    $names = ['Customer', 'Staff', 'Pekerja Lapangan', 'Sistem'];
    foreach ($xs as $index => $x) {
        $d->lane($x, $laneY, 395, $laneH, $names[$index], [
            'fill' => $index === 3 ? '#eff6ff' : ($index === 2 ? '#f0fdf4' : '#f8fafc'),
            'headerFill' => ['#dbeafe', '#e2e8f0', '#bbf7d0', '#bfdbfe'][$index],
        ]);
    }
    $d->circle(205, 160, 28, '', ['fill' => '#0f172a', 'stroke' => '#0f172a']);
    $d->rect(105, 220, 230, 68, "Mengisi dan mengirim\nbooking", ['fill' => '#ffffff', 'shadow' => true]);
    $d->rect(1300, 220, 235, 68, "Menyimpan booking dan\nmengirim notifikasi", ['fill' => '#dbeafe', 'stroke' => '#2563eb']);
    $d->diamond(535, 335, 150, 100, "Booking\nditerima?", ['fill' => '#fef3c7', 'stroke' => '#d97706']);
    $d->rect(500, 485, 220, 68, "Menetapkan pekerja dan\njadwal survei", ['fill' => '#ffffff', 'shadow' => true]);
    $d->rect(895, 485, 220, 68, "Melaksanakan dan\nmenyimpan hasil survei", ['fill' => '#dcfce7', 'stroke' => '#15803d']);
    $d->rect(895, 620, 220, 68, "Menyusun estimasi\nbiaya", ['fill' => '#dcfce7', 'stroke' => '#15803d']);
    $d->rect(500, 620, 220, 68, "Review dan kirim\nestimasi", ['fill' => '#ffffff', 'shadow' => true]);
    $d->diamond(145, 605, 150, 110, "Estimasi\ndisetujui?", ['fill' => '#fef3c7', 'stroke' => '#d97706']);
    $d->rect(500, 775, 220, 68, "Memulai pekerjaan", ['fill' => '#ffffff', 'shadow' => true]);
    $d->rect(895, 775, 220, 68, "Mengerjakan dan\nupdate progres", ['fill' => '#dcfce7', 'stroke' => '#15803d']);
    $d->diamond(930, 910, 150, 100, "Ada\nperubahan?", ['fill' => '#fef3c7', 'stroke' => '#d97706']);
    $d->rect(895, 1060, 220, 68, "Membuat revisi estimasi\natau data final", ['fill' => '#dcfce7', 'stroke' => '#15803d']);
    $d->rect(500, 1060, 220, 68, "Membuat dan mengirim\ninvoice final", ['fill' => '#ffffff', 'shadow' => true]);
    $d->rect(105, 1060, 230, 68, "Upload bukti\npembayaran", ['fill' => '#ffffff', 'shadow' => true]);
    $d->rect(500, 1195, 220, 68, "Verifikasi pembayaran\ndan tutup order", ['fill' => '#ffffff', 'shadow' => true]);
    $d->rect(105, 1195, 230, 68, "Memberikan rating dan\nulasan", ['fill' => '#ffffff', 'shadow' => true]);
    $d->circle(205, 1325, 34, '', ['fill' => '#0f172a', 'stroke' => '#ef4444', 'strokeWidth' => 3]);
    $d->circle(212, 1332, 20, '', ['fill' => '#0f172a', 'stroke' => '#0f172a']);
    $d->rect(500, 345, 180, 54, 'Membatalkan booking', ['fill' => '#fee2e2', 'stroke' => '#dc2626']);

    $edges = [
        [[[219, 188], [219, 220]], ''],
        [[[335, 254], [1300, 254]], 'Data booking'],
        [[[1300, 288], [1300, 315], [610, 315], [610, 335]], 'Notifikasi staff'],
        [[[610, 435], [610, 485]], 'Ya'],
        [[[535, 385], [465, 385], [465, 372], [500, 372]], 'Tidak'],
        [[[720, 519], [895, 519]], 'Penugasan'],
        [[[1005, 553], [1005, 620]], ''],
        [[[895, 654], [720, 654]], 'Estimasi draft'],
        [[[500, 654], [295, 654]], 'Kirim estimasi'],
        [[[220, 715], [220, 745], [610, 745], [610, 775]], 'Ya'],
        [[[145, 660], [75, 660], [75, 654], [500, 654]], 'Tidak / revisi'],
        [[[720, 809], [895, 809]], 'Instruksi kerja'],
        [[[1005, 843], [1005, 910]], ''],
        [[[1005, 1010], [1005, 1060]], 'Tidak / selesai'],
        [[[1080, 960], [1165, 960], [1165, 654], [1115, 654]], 'Ya / revisi'],
        [[[895, 1094], [720, 1094]], 'Data final'],
        [[[500, 1094], [335, 1094]], 'Invoice'],
        [[[335, 1094], [500, 1229]], 'Bukti bayar'],
        [[[500, 1229], [335, 1229]], 'Order selesai'],
        [[[220, 1263], [220, 1325]], ''],
    ];
    foreach ($edges as [$points, $label]) {
        $d->polyline($points, $label, ['labelW' => 115]);
    }
    addFooter($d);
    return $d;
}

function sequenceLogin(): Diagram
{
    $d = new Diagram('06-sequence-diagram-login', 'Sequence Diagram Login', 1280, 970);
    addTitle($d, 'Interaksi pengguna, antarmuka, backend, dan database saat autentikasi');
    $participants = [
        [90, 'Pengguna', '#ffffff', '#334155'],
        [365, 'Halaman Login', '#eff6ff', '#2563eb'],
        [675, 'Sistem Backend', '#eff6ff', '#2563eb'],
        [985, 'Database', '#f0fdf4', '#15803d'],
    ];
    foreach ($participants as [$x, $name, $fill, $stroke]) {
        $d->rect($x, 115, 205, 64, $name, ['fill' => $fill, 'stroke' => $stroke, 'bold' => true, 'shadow' => true]);
        $cx = $x + 102.5;
        $d->edge($cx, 179, $cx, 875, '', ['arrow' => false, 'dashed' => true, 'stroke' => '#94a3b8']);
    }
    $d->rect(450, 390, 15, 330, '', ['rounded' => false, 'fill' => '#bfdbfe', 'stroke' => '#2563eb']);
    $d->rect(760, 485, 15, 235, '', ['rounded' => false, 'fill' => '#bfdbfe', 'stroke' => '#2563eb']);
    $d->rect(1070, 535, 15, 100, '', ['rounded' => false, 'fill' => '#bbf7d0', 'stroke' => '#15803d']);
    $messages = [
        [192, 240, 467, 240, '1. Buka halaman login', false],
        [467, 305, 192, 305, '2. Tampilkan formulir', true],
        [192, 390, 467, 390, '3. Isi email dan password', false],
        [467, 465, 777, 465, '4. Kirim kredensial + CSRF', false],
        [777, 535, 1087, 535, '5. Cari user berdasarkan email', false],
        [1087, 615, 777, 615, '6. Return data user', true],
        [777, 690, 467, 690, '7. Status autentikasi', true],
        [467, 770, 192, 770, '8. Redirect dashboard / pesan gagal', true],
    ];
    foreach ($messages as [$x1, $y1, $x2, $y2, $label, $return]) {
        $d->edge($x1, $y1, $x2, $y2, $label, [
            'stroke' => $return ? '#64748b' : '#2563eb',
            'dashed' => $return,
            'labelW' => 220,
            'labelX' => ($x1 + $x2) / 2,
            'labelY' => $y1 - 10,
        ]);
    }
    $d->rect(625, 650, 405, 105, "alt\n[valid] buat session, update last_login_at, catat activity log\n[tidak valid] kembalikan pesan gagal login", [
        'rounded' => false, 'fill' => '#fff7ed', 'stroke' => '#d97706', 'align' => 'left', 'fontSize' => 12,
    ]);
    addFooter($d);
    return $d;
}

function sequenceBooking(): Diagram
{
    $d = new Diagram('07-sequence-diagram-proses-booking', 'Sequence Diagram Proses Booking Jasa Taman', 1780, 1380);
    addTitle($d, 'Interaksi lintas aktor dari pengajuan booking sampai order selesai');
    $participants = [
        [35, 'Customer', '#ffffff', '#334155'],
        [310, 'Website', '#eff6ff', '#2563eb'],
        [585, 'Backend', '#eff6ff', '#2563eb'],
        [860, 'Database', '#f0fdf4', '#15803d'],
        [1135, 'Staff', '#ffffff', '#475569'],
        [1410, "Pekerja\nLapangan", '#f0fdf4', '#15803d'],
    ];
    $centers = [];
    foreach ($participants as [$x, $name, $fill, $stroke]) {
        $d->rect($x, 110, 210, 65, $name, ['fill' => $fill, 'stroke' => $stroke, 'bold' => true, 'shadow' => true]);
        $cx = $x + 105;
        $centers[] = $cx;
        $d->edge($cx, 175, $cx, 1290, '', ['arrow' => false, 'dashed' => true, 'stroke' => '#94a3b8']);
    }
    [$customer, $website, $backend, $db, $staff, $worker] = $centers;
    $messages = [
        [$customer, $website, 235, '1. Kirim formulir booking'],
        [$website, $backend, 295, '2. Validasi request'],
        [$backend, $db, 355, '3. INSERT booking'],
        [$db, $backend, 415, '4. Return booking_code', true],
        [$backend, $staff, 475, '5. Notifikasi booking baru'],
        [$staff, $backend, 535, '6. Konfirmasi dan jadwalkan survei'],
        [$backend, $db, 595, '7. Simpan schedule & status'],
        [$backend, $worker, 655, '8. Notifikasi tugas survei'],
        [$worker, $backend, 715, '9. Kirim hasil survei & estimasi'],
        [$backend, $db, 775, '10. Simpan survey, foto, estimate'],
        [$staff, $backend, 835, '11. Review dan kirim estimasi'],
        [$backend, $customer, 895, '12. Tampilkan estimasi'],
        [$customer, $backend, 955, '13. Setujui / tolak estimasi'],
        [$staff, $worker, 1015, '14. Instruksi mulai pekerjaan'],
        [$worker, $backend, 1075, '15. Update progres & data final'],
        [$backend, $db, 1135, '16. Simpan progress / invoice'],
        [$backend, $customer, 1195, '17. Tampilkan invoice'],
        [$customer, $backend, 1255, '18. Upload bukti pembayaran'],
    ];
    foreach ($messages as $message) {
        [$x1, $x2, $y, $label] = $message;
        $return = $message[4] ?? false;
        $d->edge($x1, $y, $x2, $y, $label, [
            'stroke' => $return ? '#64748b' : '#2563eb',
            'dashed' => $return,
            'labelW' => 230,
            'labelX' => ($x1 + $x2) / 2,
            'labelY' => $y - 10,
        ]);
    }
    $d->rect(70, 915, 1040, 92, "alt Persetujuan estimasi\n[disetujui] status menjadi Estimasi Disetujui dan pekerjaan dapat dimulai\n[ditolak] sistem membuat kewajiban Survey Fee dan meminta revisi/penutupan", [
        'rounded' => false, 'fill' => '#fff7ed', 'stroke' => '#d97706', 'align' => 'left', 'fontSize' => 12,
    ]);
    addFooter($d);
    return $d;
}

function classDiagram(): Diagram
{
    $d = new Diagram('08-class-diagram', 'Class Diagram', 2080, 1500);
    addTitle($d, 'Struktur kelas domain utama dan operasi bisnis ABI Taman');
    $classes = [
        ['User', 45, 115, 300, ['- id: int', '- name: string', '- email: string', '- role: enum', '- status: enum'], ['+ login()', '+ updateProfile()', '+ changePassword()'], '#dbeafe', '#2563eb'],
        ['ServiceCategory', 390, 115, 280, ['- id: int', '- name: string', '- slug: string', '- status: enum'], ['+ create()', '+ update()', '+ deactivate()'], '#f1f5f9', '#475569'],
        ['Service', 715, 115, 300, ['- id: int', '- categoryId: int', '- serviceCode: string', '- name: string', '- basePrice: bigint', '- status: enum'], ['+ getCatalog()', '+ create()', '+ update()'], '#f1f5f9', '#475569'],
        ['Booking', 1060, 115, 340, ['- id: int', '- bookingCode: string', '- customerId: int', '- serviceId: int', '- surveyDate: date', '- location: text', '- status: enum'], ['+ create()', '+ assignWorker()', '+ changeStatus()', '+ cancel()'], '#dbeafe', '#2563eb'],
        ['Schedule', 1445, 115, 285, ['- id: int', '- bookingId: int', '- workerId: int', '- type: enum', '- date: date', '- status: enum'], ['+ schedule()', '+ reschedule()', '+ complete()'], '#dcfce7', '#15803d'],
        ['Survey', 1775, 115, 270, ['- id: int', '- bookingId: int', '- workerId: int', '- actualArea: decimal', '- condition: text'], ['+ start()', '+ saveResult()', '+ uploadPhoto()'], '#dcfce7', '#15803d'],
        ['Estimate', 210, 600, 330, ['- id: int', '- estimateCode: string', '- bookingId: int', '- surveyId: int', '- grandTotal: bigint', '- status: enum'], ['+ calculate()', '+ send()', '+ approve()', '+ reject()'], '#fef3c7', '#d97706'],
        ['EstimateItem', 585, 600, 295, ['- id: int', '- estimateId: int', '- itemType: enum', '- quantity: decimal', '- unitPrice: bigint', '- subtotal: bigint'], ['+ calculateSubtotal()'], '#fff7ed', '#d97706'],
        ['EstimateRevision', 925, 600, 330, ['- id: int', '- revisionCode: string', '- estimateId: int', '- reason: text', '- newTotal: bigint', '- status: enum'], ['+ create()', '+ send()', '+ approve()', '+ reject()'], '#fff7ed', '#d97706'],
        ['WorkProgress', 1300, 600, 315, ['- id: int', '- bookingId: int', '- workerId: int', '- progressPercent: int', '- title: string', '- note: text'], ['+ addProgress()', '+ completeWork()'], '#dcfce7', '#15803d'],
        ['FinalInvoice', 1660, 600, 330, ['- id: int', '- invoiceCode: string', '- bookingId: int', '- estimateId: int', '- finalTotal: bigint', '- paymentStatus: enum'], ['+ generate()', '+ send()', '+ markPaid()', '+ print()'], '#dbeafe', '#2563eb'],
        ['Payment', 435, 1080, 300, ['- id: int', '- bookingId: int', '- invoiceId: int', '- paymentType: enum', '- amount: bigint', '- proof: string'], ['+ uploadProof()', '+ verify()'], '#dcfce7', '#15803d'],
        ['Review', 890, 1080, 290, ['- id: int', '- bookingId: int', '- customerId: int', '- rating: int', '- comment: text'], ['+ create()', '+ update()'], '#fce7f3', '#be185d'],
        ['Notification', 1335, 1080, 310, ['- id: int', '- userId: int', '- roleTarget: enum', '- title: string', '- isRead: bool'], ['+ send()', '+ markRead()'], '#f1f5f9', '#475569'],
    ];
    $centers = [];
    foreach ($classes as [$name, $x, $y, $w, $attrs, $methods, $fill, $stroke]) {
        $fields = array_merge($attrs, ['────────────'], $methods);
        $id = $d->table($x, $y, $w, $name, $fields, [
            'fill' => '#ffffff',
            'headerFill' => $fill,
            'stroke' => $stroke,
            'fontSize' => 11,
            'lineHeight' => 20,
            'shadow' => true,
        ]);
        $centers[$name] = [$x + $w / 2, $y, $x + $w / 2, $y + 34 + 10 + count($fields) * 20];
    }
    $relations = [
        ['ServiceCategory', 'Service', '1', '0..*'],
        ['User', 'Booking', '1', '0..* customer'],
        ['Service', 'Booking', '1', '0..*'],
        ['Booking', 'Schedule', '1', '0..*'],
        ['Schedule', 'Survey', '1', '0..1'],
        ['Booking', 'Estimate', '1', '0..*'],
        ['Estimate', 'EstimateItem', '1', '1..*'],
        ['Estimate', 'EstimateRevision', '1', '0..*'],
        ['Booking', 'WorkProgress', '1', '0..*'],
        ['Booking', 'FinalInvoice', '1', '0..1'],
        ['FinalInvoice', 'Payment', '1', '0..*'],
        ['Booking', 'Review', '1', '0..1'],
        ['User', 'Notification', '1', '0..*'],
    ];
    foreach ($relations as [$from, $to, $fromCard, $toCard]) {
        $a = $centers[$from];
        $b = $centers[$to];
        $x1 = $a[2];
        $y1 = $a[3];
        $x2 = $b[0];
        $y2 = $b[1];
        if (abs($y2 - $y1) < 80) {
            $x1 = $a[0] < $b[0] ? $a[0] + 145 : $a[0] - 145;
            $x2 = $a[0] < $b[0] ? $b[0] - 145 : $b[0] + 145;
            $y1 = ($a[1] + $a[3]) / 2;
            $y2 = ($b[1] + $b[3]) / 2;
        }
        $d->edge($x1, $y1, $x2, $y2, "$fromCard   $toCard", [
            'arrow' => false,
            'stroke' => '#64748b',
            'labelW' => 105,
            'fontSize' => 10,
        ]);
    }
    addFooter($d);
    return $d;
}

function erd(): Diagram
{
    $d = new Diagram('09-erd', 'Entity Relationship Diagram (ERD)', 2600, 2000);
    addTitle($d, 'Model relasional lengkap berdasarkan database/refactor_schema.sql');
    $tables = schemaTables();
    $positions = [
        'users' => [50, 115], 'service_categories' => [390, 115], 'services' => [750, 115],
        'materials' => [1110, 115], 'labor_rates' => [1450, 115], 'additional_cost_types' => [1790, 115],
        'settings' => [2160, 115],
        'bookings' => [50, 570], 'booking_status_histories' => [430, 570], 'schedules' => [820, 570],
        'surveys' => [1160, 570], 'survey_photos' => [1500, 570], 'notifications' => [1840, 570],
        'activity_logs' => [2180, 570],
        'estimates' => [50, 1080], 'estimate_items' => [390, 1080], 'estimate_revisions' => [730, 1080],
        'estimate_revision_items' => [1110, 1080], 'work_progress' => [1510, 1080],
        'reviews' => [1870, 1080],
        'final_invoices' => [120, 1540], 'final_invoice_items' => [520, 1540], 'payments' => [910, 1540],
    ];
    $dims = [];
    foreach ($positions as $name => [$x, $y]) {
        $fields = $tables[$name];
        $w = in_array($name, ['booking_status_histories', 'estimate_revision_items', 'additional_cost_types'], true) ? 330 : 300;
        $d->table($x, $y, $w, $name, $fields, [
            'fill' => '#ffffff',
            'headerFill' => str_contains($name, 'invoice') || $name === 'payments'
                ? '#dbeafe'
                : (in_array($name, ['bookings', 'estimates', 'surveys', 'work_progress'], true) ? '#dcfce7' : '#f1f5f9'),
            'stroke' => '#475569',
            'fontSize' => 10,
            'lineHeight' => 17,
            'shadow' => true,
        ]);
        $h = 34 + 10 + count($fields) * 17;
        $dims[$name] = [$x, $y, $w, $h];
    }
    foreach (schemaRelations() as [$parent, $child, $label]) {
        [$px, $py, $pw, $ph] = $dims[$parent];
        [$cx, $cy, $cw, $ch] = $dims[$child];
        $pcx = $px + $pw / 2;
        $pcy = $py + $ph / 2;
        $ccx = $cx + $cw / 2;
        $ccy = $cy + $ch / 2;
        if (abs($ccy - $pcy) > abs($ccx - $pcx)) {
            $x1 = $pcx;
            $y1 = $ccy > $pcy ? $py + $ph : $py;
            $x2 = $ccx;
            $y2 = $ccy > $pcy ? $cy : $cy + $ch;
        } else {
            $x1 = $ccx > $pcx ? $px + $pw : $px;
            $y1 = $pcy;
            $x2 = $ccx > $pcx ? $cx : $cx + $cw;
            $y2 = $ccy;
        }
        $d->edge($x1, $y1, $x2, $y2, "1 — N\n$label", [
            'arrow' => false,
            'stroke' => '#64748b',
            'labelW' => 125,
            'fontSize' => 9,
        ]);
    }
    addFooter($d);
    return $d;
}

function erdTransformation(): Diagram
{
    $d = new Diagram('10-transformasi-erd-ke-lrs', 'Transformasi ERD ke LRS', 2100, 1460);
    addTitle($d, 'Pemetaan entitas, relasi, dan foreign key menjadi struktur record logis');

    $d->rect(35, 100, 820, 1280, 'MODEL KONSEPTUAL (ERD)', [
        'rounded' => false, 'fill' => '#f8fafc', 'stroke' => '#64748b',
        'align' => 'left', 'bold' => true,
    ]);
    $d->rect(895, 100, 300, 1280, 'ATURAN TRANSFORMASI', [
        'rounded' => false, 'fill' => '#fff7ed', 'stroke' => '#d97706',
        'align' => 'left', 'bold' => true,
    ]);
    $d->rect(1235, 100, 830, 1280, 'STRUKTUR LOGIS (LRS)', [
        'rounded' => false, 'fill' => '#eff6ff', 'stroke' => '#2563eb',
        'align' => 'left', 'bold' => true,
    ]);

    $entities = [
        [85, 190, 'USERS'],
        [570, 190, 'SERVICES'],
        [320, 400, 'BOOKINGS'],
        [85, 655, 'SURVEYS'],
        [570, 655, 'ESTIMATES'],
        [85, 910, 'FINAL_INVOICES'],
        [570, 910, 'PAYMENTS'],
        [320, 1160, 'REVIEWS'],
    ];
    foreach ($entities as [$x, $y, $name]) {
        $d->rect($x, $y, 210, 64, $name, ['fill' => '#ffffff', 'stroke' => '#2563eb', 'bold' => true, 'shadow' => true]);
    }
    $relationships = [
        [342, 210, 'MEMBUAT'],
        [342, 585, 'DASAR'],
        [342, 840, 'DITAGIH'],
        [342, 1045, 'MEMBAYAR'],
    ];
    foreach ($relationships as [$x, $y, $name]) {
        $d->diamond($x, $y, 170, 90, $name, ['fill' => '#fef3c7', 'stroke' => '#d97706', 'fontSize' => 11]);
    }
    $d->polyline([[295, 222], [342, 255]], '1', ['arrow' => false, 'labelW' => 35]);
    $d->polyline([[570, 222], [512, 255]], '1', ['arrow' => false, 'labelW' => 35]);
    $d->polyline([[427, 300], [427, 400]], 'N', ['arrow' => false, 'labelW' => 35]);
    $d->polyline([[190, 719], [342, 630]], '1', ['arrow' => false, 'labelW' => 35]);
    $d->polyline([[675, 719], [512, 630]], '1', ['arrow' => false, 'labelW' => 35]);
    $d->polyline([[427, 585], [427, 464]], 'N', ['arrow' => false, 'labelW' => 35]);
    $d->polyline([[190, 974], [342, 885]], '1', ['arrow' => false, 'labelW' => 35]);
    $d->polyline([[675, 974], [512, 885]], 'N', ['arrow' => false, 'labelW' => 35]);
    $d->polyline([[427, 840], [427, 464]], '1', ['arrow' => false, 'labelW' => 35]);
    $d->polyline([[190, 942], [342, 1090]], '1', ['arrow' => false, 'labelW' => 35]);
    $d->polyline([[675, 942], [512, 1090]], 'N', ['arrow' => false, 'labelW' => 35]);
    $d->polyline([[427, 1135], [427, 1160]], '1 : 0..1', ['arrow' => false, 'labelW' => 75]);

    $rules = [
        [930, 210, "1. Setiap entitas kuat\nmenjadi satu tabel."],
        [930, 390, "2. Relasi 1:N dipindahkan\nmenjadi FK di sisi N."],
        [930, 600, "3. Relasi beratribut atau\nitem berulang menjadi\ntabel detail."],
        [930, 840, "4. Relasi 1:1 memakai FK\nunik pada tabel anak."],
        [930, 1050, "5. PK setiap tabel memakai\nid auto increment;\nkode bisnis tetap UNIQUE."],
    ];
    foreach ($rules as [$x, $y, $text]) {
        $d->rect($x, $y, 230, 115, $text, ['fill' => '#ffffff', 'stroke' => '#d97706', 'shadow' => true, 'fontSize' => 12]);
    }

    $lrsTables = [
        ['users', 1280, 185, ['PK id', 'name', 'email (UQ)', 'role']],
        ['services', 1580, 185, ['PK id', 'FK category_id', 'service_code (UQ)', 'name']],
        ['bookings', 1280, 430, ['PK id', 'FK customer_id', 'FK service_id', 'FK assigned_worker_id', 'status']],
        ['surveys', 1580, 430, ['PK id', 'FK booking_id', 'FK worker_id', 'actual_area']],
        ['estimates', 1280, 705, ['PK id', 'FK booking_id', 'FK survey_id', 'grand_total']],
        ['estimate_items', 1580, 705, ['PK id', 'FK estimate_id', 'item_type', 'subtotal']],
        ['final_invoices', 1280, 980, ['PK id', 'FK booking_id', 'FK estimate_id', 'final_total']],
        ['payments', 1580, 980, ['PK id', 'FK booking_id', 'FK invoice_id', 'amount']],
        ['reviews', 1280, 1200, ['PK id', 'FK booking_id (UQ)', 'FK customer_id', 'rating']],
    ];
    foreach ($lrsTables as [$name, $x, $y, $fields]) {
        $d->table($x, $y, 250, $name, $fields, [
            'fill' => '#ffffff', 'headerFill' => '#dbeafe', 'stroke' => '#2563eb',
            'fontSize' => 11, 'lineHeight' => 19, 'shadow' => true,
        ]);
    }
    $d->polyline([[1530, 525], [1580, 525]], '1 : 0..1', ['arrow' => false, 'labelW' => 70]);
    $d->polyline([[1530, 790], [1580, 790]], '1 : N', ['arrow' => false, 'labelW' => 55]);
    $d->polyline([[1530, 1065], [1580, 1065]], '1 : N', ['arrow' => false, 'labelW' => 55]);
    addFooter($d);
    return $d;
}

function lrs(): Diagram
{
    $d = new Diagram('11-lrs', 'Logical Record Structure (LRS)', 2760, 2100);
    addTitle($d, 'Struktur record, primary key, foreign key, dan hubungan seluruh tabel ABI Taman');
    $tables = schemaTables();
    $positions = [
        'service_categories' => [50, 130], 'services' => [400, 130], 'materials' => [750, 130],
        'labor_rates' => [1100, 130], 'additional_cost_types' => [1450, 130], 'settings' => [1830, 130],
        'users' => [2220, 130],
        'bookings' => [50, 610], 'booking_status_histories' => [430, 610], 'schedules' => [820, 610],
        'surveys' => [1160, 610], 'survey_photos' => [1500, 610], 'notifications' => [1840, 610],
        'activity_logs' => [2190, 610],
        'estimates' => [50, 1130], 'estimate_items' => [390, 1130], 'estimate_revisions' => [730, 1130],
        'estimate_revision_items' => [1110, 1130], 'work_progress' => [1510, 1130], 'reviews' => [1880, 1130],
        'final_invoices' => [180, 1640], 'final_invoice_items' => [580, 1640], 'payments' => [990, 1640],
    ];
    $dims = [];
    foreach ($positions as $name => [$x, $y]) {
        $fields = $tables[$name];
        $w = in_array($name, ['booking_status_histories', 'estimate_revision_items', 'additional_cost_types'], true) ? 330 : 300;
        $d->table($x, $y, $w, strtoupper($name), $fields, [
            'fill' => '#ffffff',
            'headerFill' => str_contains($name, 'invoice') || $name === 'payments'
                ? '#dbeafe'
                : (in_array($name, ['bookings', 'estimates', 'surveys', 'work_progress'], true) ? '#dcfce7' : '#f1f5f9'),
            'stroke' => '#334155',
            'fontSize' => 10,
            'lineHeight' => 17,
            'shadow' => true,
        ]);
        $dims[$name] = [$x, $y, $w, 34 + 10 + count($fields) * 17];
    }
    foreach (schemaRelations() as [$parent, $child, $label]) {
        [$px, $py, $pw, $ph] = $dims[$parent];
        [$cx, $cy, $cw, $ch] = $dims[$child];
        $pcx = $px + $pw / 2;
        $pcy = $py + $ph / 2;
        $ccx = $cx + $cw / 2;
        $ccy = $cy + $ch / 2;
        if (abs($ccy - $pcy) > abs($ccx - $pcx)) {
            $from = [$pcx, $ccy > $pcy ? $py + $ph : $py];
            $to = [$ccx, $ccy > $pcy ? $cy : $cy + $ch];
        } else {
            $from = [$ccx > $pcx ? $px + $pw : $px, $pcy];
            $to = [$ccx > $pcx ? $cx : $cx + $cw, $ccy];
        }
        $d->polyline([$from, $to], "1 : N\n$label", [
            'arrow' => false, 'stroke' => '#2563eb', 'labelW' => 120, 'fontSize' => 9,
        ]);
    }
    addFooter($d);
    return $d;
}

function schemaTables(): array
{
    return [
        'users' => ['PK id', 'name', 'email UQ', 'password', 'role', 'phone', 'address', 'status'],
        'service_categories' => ['PK id', 'name', 'slug UQ', 'description', 'image', 'status'],
        'services' => ['PK id', 'FK category_id', 'service_code UQ', 'name', 'slug UQ', 'base_price_per_10m', 'status'],
        'materials' => ['PK id', 'material_code UQ', 'name', 'unit', 'price_per_unit', 'default_qty_per_10m', 'status'],
        'labor_rates' => ['PK id', 'name', 'description', 'daily_rate', 'status'],
        'additional_cost_types' => ['PK id', 'name', 'description', 'default_price', 'unit', 'status'],
        'bookings' => ['PK id', 'booking_code UQ', 'FK customer_id', 'FK service_id', 'FK assigned_worker_id', 'requested_survey_date', 'location_address', 'status'],
        'booking_status_histories' => ['PK id', 'FK booking_id', 'old_status', 'new_status', 'FK changed_by', 'note'],
        'schedules' => ['PK id', 'schedule_code UQ', 'FK booking_id', 'FK worker_id', 'schedule_type', 'schedule_date', 'status'],
        'surveys' => ['PK id', 'FK booking_id', 'FK worker_id', 'actual_area', 'location_condition', 'estimated_work_days'],
        'survey_photos' => ['PK id', 'FK survey_id', 'FK booking_id', 'photo_path', 'photo_type', 'FK uploaded_by'],
        'estimates' => ['PK id', 'estimate_code UQ', 'FK booking_id', 'FK survey_id', 'FK created_by', 'grand_total', 'status'],
        'estimate_items' => ['PK id', 'FK estimate_id', 'item_type', 'item_name', 'quantity', 'unit_price', 'subtotal'],
        'estimate_revisions' => ['PK id', 'revision_code UQ', 'FK estimate_id', 'FK booking_id', 'FK created_by', 'new_total', 'status'],
        'estimate_revision_items' => ['PK id', 'FK revision_id', 'item_name', 'quantity', 'unit_price', 'subtotal'],
        'work_progress' => ['PK id', 'FK booking_id', 'FK worker_id', 'progress_percent', 'title', 'note'],
        'final_invoices' => ['PK id', 'invoice_code UQ', 'FK booking_id', 'FK estimate_id', 'FK created_by', 'final_total', 'payment_status'],
        'final_invoice_items' => ['PK id', 'FK final_invoice_id', 'item_type', 'item_name', 'quantity', 'unit_price', 'subtotal'],
        'payments' => ['PK id', 'FK booking_id', 'FK invoice_id', 'payment_type', 'amount', 'payment_status', 'FK marked_by'],
        'notifications' => ['PK id', 'FK user_id', 'role_target', 'title', 'reference_type', 'reference_id', 'is_read'],
        'activity_logs' => ['PK id', 'FK user_id', 'activity_type', 'description', 'reference_type', 'reference_id'],
        'settings' => ['PK id', 'setting_key UQ', 'setting_value'],
        'reviews' => ['PK id', 'FK booking_id UQ', 'FK customer_id', 'rating', 'comment'],
    ];
}

function schemaRelations(): array
{
    return [
        ['service_categories', 'services', 'category_id'],
        ['users', 'bookings', 'customer_id / assigned_worker_id / created_by'],
        ['services', 'bookings', 'service_id'],
        ['bookings', 'booking_status_histories', 'booking_id'],
        ['users', 'booking_status_histories', 'changed_by'],
        ['bookings', 'schedules', 'booking_id'],
        ['users', 'schedules', 'worker_id / created_by'],
        ['bookings', 'surveys', 'booking_id'],
        ['users', 'surveys', 'worker_id'],
        ['surveys', 'survey_photos', 'survey_id'],
        ['bookings', 'survey_photos', 'booking_id'],
        ['bookings', 'estimates', 'booking_id'],
        ['surveys', 'estimates', 'survey_id'],
        ['estimates', 'estimate_items', 'estimate_id'],
        ['estimates', 'estimate_revisions', 'estimate_id'],
        ['bookings', 'estimate_revisions', 'booking_id'],
        ['estimate_revisions', 'estimate_revision_items', 'revision_id'],
        ['bookings', 'work_progress', 'booking_id'],
        ['users', 'work_progress', 'worker_id'],
        ['bookings', 'final_invoices', 'booking_id'],
        ['estimates', 'final_invoices', 'estimate_id'],
        ['final_invoices', 'final_invoice_items', 'final_invoice_id'],
        ['bookings', 'payments', 'booking_id'],
        ['final_invoices', 'payments', 'invoice_id'],
        ['users', 'notifications', 'user_id'],
        ['users', 'activity_logs', 'user_id'],
        ['bookings', 'reviews', 'booking_id'],
        ['users', 'reviews', 'customer_id'],
    ];
}

/**
 * Diagram rinci per proses aplikasi.
 *
 * Setiap spesifikasi dipakai dua kali:
 * - steps/flows untuk activity diagram;
 * - participants/messages untuk sequence diagram.
 */
function detailedProcessSpecs(): array
{
    return [
        [
            'slug' => '01-registrasi-customer',
            'title' => 'Registrasi Akun Customer',
            'lanes' => ['Calon Customer', 'Sistem', 'Database'],
            'steps' => [
                ['start', 0, 'start', ''],
                ['open', 0, 'action', 'Membuka halaman Daftar Akun'],
                ['form', 1, 'action', 'Menampilkan form nama, email, nomor HP, alamat, password, dan konfirmasi'],
                ['input', 0, 'action', 'Mengisi seluruh data lalu klik Buat Akun'],
                ['csrf', 1, 'decision', 'CSRF dan field wajib valid?'],
                ['format', 1, 'decision', 'Email valid, password ≥ 6 karakter, dan konfirmasi cocok?'],
                ['duplicate', 2, 'decision', 'Email sudah terdaftar?'],
                ['hash', 1, 'action', 'Hash password dengan bcrypt dan tetapkan role customer aktif'],
                ['insert', 2, 'action', 'INSERT users dan menghasilkan id user'],
                ['session', 1, 'action', 'Regenerasi session, simpan abi_user, dan catat activity log'],
                ['dashboard', 0, 'action', 'Dialihkan ke dashboard Customer'],
                ['error', 1, 'error', 'Tampilkan pesan validasi; pertahankan input yang aman'],
                ['end', 0, 'end', ''],
            ],
            'flows' => [
                ['start', 'open'], ['open', 'form'], ['form', 'input'], ['input', 'csrf'],
                ['csrf', 'format', 'Ya'], ['csrf', 'error', 'Tidak'],
                ['format', 'duplicate', 'Ya'], ['format', 'error', 'Tidak'],
                ['duplicate', 'error', 'Ya'], ['duplicate', 'hash', 'Tidak'],
                ['hash', 'insert'], ['insert', 'session'], ['session', 'dashboard'], ['dashboard', 'end'],
                ['error', 'input', 'Perbaiki data'],
            ],
            'participants' => ['Calon Customer', 'Halaman Register', 'Backend PHP', 'Database'],
            'messages' => [
                [0, 1, 'Membuka mode register'],
                [1, 0, 'Menampilkan form + CSRF token', true],
                [0, 1, 'Submit data registrasi'],
                [1, 2, 'POST mode=register + data + CSRF'],
                [2, 2, 'Validasi field, email, panjang password, dan konfirmasi'],
                [2, 3, 'SELECT users WHERE email=?'],
                [3, 2, 'Status duplikasi email', true],
                [2, 2, '[valid] password_hash(BCRYPT)'],
                [2, 3, 'INSERT users role=customer status=active'],
                [3, 2, 'Return user id', true],
                [2, 2, 'session_regenerate_id + log_activity(Register)'],
                [2, 1, 'Redirect dashboard.php', true],
                [1, 0, 'Dashboard Customer / pesan validasi', true],
            ],
        ],
        [
            'slug' => '02-login',
            'title' => 'Login Pengguna',
            'lanes' => ['Pengguna', 'Sistem', 'Database'],
            'steps' => [
                ['start', 0, 'start', ''],
                ['open', 0, 'action', 'Membuka halaman Login'],
                ['form', 1, 'action', 'Menampilkan form email, password, dan CSRF token'],
                ['input', 0, 'action', 'Mengisi email dan password lalu klik Masuk'],
                ['csrf', 1, 'decision', 'CSRF dan field wajib valid?'],
                ['lookup', 2, 'action', 'Cari user berdasarkan email dan status active'],
                ['password', 1, 'decision', 'Password cocok?'],
                ['session', 1, 'action', 'Regenerasi session dan simpan data abi_user'],
                ['log', 2, 'action', 'Update last_login_at dan INSERT activity_logs'],
                ['redirect', 1, 'action', 'Redirect ke dashboard sesuai role'],
                ['dashboard', 0, 'action', 'Melihat dashboard Customer / Staff / Pekerja / Boss'],
                ['error', 1, 'error', 'Tampilkan email/password salah atau akun tidak aktif'],
                ['end', 0, 'end', ''],
            ],
            'flows' => [
                ['start', 'open'], ['open', 'form'], ['form', 'input'], ['input', 'csrf'],
                ['csrf', 'lookup', 'Ya'], ['csrf', 'error', 'Tidak'], ['lookup', 'password'],
                ['password', 'session', 'Ya'], ['password', 'error', 'Tidak'],
                ['session', 'log'], ['log', 'redirect'], ['redirect', 'dashboard'], ['dashboard', 'end'],
                ['error', 'input', 'Ulangi'],
            ],
            'participants' => ['Pengguna', 'Halaman Login', 'Backend PHP', 'Database'],
            'messages' => [
                [0, 1, 'GET login.php'],
                [1, 0, 'Form login + CSRF token', true],
                [0, 1, 'Submit email dan password'],
                [1, 2, 'POST kredensial + CSRF'],
                [2, 2, 'verify_csrf dan cek field wajib'],
                [2, 3, 'SELECT user aktif berdasarkan email'],
                [3, 2, 'Return data user + password hash', true],
                [2, 2, 'password_verify'],
                [2, 2, '[valid] session_regenerate_id dan set abi_user'],
                [2, 3, 'UPDATE last_login_at + INSERT activity_logs'],
                [2, 1, 'Redirect dashboard.php', true],
                [1, 0, 'Dashboard sesuai role / pesan gagal', true],
            ],
        ],
        [
            'slug' => '03-profil-dan-ubah-password',
            'title' => 'Kelola Profil dan Ubah Password',
            'lanes' => ['Pengguna', 'Sistem', 'Database'],
            'steps' => [
                ['start', 0, 'start', ''],
                ['menu', 0, 'action', 'Membuka menu Profil'],
                ['load', 2, 'action', 'SELECT data user aktif'],
                ['choose', 0, 'decision', 'Pilih ubah profil atau password?'],
                ['profile', 0, 'action', 'Ubah nama, email, nomor HP, dan alamat'],
                ['validProfile', 1, 'decision', 'Nama/email valid dan email tidak duplikat?'],
                ['saveProfile', 2, 'action', 'UPDATE users dan refresh current session'],
                ['password', 0, 'action', 'Isi password lama, password baru, dan konfirmasi'],
                ['validNew', 1, 'decision', 'Password baru ≥ 6 dan konfirmasi cocok?'],
                ['verifyOld', 2, 'decision', 'Password lama cocok dengan hash database?'],
                ['savePassword', 2, 'action', 'UPDATE password hash dan destroy session'],
                ['success', 1, 'action', 'Catat activity log dan tampilkan notifikasi berhasil'],
                ['error', 1, 'error', 'Tampilkan pesan validasi'],
                ['end', 0, 'end', ''],
            ],
            'flows' => [
                ['start', 'menu'], ['menu', 'load'], ['load', 'choose'],
                ['choose', 'profile', 'Profil'], ['profile', 'validProfile'],
                ['validProfile', 'saveProfile', 'Valid'], ['validProfile', 'error', 'Tidak valid'],
                ['saveProfile', 'success'],
                ['choose', 'password', 'Password'], ['password', 'validNew'],
                ['validNew', 'verifyOld', 'Valid'], ['validNew', 'error', 'Tidak valid'],
                ['verifyOld', 'savePassword', 'Cocok'], ['verifyOld', 'error', 'Tidak cocok'],
                ['savePassword', 'success'], ['success', 'end'], ['error', 'menu', 'Perbaiki'],
            ],
            'participants' => ['Pengguna', 'Halaman Profil', 'Backend API', 'Database'],
            'messages' => [
                [0, 1, 'Buka menu Profil'],
                [1, 3, 'SELECT user by session id'],
                [3, 1, 'Data profil', true],
                [0, 1, 'Submit update_profile / change_password'],
                [1, 2, 'POST action + CSRF'],
                [2, 2, 'Validasi nama, email, atau password baru'],
                [2, 3, '[profil] cek email duplikat'],
                [3, 2, 'Hasil pengecekan', true],
                [2, 3, '[profil valid] UPDATE users'],
                [2, 3, '[password] SELECT password hash'],
                [3, 2, 'Password hash', true],
                [2, 2, 'password_verify dan password_hash'],
                [2, 3, '[password valid] UPDATE users.password'],
                [2, 2, 'Refresh user / destroy session dan log aktivitas'],
                [2, 1, 'JSON sukses atau error', true],
                [1, 0, 'Notifikasi; login ulang bila password berubah', true],
            ],
        ],
        [
            'slug' => '04-pembuatan-booking',
            'title' => 'Pembuatan Booking Layanan',
            'lanes' => ['Customer', 'Sistem', 'Database', 'Staff'],
            'steps' => [
                ['start', 0, 'start', ''],
                ['catalog', 0, 'action', 'Memilih layanan katalog atau kebutuhan custom'],
                ['form', 0, 'action', 'Mengisi tanggal survei, alamat, luas, catatan, persetujuan, dan foto referensi'],
                ['validate', 1, 'decision', 'Role customer, CSRF, field wajib, dan layanan valid?'],
                ['custom', 1, 'decision', 'Jika custom, deskripsi kebutuhan terisi?'],
                ['code', 1, 'action', 'Generate booking_code BK-xxxx'],
                ['insert', 2, 'action', 'INSERT bookings status Menunggu Konfirmasi'],
                ['upload', 1, 'action', 'Validasi dan simpan foto referensi bila ada'],
                ['history', 2, 'action', 'INSERT booking_status_histories dan activity_logs'],
                ['notify', 1, 'action', 'Kirim notifikasi Booking baru masuk kepada Staff'],
                ['queue', 3, 'action', 'Booking masuk antrean konfirmasi Staff'],
                ['success', 0, 'action', 'Melihat detail booking dan status Menunggu Konfirmasi'],
                ['error', 1, 'error', 'Tolak request dan tampilkan pesan validasi'],
                ['end', 0, 'end', ''],
            ],
            'flows' => [
                ['start', 'catalog'], ['catalog', 'form'], ['form', 'validate'],
                ['validate', 'custom', 'Valid'], ['validate', 'error', 'Tidak'],
                ['custom', 'code', 'Valid / non-custom'], ['custom', 'error', 'Deskripsi kosong'],
                ['code', 'insert'], ['insert', 'upload'], ['upload', 'history'], ['history', 'notify'],
                ['notify', 'queue'], ['queue', 'success'], ['success', 'end'], ['error', 'form', 'Perbaiki'],
            ],
            'participants' => ['Customer', 'Form Booking', 'Backend API', 'Database', 'Staff'],
            'messages' => [
                [0, 1, 'Pilih layanan / custom dan isi booking'],
                [1, 2, 'POST create_booking + CSRF + foto'],
                [2, 2, 'require role customer dan validasi field'],
                [2, 3, '[layanan biasa] SELECT services aktif'],
                [3, 2, 'Status layanan', true],
                [2, 2, 'Generate booking_code'],
                [2, 3, 'INSERT bookings status Menunggu Konfirmasi'],
                [3, 2, 'Return booking id', true],
                [2, 2, 'Simpan reference_photo bila valid'],
                [2, 3, 'UPDATE reference_photo + INSERT history/log'],
                [2, 3, 'INSERT notification role_target=staff'],
                [2, 4, 'Notifikasi booking baru'],
                [2, 1, 'JSON success + redirect detail', true],
                [1, 0, 'Detail booking / pesan validasi', true],
            ],
        ],
        [
            'slug' => '05-konfirmasi-dan-penjadwalan-survei',
            'title' => 'Konfirmasi Booking dan Penjadwalan Survei',
            'lanes' => ['Staff', 'Sistem', 'Database', 'Customer / Pekerja'],
            'steps' => [
                ['start', 0, 'start', ''],
                ['queue', 0, 'action', 'Membuka booking status Menunggu Konfirmasi'],
                ['choose', 0, 'decision', 'Terima atau tolak booking?'],
                ['reject', 0, 'action', 'Isi alasan penolakan'],
                ['cancel', 2, 'action', 'Update status Dibatalkan dan catat history/log'],
                ['schedule', 0, 'action', 'Pilih pekerja aktif, tanggal, jam, dan catatan survei'],
                ['validate', 1, 'decision', 'Data lengkap, status diizinkan, dan pekerja aktif?'],
                ['conflict', 2, 'decision', 'Ada konflik jadwal pekerja?'],
                ['existing', 2, 'decision', 'Sudah ada jadwal survei?'],
                ['upsert', 2, 'action', 'INSERT jadwal baru atau UPDATE sebagai Dijadwalkan Ulang'],
                ['assign', 2, 'action', 'Update assigned_worker, tanggal/jam aktual, dan status Survey Dijadwalkan'],
                ['notify', 1, 'action', 'Buat notifikasi untuk Customer dan Pekerja Lapangan; catat log'],
                ['receive', 3, 'action', 'Customer menerima jadwal dan pekerja menerima tugas'],
                ['error', 1, 'error', 'Tampilkan data tidak valid atau konflik jadwal'],
                ['end', 0, 'end', ''],
            ],
            'flows' => [
                ['start', 'queue'], ['queue', 'choose'], ['choose', 'reject', 'Tolak'],
                ['reject', 'cancel'], ['cancel', 'end'],
                ['choose', 'schedule', 'Terima'], ['schedule', 'validate'],
                ['validate', 'conflict', 'Valid'], ['validate', 'error', 'Tidak valid'],
                ['conflict', 'error', 'Ada'], ['conflict', 'existing', 'Tidak ada'],
                ['existing', 'upsert', 'Ya / Tidak'], ['upsert', 'assign'], ['assign', 'notify'],
                ['notify', 'receive'], ['receive', 'end'], ['error', 'schedule', 'Atur ulang'],
            ],
            'participants' => ['Staff', 'Halaman Booking', 'Backend API', 'Database', 'Customer', 'Pekerja Lapangan'],
            'messages' => [
                [0, 1, 'Buka booking baru'],
                [1, 3, 'SELECT booking + pekerja aktif + jadwal'],
                [3, 1, 'Data booking dan opsi pekerja', true],
                [0, 1, 'Submit schedule_survey / reject_booking'],
                [1, 2, 'POST action + data + CSRF'],
                [2, 3, 'Validasi status booking dan pekerja aktif'],
                [2, 3, 'SELECT konflik jadwal waktu yang sama'],
                [3, 2, 'Status konflik', true],
                [2, 3, 'SELECT jadwal survei existing'],
                [2, 3, 'INSERT schedule / UPDATE reschedule'],
                [2, 3, 'UPDATE bookings assignment + status + history'],
                [2, 3, 'INSERT notifications dan activity_logs'],
                [2, 4, 'Notifikasi jadwal survei'],
                [2, 5, 'Notifikasi tugas survei'],
                [2, 1, 'JSON success / konflik', true],
                [1, 0, 'Status terbaru atau pesan koreksi', true],
            ],
        ],
        [
            'slug' => '06-pelaksanaan-survei',
            'title' => 'Pelaksanaan dan Penyelesaian Survei',
            'lanes' => ['Pekerja Lapangan', 'Sistem', 'Database'],
            'steps' => [
                ['start', 0, 'start', ''],
                ['task', 0, 'action', 'Membuka tugas status Survey Dijadwalkan'],
                ['access', 1, 'decision', 'Booking ditugaskan ke pekerja dan status valid?'],
                ['startSurvey', 0, 'action', 'Klik Mulai Survei'],
                ['running', 2, 'action', 'Update booking Survey Berlangsung dan schedule Berlangsung'],
                ['field', 0, 'action', 'Mengukur luas, mencatat kondisi, akses, kendala, hari, pekerja, dan catatan'],
                ['photos', 0, 'action', 'Upload 1–5 foto before sesuai batas total'],
                ['validate', 1, 'decision', 'Luas > 0, kondisi/akses terisi, dan foto valid?'],
                ['insert', 2, 'action', 'INSERT surveys dan survey_photos'],
                ['complete', 2, 'action', 'Update actual_area, schedule Selesai, booking Survey Selesai, history/log'],
                ['success', 0, 'action', 'Menerima notifikasi survei berhasil disimpan'],
                ['error', 1, 'error', 'Tampilkan status/field/foto tidak valid'],
                ['end', 0, 'end', ''],
            ],
            'flows' => [
                ['start', 'task'], ['task', 'access'], ['access', 'startSurvey', 'Ya'],
                ['access', 'error', 'Tidak'], ['startSurvey', 'running'], ['running', 'field'],
                ['field', 'photos'], ['photos', 'validate'], ['validate', 'insert', 'Valid'],
                ['validate', 'error', 'Tidak'], ['insert', 'complete'], ['complete', 'success'],
                ['success', 'end'], ['error', 'task', 'Periksa ulang'],
            ],
            'participants' => ['Pekerja Lapangan', 'Halaman Survei', 'Backend API', 'Database', 'Penyimpanan File'],
            'messages' => [
                [0, 1, 'Buka tugas survei'],
                [1, 3, 'SELECT booking dan schedule sesuai worker'],
                [3, 1, 'Data tugas', true],
                [0, 1, 'Klik Mulai Survei'],
                [1, 2, 'POST start_survey'],
                [2, 3, 'UPDATE booking dan schedule menjadi berlangsung'],
                [0, 1, 'Submit hasil survei + foto before'],
                [1, 2, 'POST complete_survey multipart'],
                [2, 2, 'Validasi akses, status, field, jumlah dan tipe foto'],
                [2, 3, 'INSERT surveys'],
                [3, 2, 'Return survey id', true],
                [2, 4, 'Simpan file foto booking/before'],
                [2, 3, 'INSERT survey_photos'],
                [2, 3, 'UPDATE actual_area, schedule, status, history, log'],
                [2, 1, 'JSON success / validation error', true],
                [1, 0, 'Tampilkan hasil', true],
            ],
        ],
        [
            'slug' => '07-penyusunan-estimasi',
            'title' => 'Penyusunan Draft Estimasi',
            'lanes' => ['Pekerja Lapangan', 'Sistem Estimator', 'Database', 'Staff'],
            'steps' => [
                ['start', 0, 'start', ''],
                ['open', 0, 'action', 'Membuka booking status Survey Selesai'],
                ['survey', 2, 'action', 'Ambil hasil survei, material, tarif tenaga kerja, dan biaya tambahan aktif'],
                ['input', 0, 'action', 'Pilih material dan kuantitas, tarif tenaga kerja, durasi, jumlah pekerja, serta biaya tambahan'],
                ['validate', 1, 'decision', 'Minimal satu material dan semua master data aktif/valid?'],
                ['block', 1, 'action', 'Hitung block_count dari luas aktual'],
                ['material', 1, 'action', 'Hitung subtotal material = quantity × unit_price'],
                ['labor', 1, 'action', 'Hitung tenaga kerja = pekerja × hari × daily_rate'],
                ['additional', 1, 'action', 'Hitung biaya tambahan master dan custom'],
                ['total', 1, 'action', 'Hitung grand_total = material + labor + additional'],
                ['estimate', 2, 'action', 'INSERT estimates status Draft dan seluruh estimate_items'],
                ['status', 2, 'action', 'Update booking Estimasi Dibuat; insert history/log/notifikasi Staff'],
                ['review', 3, 'action', 'Draft masuk antrean review Staff'],
                ['error', 1, 'error', 'Tolak material/tarif/quantity/harga tidak valid'],
                ['end', 0, 'end', ''],
            ],
            'flows' => [
                ['start', 'open'], ['open', 'survey'], ['survey', 'input'], ['input', 'validate'],
                ['validate', 'block', 'Valid'], ['validate', 'error', 'Tidak'],
                ['block', 'material'], ['material', 'labor'], ['labor', 'additional'],
                ['additional', 'total'], ['total', 'estimate'], ['estimate', 'status'],
                ['status', 'review'], ['review', 'end'], ['error', 'input', 'Perbaiki item'],
            ],
            'participants' => ['Pekerja Lapangan', 'Form Estimasi', 'Estimator/Backend', 'Database', 'Staff'],
            'messages' => [
                [0, 1, 'Buka form estimasi'],
                [1, 3, 'SELECT survey + master biaya aktif'],
                [3, 1, 'Data luas, material, tarif, biaya', true],
                [0, 1, 'Submit pilihan item dan kuantitas'],
                [1, 2, 'POST create_estimate'],
                [2, 3, 'Validasi status Survey Selesai dan master id'],
                [3, 2, 'Master data valid', true],
                [2, 2, 'calculate_block_count'],
                [2, 2, 'Hitung material_total'],
                [2, 2, 'calculate_labor_total'],
                [2, 2, 'calculate_additional_total dan grand_total'],
                [2, 3, 'INSERT estimates status Draft'],
                [2, 3, 'INSERT estimate_items material/labor/additional'],
                [2, 3, 'UPDATE booking + history + log + notification'],
                [2, 4, 'Notifikasi estimasi perlu direview'],
                [2, 1, 'JSON success + redirect estimate', true],
                [1, 0, 'Draft estimasi tersimpan', true],
            ],
        ],
        [
            'slug' => '08-review-dan-pengiriman-estimasi',
            'title' => 'Review dan Pengiriman Estimasi',
            'lanes' => ['Staff', 'Sistem', 'Database', 'Customer'],
            'steps' => [
                ['start', 0, 'start', ''],
                ['queue', 0, 'action', 'Membuka antrean estimasi status Draft'],
                ['detail', 2, 'action', 'Ambil booking, survey, rincian item, dan grand total'],
                ['review', 0, 'action', 'Memeriksa kecocokan item, kuantitas, harga, durasi, dan total'],
                ['decision', 0, 'decision', 'Estimasi layak dikirim?'],
                ['return', 0, 'action', 'Kembalikan untuk koreksi Pekerja Lapangan'],
                ['send', 0, 'action', 'Klik Kirim Estimasi ke Customer'],
                ['validate', 1, 'decision', 'Status estimasi masih Draft?'],
                ['update', 2, 'action', 'Update estimate Dikirim + sent_at dan booking Menunggu Persetujuan Customer'],
                ['notify', 2, 'action', 'Insert history, activity log, dan notification Customer'],
                ['receive', 3, 'action', 'Menerima notifikasi serta melihat rincian estimasi'],
                ['error', 1, 'error', 'Tampilkan estimasi sudah diproses / status tidak valid'],
                ['end', 0, 'end', ''],
            ],
            'flows' => [
                ['start', 'queue'], ['queue', 'detail'], ['detail', 'review'], ['review', 'decision'],
                ['decision', 'return', 'Belum'], ['return', 'queue', 'Setelah koreksi'],
                ['decision', 'send', 'Ya'], ['send', 'validate'],
                ['validate', 'update', 'Valid'], ['validate', 'error', 'Tidak'],
                ['update', 'notify'], ['notify', 'receive'], ['receive', 'end'], ['error', 'queue'],
            ],
            'participants' => ['Staff', 'Halaman Estimasi', 'Backend API', 'Database', 'Customer'],
            'messages' => [
                [0, 1, 'Buka estimasi Draft'],
                [1, 3, 'SELECT estimate + items + booking + survey'],
                [3, 1, 'Rincian estimasi lengkap', true],
                [0, 1, 'Review item dan total'],
                [0, 1, 'Klik Kirim Estimasi'],
                [1, 2, 'POST send_estimate + estimate_id'],
                [2, 3, 'SELECT estimate dan validasi status Draft'],
                [3, 2, 'Data estimate/customer/booking', true],
                [2, 3, 'UPDATE estimates status Dikirim, sent_at=NOW'],
                [2, 3, 'UPDATE booking + INSERT history/log'],
                [2, 3, 'INSERT notification Customer'],
                [2, 4, 'Estimasi menunggu keputusan'],
                [2, 1, 'JSON success / status error', true],
                [1, 0, 'Tampilkan status terkirim', true],
            ],
        ],
        [
            'slug' => '09-keputusan-estimasi',
            'title' => 'Persetujuan atau Penolakan Estimasi',
            'lanes' => ['Customer', 'Sistem', 'Database', 'Staff / Pekerja'],
            'steps' => [
                ['start', 0, 'start', ''],
                ['open', 0, 'action', 'Membuka estimasi status Dikirim'],
                ['detail', 2, 'action', 'Ambil item, total, booking, pekerja, dan survey fee'],
                ['decision', 0, 'decision', 'Setujui atau tolak estimasi?'],
                ['approve', 2, 'action', 'Update estimate Disetujui + approved_at dan booking Estimasi Disetujui'],
                ['notifyWorker', 1, 'action', 'Notifikasi Pekerja: booking siap masuk pengerjaan'],
                ['reject', 2, 'action', 'Update estimate Ditolak + rejected_at; set survey fee Belum Dibayar'],
                ['invoice', 1, 'action', 'Buat invoice Survey Fee bila belum ada'],
                ['payment', 2, 'action', 'Update booking Menunggu Pembayaran dan catat history/log'],
                ['notifyReject', 1, 'action', 'Notifikasi Staff dan Customer tentang kewajiban Survey Fee'],
                ['result', 3, 'action', 'Pekerja menerima persetujuan atau Staff menerima penolakan'],
                ['error', 1, 'error', 'Tolak keputusan jika bukan pemilik atau status bukan Dikirim'],
                ['end', 0, 'end', ''],
            ],
            'flows' => [
                ['start', 'open'], ['open', 'detail'], ['detail', 'decision'],
                ['decision', 'approve', 'Setujui'], ['approve', 'notifyWorker'], ['notifyWorker', 'result'],
                ['decision', 'reject', 'Tolak'], ['reject', 'invoice'], ['invoice', 'payment'],
                ['payment', 'notifyReject'], ['notifyReject', 'result'], ['result', 'end'],
                ['error', 'open'],
            ],
            'participants' => ['Customer', 'Halaman Estimasi', 'Backend API', 'Database', 'Staff', 'Pekerja Lapangan'],
            'messages' => [
                [0, 1, 'Buka estimasi terkirim'],
                [1, 3, 'SELECT estimate + items milik customer'],
                [3, 1, 'Rincian dan survey_fee_amount', true],
                [0, 1, 'Setujui / Tolak + catatan'],
                [1, 2, 'POST approve_estimate / reject_estimate'],
                [2, 3, 'Validasi owner dan status Dikirim'],
                [2, 3, '[setuju] UPDATE estimate approved + booking status'],
                [2, 5, '[setuju] Notifikasi siap pengerjaan'],
                [2, 3, '[tolak] UPDATE estimate rejected + survey fee pending'],
                [2, 3, '[tolak] SELECT/INSERT invoice Survey Fee + item'],
                [2, 3, '[tolak] UPDATE booking Menunggu Pembayaran'],
                [2, 4, '[tolak] Notifikasi Staff'],
                [2, 0, '[tolak] Notifikasi invoice Survey Fee'],
                [2, 1, 'JSON hasil keputusan + redirect', true],
                [1, 0, 'Status persetujuan / invoice fee', true],
            ],
        ],
        [
            'slug' => '10-pengerjaan-dan-update-progres',
            'title' => 'Mulai Pengerjaan dan Update Progres',
            'lanes' => ['Staff / Pekerja', 'Pekerja Lapangan', 'Sistem', 'Database', 'Customer'],
            'steps' => [
                ['start', 0, 'start', ''],
                ['open', 0, 'action', 'Membuka booking status Estimasi Disetujui'],
                ['startWork', 0, 'action', 'Klik Mulai Pekerjaan'],
                ['access', 2, 'decision', 'Role dan akses booking valid?'],
                ['running', 3, 'action', 'Update booking Dalam Pengerjaan + history/log'],
                ['work', 1, 'action', 'Melaksanakan pekerjaan di lokasi'],
                ['input', 1, 'action', 'Isi persen 0–100, judul, catatan, kendala, dan foto progress'],
                ['validate', 2, 'decision', 'Status berjalan, persen valid, judul terisi, dan foto sesuai batas?'],
                ['insert', 3, 'action', 'INSERT work_progress dan survey_photos type progress'],
                ['history', 3, 'action', 'Insert activity log dan pertahankan status berjalan/revisi'],
                ['display', 4, 'action', 'Customer melihat timeline, persentase, catatan, dan foto terbaru'],
                ['repeat', 1, 'decision', 'Pekerjaan sudah 100%?'],
                ['error', 2, 'error', 'Tampilkan validasi akses/status/input/foto'],
                ['end', 1, 'end', ''],
            ],
            'flows' => [
                ['start', 'open'], ['open', 'startWork'], ['startWork', 'access'],
                ['access', 'running', 'Valid'], ['access', 'error', 'Tidak'],
                ['running', 'work'], ['work', 'input'], ['input', 'validate'],
                ['validate', 'insert', 'Valid'], ['validate', 'error', 'Tidak'],
                ['insert', 'history'], ['history', 'display'], ['display', 'repeat'],
                ['repeat', 'work', 'Belum'], ['repeat', 'end', 'Ya'], ['error', 'input'],
            ],
            'participants' => ['Staff/Pekerja', 'Halaman Pekerjaan', 'Backend API', 'Database', 'File Storage', 'Customer'],
            'messages' => [
                [0, 1, 'Klik Mulai Pekerjaan'],
                [1, 2, 'POST start_work'],
                [2, 3, 'Validasi akses dan status Estimasi Disetujui'],
                [2, 3, 'UPDATE booking Dalam Pengerjaan + history/log'],
                [2, 1, 'JSON pekerjaan dimulai', true],
                [0, 1, 'Submit progress + foto'],
                [1, 2, 'POST update_progress multipart'],
                [2, 2, 'Validasi status, persen, judul, foto'],
                [2, 3, 'INSERT work_progress'],
                [2, 4, 'Simpan file progress'],
                [2, 3, 'INSERT survey_photos type=progress'],
                [2, 3, 'INSERT activity_logs'],
                [5, 1, 'Membuka detail order'],
                [1, 3, 'SELECT progress dan photos terbaru'],
                [3, 1, 'Timeline progres', true],
                [1, 5, 'Tampilkan persentase/foto/catatan', true],
            ],
        ],
        [
            'slug' => '11-revisi-estimasi',
            'title' => 'Pembuatan dan Keputusan Revisi Estimasi',
            'lanes' => ['Pekerja Lapangan', 'Staff', 'Customer', 'Sistem / Database'],
            'steps' => [
                ['start', 0, 'start', ''],
                ['need', 0, 'action', 'Menemukan perubahan kebutuhan/biaya saat pekerjaan berjalan'],
                ['form', 0, 'action', 'Isi alasan dan item tambahan: nama, quantity, unit, harga'],
                ['validate', 3, 'decision', 'Status Dalam Pengerjaan/Revisi dan item valid?'],
                ['calculate', 3, 'action', 'Hitung additional_total dan new_total dari estimasi lama'],
                ['draft', 3, 'action', 'INSERT estimate_revisions Draft dan revision_items'],
                ['status', 3, 'action', 'Update booking Revisi Estimasi; log dan notifikasi Staff'],
                ['review', 1, 'action', 'Review draft revisi lalu klik Kirim'],
                ['send', 3, 'action', 'Update revisi Dikirim dan notifikasi Customer'],
                ['decision', 2, 'decision', 'Setujui atau tolak revisi?'],
                ['update', 3, 'action', 'Update revisi Disetujui/Ditolak dan booking kembali Dalam Pengerjaan'],
                ['notify', 3, 'action', 'Notifikasi Pekerja dan catat activity log'],
                ['continue', 0, 'action', 'Melanjutkan pekerjaan dengan keputusan revisi'],
                ['error', 3, 'error', 'Tampilkan status/item tidak valid'],
                ['end', 0, 'end', ''],
            ],
            'flows' => [
                ['start', 'need'], ['need', 'form'], ['form', 'validate'],
                ['validate', 'calculate', 'Valid'], ['validate', 'error', 'Tidak'],
                ['calculate', 'draft'], ['draft', 'status'], ['status', 'review'],
                ['review', 'send'], ['send', 'decision'],
                ['decision', 'update', 'Setujui/Tolak'], ['update', 'notify'],
                ['notify', 'continue'], ['continue', 'end'], ['error', 'form'],
            ],
            'participants' => ['Pekerja Lapangan', 'Halaman Revisi', 'Backend API', 'Database', 'Staff', 'Customer'],
            'messages' => [
                [0, 1, 'Isi alasan dan item revisi'],
                [1, 2, 'POST create_revision'],
                [2, 3, 'Validasi booking berjalan dan estimate aktif'],
                [2, 2, 'Hitung subtotal, additional_total, new_total'],
                [2, 3, 'INSERT estimate_revisions Draft'],
                [2, 3, 'INSERT estimate_revision_items'],
                [2, 3, 'UPDATE booking Revisi Estimasi + history/log'],
                [2, 4, 'Notifikasi revisi perlu review'],
                [4, 1, 'Klik Kirim Revisi'],
                [1, 2, 'POST send_revision'],
                [2, 3, 'UPDATE revision Dikirim'],
                [2, 5, 'Notifikasi keputusan revisi'],
                [5, 1, 'Setujui / tolak revisi'],
                [1, 2, 'POST approve_revision / reject_revision'],
                [2, 3, 'UPDATE revision dan booking Dalam Pengerjaan'],
                [2, 0, 'Notifikasi hasil keputusan'],
                [2, 1, 'JSON success', true],
            ],
        ],
        [
            'slug' => '12-penyelesaian-pekerjaan-dan-invoice-draft',
            'title' => 'Penyelesaian Pekerjaan dan Pembuatan Invoice Draft',
            'lanes' => ['Pekerja Lapangan', 'Sistem', 'Database', 'Staff'],
            'steps' => [
                ['start', 0, 'start', ''],
                ['done', 0, 'action', 'Klik Tandai Pekerjaan Selesai'],
                ['status', 1, 'decision', 'Booking Dalam Pengerjaan/Revisi dan ditugaskan ke pekerja?'],
                ['mark', 2, 'action', 'Update booking Pekerjaan Selesai + history/log'],
                ['final', 0, 'action', 'Isi hari/pekerja aktual, catatan, dan upload 1–5 foto after'],
                ['validate', 1, 'decision', 'Belum ada invoice, estimasi disetujui tersedia, dan foto valid?'],
                ['sum', 1, 'action', 'Jumlahkan estimate_items + revisi yang disetujui'],
                ['invoice', 2, 'action', 'INSERT final_invoices status Draft'],
                ['copy', 2, 'action', 'Salin estimate_items dan revision_items ke final_invoice_items'],
                ['booking', 2, 'action', 'Update booking Invoice Final Dibuat + history/log'],
                ['notify', 1, 'action', 'Notifikasi Staff bahwa invoice menunggu review'],
                ['queue', 3, 'action', 'Invoice masuk antrean review Staff'],
                ['error', 1, 'error', 'Tampilkan status, estimasi, duplikasi invoice, atau foto tidak valid'],
                ['end', 0, 'end', ''],
            ],
            'flows' => [
                ['start', 'done'], ['done', 'status'], ['status', 'mark', 'Valid'],
                ['status', 'error', 'Tidak'], ['mark', 'final'], ['final', 'validate'],
                ['validate', 'sum', 'Valid'], ['validate', 'error', 'Tidak'],
                ['sum', 'invoice'], ['invoice', 'copy'], ['copy', 'booking'],
                ['booking', 'notify'], ['notify', 'queue'], ['queue', 'end'], ['error', 'final'],
            ],
            'participants' => ['Pekerja Lapangan', 'Halaman Pekerjaan', 'Backend API', 'Database', 'File Storage', 'Staff'],
            'messages' => [
                [0, 1, 'Klik pekerjaan selesai'],
                [1, 2, 'POST mark_work_done'],
                [2, 3, 'Validasi akses dan status'],
                [2, 3, 'UPDATE booking Pekerjaan Selesai + history/log'],
                [0, 1, 'Submit data final + foto after'],
                [1, 2, 'POST create_invoice multipart'],
                [2, 3, 'Cek invoice existing dan estimate disetujui'],
                [2, 4, 'Simpan 1–5 foto after'],
                [2, 3, 'SUM estimate_items + approved revisions'],
                [2, 3, 'INSERT final_invoices Draft'],
                [2, 3, 'SELECT dan salin base items ke final_invoice_items'],
                [2, 3, 'SELECT dan salin revision items'],
                [2, 3, 'UPDATE booking Invoice Final Dibuat + log'],
                [2, 5, 'Notifikasi invoice final menunggu review'],
                [2, 1, 'JSON success', true],
                [1, 0, 'Data final berhasil dikirim', true],
            ],
        ],
        [
            'slug' => '13-review-dan-pengiriman-invoice',
            'title' => 'Review, Edit, dan Pengiriman Invoice Final',
            'lanes' => ['Staff', 'Sistem', 'Database', 'Customer'],
            'steps' => [
                ['start', 0, 'start', ''],
                ['queue', 0, 'action', 'Membuka invoice status Draft'],
                ['detail', 2, 'action', 'Ambil invoice, item, estimasi, revisi, dan booking'],
                ['edit', 0, 'action', 'Koreksi hari/pekerja aktual, catatan, quantity, dan unit price'],
                ['save', 1, 'action', 'Hitung ulang subtotal estimate, revision_total, dan final_total'],
                ['update', 2, 'action', 'UPDATE final_invoice_items dan final_invoices; catat log'],
                ['review', 0, 'decision', 'Invoice sudah benar?'],
                ['send', 0, 'action', 'Klik Kirim Invoice'],
                ['validate', 1, 'decision', 'Status invoice masih Draft?'],
                ['sent', 2, 'action', 'Update invoice Dikirim dan booking Menunggu Pembayaran'],
                ['notify', 2, 'action', 'Insert history/log/notifikasi Customer'],
                ['receive', 3, 'action', 'Melihat invoice dan nominal pembayaran'],
                ['error', 1, 'error', 'Tampilkan status tidak valid atau data item salah'],
                ['end', 0, 'end', ''],
            ],
            'flows' => [
                ['start', 'queue'], ['queue', 'detail'], ['detail', 'edit'], ['edit', 'save'],
                ['save', 'update'], ['update', 'review'], ['review', 'edit', 'Belum'],
                ['review', 'send', 'Ya'], ['send', 'validate'],
                ['validate', 'sent', 'Valid'], ['validate', 'error', 'Tidak'],
                ['sent', 'notify'], ['notify', 'receive'], ['receive', 'end'], ['error', 'queue'],
            ],
            'participants' => ['Staff', 'Halaman Invoice', 'Backend API', 'Database', 'Customer'],
            'messages' => [
                [0, 1, 'Buka invoice Draft'],
                [1, 3, 'SELECT invoice + items + booking'],
                [3, 1, 'Rincian invoice', true],
                [0, 1, 'Edit item/hari/pekerja/catatan'],
                [1, 2, 'POST save_invoice'],
                [2, 3, 'UPDATE item quantity, unit_price, subtotal'],
                [2, 3, 'SELECT seluruh item dan hitung ulang total'],
                [2, 3, 'UPDATE final_invoices + log'],
                [2, 1, 'JSON invoice disimpan', true],
                [0, 1, 'Klik Kirim Invoice'],
                [1, 2, 'POST send_invoice'],
                [2, 3, 'Validasi Draft; UPDATE invoice Dikirim'],
                [2, 3, 'UPDATE booking Menunggu Pembayaran + history/log'],
                [2, 3, 'INSERT notification Customer'],
                [2, 4, 'Invoice siap dibayar'],
                [2, 1, 'JSON success', true],
            ],
        ],
        [
            'slug' => '14-pembayaran-dan-verifikasi',
            'title' => 'Upload Bukti dan Verifikasi Pembayaran',
            'lanes' => ['Customer', 'Sistem', 'Database', 'Staff'],
            'steps' => [
                ['start', 0, 'start', ''],
                ['invoice', 0, 'action', 'Membuka invoice final atau invoice Survey Fee belum lunas'],
                ['upload', 0, 'action', 'Upload bukti pembayaran dan catatan'],
                ['file', 1, 'decision', 'File valid dan invoice/booking milik Customer?'],
                ['upsert', 2, 'action', 'INSERT/UPDATE payments status Belum Dibayar + payment_proof'],
                ['notify', 1, 'action', 'Notifikasi Staff dan catat activity log'],
                ['review', 3, 'action', 'Membuka bukti pembayaran tertunda'],
                ['decision', 3, 'decision', 'Bukti pembayaran valid?'],
                ['reject', 3, 'action', 'Minta Customer mengunggah bukti yang benar'],
                ['paid', 2, 'action', 'Update invoice Lunas, payment Lunas, marked_by, paid_at'],
                ['fee', 2, 'action', 'Jika Survey Fee: update survey_fee_payment_status Sudah Dibayar'],
                ['status', 2, 'action', 'Update booking Lunas + history/log'],
                ['result', 0, 'action', 'Customer melihat status pembayaran Lunas'],
                ['error', 1, 'error', 'Tampilkan file/status/akses tidak valid'],
                ['end', 0, 'end', ''],
            ],
            'flows' => [
                ['start', 'invoice'], ['invoice', 'upload'], ['upload', 'file'],
                ['file', 'upsert', 'Valid'], ['file', 'error', 'Tidak'],
                ['upsert', 'notify'], ['notify', 'review'], ['review', 'decision'],
                ['decision', 'reject', 'Tidak valid'], ['reject', 'upload'],
                ['decision', 'paid', 'Valid'], ['paid', 'fee'], ['fee', 'status'],
                ['status', 'result'], ['result', 'end'], ['error', 'upload'],
            ],
            'participants' => ['Customer', 'Halaman Pembayaran', 'Backend API', 'Database', 'File Storage', 'Staff'],
            'messages' => [
                [0, 1, 'Pilih invoice dan upload bukti'],
                [1, 2, 'POST upload_payment_proof multipart'],
                [2, 4, 'Validasi dan simpan file bukti'],
                [2, 3, 'Validasi invoice/booking milik customer'],
                [2, 3, 'SELECT payment pending existing'],
                [2, 3, 'INSERT/UPDATE payments + payment_proof'],
                [2, 3, 'INSERT notification Staff + activity_log'],
                [2, 5, 'Notifikasi bukti bayar dikirim'],
                [5, 1, 'Buka detail pembayaran'],
                [1, 3, 'SELECT payment + invoice + booking'],
                [3, 1, 'Data dan bukti pembayaran', true],
                [5, 1, 'Klik Tandai Lunas'],
                [1, 2, 'POST mark_invoice_paid / mark_survey_fee_paid'],
                [2, 3, 'UPDATE invoice/payment/fee status Lunas'],
                [2, 3, 'UPDATE booking Lunas + history/log'],
                [2, 1, 'JSON verifikasi berhasil', true],
                [1, 0, 'Status pembayaran Lunas', true],
            ],
        ],
        [
            'slug' => '15-penyelesaian-order-dan-ulasan',
            'title' => 'Penyelesaian Order dan Ulasan Customer',
            'lanes' => ['Staff', 'Sistem', 'Database', 'Customer'],
            'steps' => [
                ['start', 0, 'start', ''],
                ['paid', 0, 'action', 'Membuka booking status Lunas'],
                ['complete', 0, 'action', 'Klik Selesaikan Order'],
                ['validate', 1, 'decision', 'Status booking benar-benar Lunas?'],
                ['status', 2, 'action', 'Update booking Selesai + history/log'],
                ['notify', 1, 'action', 'Kirim notifikasi Berikan Ulasan kepada Customer'],
                ['open', 3, 'action', 'Membuka detail order selesai'],
                ['form', 3, 'action', 'Memilih rating 1–5 dan menulis komentar'],
                ['reviewValid', 1, 'decision', 'Customer pemilik, status Selesai, dan rating valid?'],
                ['existing', 2, 'decision', 'Ulasan booking sudah ada?'],
                ['save', 2, 'action', 'INSERT ulasan baru atau UPDATE ulasan existing'],
                ['log', 2, 'action', 'Catat activity log rating Customer'],
                ['success', 3, 'action', 'Melihat notifikasi ulasan berhasil disimpan'],
                ['error', 1, 'error', 'Tampilkan status/rating/akses tidak valid'],
                ['end', 3, 'end', ''],
            ],
            'flows' => [
                ['start', 'paid'], ['paid', 'complete'], ['complete', 'validate'],
                ['validate', 'status', 'Valid'], ['validate', 'error', 'Tidak'],
                ['status', 'notify'], ['notify', 'open'], ['open', 'form'],
                ['form', 'reviewValid'], ['reviewValid', 'existing', 'Valid'],
                ['reviewValid', 'error', 'Tidak'], ['existing', 'save', 'Ya / Tidak'],
                ['save', 'log'], ['log', 'success'], ['success', 'end'], ['error', 'form'],
            ],
            'participants' => ['Staff', 'Halaman Booking', 'Backend API', 'Database', 'Customer'],
            'messages' => [
                [0, 1, 'Klik Selesaikan Order'],
                [1, 2, 'POST complete_order'],
                [2, 3, 'SELECT booking dan validasi status Lunas'],
                [2, 3, 'UPDATE booking Selesai + history/log'],
                [2, 3, 'INSERT notification Berikan Ulasan'],
                [2, 4, 'Notifikasi order selesai'],
                [4, 1, 'Buka detail dan submit rating/comment'],
                [1, 2, 'POST submit_review'],
                [2, 3, 'Validasi booking milik customer dan status Selesai'],
                [2, 3, 'SELECT review existing'],
                [3, 2, 'Status review', true],
                [2, 3, 'INSERT review / UPDATE rating dan comment'],
                [2, 3, 'INSERT activity_log'],
                [2, 1, 'JSON ulasan berhasil', true],
                [1, 4, 'Tampilkan ucapan terima kasih', true],
            ],
        ],
        [
            'slug' => '16-kelola-master-data',
            'title' => 'Kelola Master Data',
            'lanes' => ['Staff', 'Sistem', 'Database', 'Penyimpanan File'],
            'steps' => [
                ['start', 0, 'start', ''],
                ['menu', 0, 'action', 'Pilih kategori, layanan, material, tarif tenaga kerja, atau biaya tambahan'],
                ['list', 2, 'action', 'SELECT daftar master beserta status'],
                ['choose', 0, 'decision', 'Tambah, ubah, hapus, atau nonaktifkan?'],
                ['form', 0, 'action', 'Isi nama, kode otomatis, harga/tarif, unit, deskripsi, status, dan gambar'],
                ['validate', 1, 'decision', 'Role Staff, CSRF, field wajib, dan angka valid?'],
                ['upload', 3, 'action', 'Validasi serta simpan gambar layanan/material bila ada'],
                ['save', 2, 'action', 'INSERT atau UPDATE tabel master terkait'],
                ['delete', 1, 'action', 'Minta konfirmasi aksi hapus'],
                ['used', 2, 'decision', 'Record masih dipakai booking/layanan histori?'],
                ['inactive', 2, 'action', 'Set status inactive agar histori tetap aman'],
                ['remove', 2, 'action', 'DELETE record yang tidak memiliki relasi'],
                ['log', 2, 'action', 'INSERT activity_logs dan tampilkan hasil'],
                ['error', 1, 'error', 'Tampilkan target/field/file tidak valid'],
                ['end', 0, 'end', ''],
            ],
            'flows' => [
                ['start', 'menu'], ['menu', 'list'], ['list', 'choose'],
                ['choose', 'form', 'Tambah/Ubah'], ['form', 'validate'],
                ['validate', 'upload', 'Valid'], ['validate', 'error', 'Tidak'],
                ['upload', 'save'], ['save', 'log'],
                ['choose', 'delete', 'Hapus'], ['delete', 'used'],
                ['used', 'inactive', 'Masih dipakai'], ['used', 'remove', 'Tidak dipakai'],
                ['inactive', 'log'], ['remove', 'log'], ['log', 'end'], ['error', 'form'],
            ],
            'participants' => ['Staff', 'Halaman Pengaturan', 'Backend API', 'Database', 'File Storage'],
            'messages' => [
                [0, 1, 'Pilih jenis master data'],
                [1, 3, 'SELECT daftar kategori/layanan/material/tarif/biaya'],
                [3, 1, 'Data master', true],
                [0, 1, 'Submit tambah/ubah/hapus'],
                [1, 2, 'POST save_* / delete_master_record'],
                [2, 2, 'require Staff + CSRF + validasi field/target'],
                [2, 4, '[gambar] simpan file layanan/material'],
                [2, 3, '[tambah/ubah] INSERT/UPDATE tabel target'],
                [2, 3, '[hapus] hitung relasi histori'],
                [3, 2, 'Jumlah penggunaan', true],
                [2, 3, '[dipakai] UPDATE status inactive'],
                [2, 3, '[tidak dipakai] DELETE record'],
                [2, 3, 'INSERT activity_logs'],
                [2, 1, 'JSON success / validation error', true],
                [1, 0, 'Refresh daftar dan notifikasi', true],
            ],
        ],
        [
            'slug' => '17-kelola-user-dan-pengaturan',
            'title' => 'Kelola User dan Pengaturan Sistem',
            'lanes' => ['Staff', 'Sistem', 'Database'],
            'steps' => [
                ['start', 0, 'start', ''],
                ['menu', 0, 'decision', 'Pilih Kelola User atau Pengaturan Sistem?'],
                ['userForm', 0, 'action', 'Isi nama, email, role, kontak, status, dan password'],
                ['userValid', 1, 'decision', 'Email/role valid, tidak duplikat, dan password ≥ 6?'],
                ['self', 1, 'decision', 'Mengubah role/status akun Staff yang sedang dipakai?'],
                ['saveUser', 2, 'action', 'INSERT/UPDATE users; hash password bila diisi'],
                ['delete', 0, 'action', 'Konfirmasi hapus user'],
                ['linked', 2, 'decision', 'User punya histori operasional atau role Staff/Boss?'],
                ['disable', 2, 'action', 'Set status inactive'],
                ['remove', 2, 'action', 'DELETE user tanpa histori'],
                ['settings', 0, 'action', 'Isi identitas perusahaan dan nilai Survey Fee'],
                ['settingsValid', 1, 'decision', 'Email perusahaan valid dan Survey Fee ≥ 0?'],
                ['upsert', 2, 'action', 'UPSERT enam setting_key melalui setting_set'],
                ['log', 2, 'action', 'INSERT activity_logs dan tampilkan notifikasi'],
                ['error', 1, 'error', 'Tampilkan email/role/password/setting tidak valid'],
                ['end', 0, 'end', ''],
            ],
            'flows' => [
                ['start', 'menu'], ['menu', 'userForm', 'User'], ['userForm', 'userValid'],
                ['userValid', 'self', 'Valid'], ['userValid', 'error', 'Tidak'],
                ['self', 'saveUser', 'Tidak'], ['self', 'error', 'Ya'],
                ['saveUser', 'log'], ['menu', 'delete', 'Hapus user'], ['delete', 'linked'],
                ['linked', 'disable', 'Ya'], ['linked', 'remove', 'Tidak'],
                ['disable', 'log'], ['remove', 'log'],
                ['menu', 'settings', 'Pengaturan'], ['settings', 'settingsValid'],
                ['settingsValid', 'upsert', 'Valid'], ['settingsValid', 'error', 'Tidak'],
                ['upsert', 'log'], ['log', 'end'], ['error', 'menu'],
            ],
            'participants' => ['Staff', 'Halaman Pengaturan', 'Backend API', 'Database'],
            'messages' => [
                [0, 1, 'Buka User / Pengaturan'],
                [1, 3, 'SELECT users atau settings'],
                [3, 1, 'Data existing', true],
                [0, 1, 'Submit save_user/delete_user/save_settings'],
                [1, 2, 'POST action + CSRF'],
                [2, 2, 'Validasi email, role, password, dan self-protection'],
                [2, 3, 'SELECT duplicate email / histori operasional'],
                [3, 2, 'Hasil pengecekan', true],
                [2, 2, 'Hash password bila ada'],
                [2, 3, 'INSERT/UPDATE/DELETE/deactivate users'],
                [2, 2, '[settings] validasi email dan survey fee'],
                [2, 3, 'UPSERT setting_key/value'],
                [2, 3, 'INSERT activity_logs'],
                [2, 1, 'JSON success / error', true],
                [1, 0, 'Refresh halaman dan notifikasi', true],
            ],
        ],
        [
            'slug' => '18-monitoring-dan-laporan-boss',
            'title' => 'Monitoring dan Laporan Boss',
            'lanes' => ['Boss', 'Sistem', 'Database', 'Browser / Printer'],
            'steps' => [
                ['start', 0, 'start', ''],
                ['dashboard', 0, 'action', 'Membuka Dashboard KPI'],
                ['aggregate', 2, 'action', 'Agregasi pendapatan, order aktif, keterlambatan, dan pembayaran tertunda'],
                ['display', 1, 'action', 'Menampilkan summary rail dan aktivitas terbaru'],
                ['menu', 0, 'decision', 'Pilih Monitoring atau Laporan?'],
                ['monitor', 0, 'action', 'Filter booking, pekerjaan, pekerja, estimasi, invoice, atau pembayaran'],
                ['queryMonitor', 2, 'action', 'SELECT data sesuai role Boss, filter tahap/status, pencarian, dan pagination'],
                ['detail', 1, 'action', 'Menampilkan detail read-only tanpa aksi operasional'],
                ['report', 0, 'action', 'Pilih periode, layanan, dan pekerja lapangan'],
                ['queryReport', 2, 'action', 'Hitung KPI laporan dan ambil data pendapatan terfilter'],
                ['chart', 1, 'action', 'Menampilkan kartu KPI, tabel, dan grafik ringkasan'],
                ['export', 0, 'decision', 'Cetak atau Download CSV?'],
                ['csv', 3, 'action', 'Browser membentuk dan mengunduh file CSV dari tabel'],
                ['print', 3, 'action', 'window.print membuka dialog cetak/PDF'],
                ['end', 0, 'end', ''],
            ],
            'flows' => [
                ['start', 'dashboard'], ['dashboard', 'aggregate'], ['aggregate', 'display'],
                ['display', 'menu'], ['menu', 'monitor', 'Monitoring'], ['monitor', 'queryMonitor'],
                ['queryMonitor', 'detail'], ['detail', 'menu'],
                ['menu', 'report', 'Laporan'], ['report', 'queryReport'], ['queryReport', 'chart'],
                ['chart', 'export'], ['export', 'csv', 'CSV'], ['export', 'print', 'Cetak/PDF'],
                ['csv', 'end'], ['print', 'end'],
            ],
            'participants' => ['Boss', 'Dashboard/Reports', 'PHP Page Controller', 'Database', 'Browser'],
            'messages' => [
                [0, 1, 'Buka dashboard Boss'],
                [1, 2, 'Request KPI dashboard'],
                [2, 3, 'COUNT/SUM booking, invoice, payment, schedule'],
                [3, 2, 'Dataset KPI dan aktivitas', true],
                [2, 1, 'Render dashboard KPI', true],
                [0, 1, 'Pilih monitoring + filter'],
                [1, 2, 'GET page/filter/stage/status/q'],
                [2, 3, 'SELECT data operasional terfilter + pagination'],
                [3, 2, 'Rows monitoring read-only', true],
                [2, 1, 'Render daftar/detail', true],
                [0, 1, 'Pilih laporan + periode/layanan/pekerja'],
                [1, 2, 'GET reports filters'],
                [2, 3, 'Aggregate pendapatan dan performa'],
                [3, 2, 'KPI + data pendapatan', true],
                [2, 1, 'Render tabel/grafik laporan', true],
                [0, 4, 'Klik Download CSV / Cetak'],
                [4, 4, 'Bangun CSV atau window.print'],
                [4, 0, 'File CSV / dialog cetak PDF', true],
            ],
        ],
        [
            'slug' => '19-logout',
            'title' => 'Logout Pengguna',
            'lanes' => ['Pengguna', 'Sistem', 'Browser'],
            'steps' => [
                ['start', 0, 'start', ''],
                ['click', 0, 'action', 'Klik tombol Logout pada sidebar'],
                ['post', 2, 'action', 'Browser mengirim POST logout.php + CSRF token'],
                ['method', 1, 'decision', 'Request POST dan CSRF valid?'],
                ['clear', 1, 'action', 'Kosongkan seluruh data session'],
                ['cookie', 2, 'action', 'Hapus session cookie bila digunakan'],
                ['destroy', 1, 'action', 'session_destroy'],
                ['redirect', 1, 'action', 'Redirect ke login.php'],
                ['login', 0, 'action', 'Melihat halaman Login'],
                ['ignore', 1, 'error', 'Request tidak valid diarahkan kembali ke dashboard'],
                ['end', 0, 'end', ''],
            ],
            'flows' => [
                ['start', 'click'], ['click', 'post'], ['post', 'method'],
                ['method', 'clear', 'Valid'], ['method', 'ignore', 'Tidak valid'],
                ['clear', 'cookie'], ['cookie', 'destroy'], ['destroy', 'redirect'],
                ['redirect', 'login'], ['login', 'end'], ['ignore', 'end'],
            ],
            'participants' => ['Pengguna', 'Sidebar/Form', 'logout.php', 'Session Store', 'Browser'],
            'messages' => [
                [0, 1, 'Klik Logout'],
                [1, 2, 'POST logout.php + CSRF'],
                [2, 2, 'Validasi method POST dan verify_csrf'],
                [2, 3, 'Set $_SESSION = []'],
                [2, 4, 'Expire session cookie'],
                [2, 3, 'session_destroy'],
                [2, 4, 'Location: login.php'],
                [4, 0, 'Tampilkan halaman Login', true],
                [2, 4, '[request tidak valid] redirect dashboard', true],
            ],
        ],
    ];
}

function detailedActivityDiagram(array $spec): Diagram
{
    $laneCount = count($spec['lanes']);
    $laneWidth = 390;
    $margin = 35;
    $headerY = 105;
    $nodeGap = 112;
    $height = 220 + (count($spec['steps']) * $nodeGap);
    $width = ($laneCount * $laneWidth) + ($margin * 2);
    $d = new Diagram(
        'activity-diagram-rinci/' . $spec['slug'],
        'Activity Diagram — ' . $spec['title'],
        $width,
        $height
    );
    addTitle($d, 'Alur rinci berdasarkan validasi, role, status, dan aksi backend aplikasi ABI Taman');
    $laneHeight = $height - 175;
    foreach ($spec['lanes'] as $index => $lane) {
        $d->lane($margin + ($index * $laneWidth), $headerY, $laneWidth, $laneHeight, $lane, [
            'fill' => $index % 2 === 0 ? '#f8fafc' : '#eff6ff',
            'headerFill' => ['#dbeafe', '#bfdbfe', '#dcfce7', '#fed7aa', '#e9d5ff'][$index % 5],
            'fontSize' => 13,
        ]);
    }

    $coords = [];
    foreach ($spec['steps'] as $index => [$id, $lane, $type, $text]) {
        $centerX = $margin + ($lane * $laneWidth) + ($laneWidth / 2);
        $y = 175 + ($index * $nodeGap);
        if ($type === 'start') {
            $d->circle($centerX - 15, $y, 30, '', ['fill' => '#0f172a', 'stroke' => '#0f172a']);
            $coords[$id] = [$centerX, $y, $centerX, $y + 30, $lane];
            continue;
        }
        if ($type === 'end') {
            $d->circle($centerX - 18, $y, 36, '', ['fill' => '#0f172a', 'stroke' => '#ef4444', 'strokeWidth' => 3]);
            $d->circle($centerX - 10, $y + 8, 20, '', ['fill' => '#0f172a', 'stroke' => '#0f172a']);
            $coords[$id] = [$centerX, $y, $centerX, $y + 36, $lane];
            continue;
        }
        if ($type === 'decision') {
            $w = 190;
            $h = 86;
            $d->diamond($centerX - ($w / 2), $y, $w, $h, $text, [
                'fill' => '#fef3c7', 'stroke' => '#d97706', 'fontSize' => 11,
            ]);
            $coords[$id] = [$centerX, $y, $centerX, $y + $h, $lane];
            continue;
        }
        $w = $laneWidth - 105;
        $h = 72;
        $isError = $type === 'error';
        $d->rect($centerX - ($w / 2), $y, $w, $h, $text, [
            'fill' => $isError ? '#fee2e2' : ($lane === 1 ? '#dbeafe' : '#ffffff'),
            'stroke' => $isError ? '#dc2626' : ($lane === 1 ? '#2563eb' : '#475569'),
            'fontSize' => 11,
            'shadow' => true,
        ]);
        $coords[$id] = [$centerX, $y, $centerX, $y + $h, $lane];
    }

    foreach ($spec['flows'] as $flow) {
        [$from, $to] = $flow;
        $label = $flow[2] ?? '';
        [$fx, $fyTop, $fx2, $fyBottom, $fromLane] = $coords[$from];
        [$tx, $tyTop, $tx2, $tyBottom, $toLane] = $coords[$to];
        if ($tyTop >= $fyBottom) {
            $isLongSameLaneJump = $fromLane === $toLane && ($tyTop - $fyBottom) > ($nodeGap * 1.2);
            if ($isLongSameLaneJump) {
                $routeX = $margin + ($fromLane * $laneWidth) + 24;
                $fromCenterY = ($fyTop + $fyBottom) / 2;
                $toCenterY = ($tyTop + $tyBottom) / 2;
                $points = [[$fx2 - 95, $fromCenterY], [$routeX, $fromCenterY], [$routeX, $toCenterY], [$tx - 142, $toCenterY]];
                $labelX = $routeX + 52;
                $labelY = $fromCenterY - 9;
            } elseif ($fromLane === $toLane) {
                $points = [[$fx2, $fyBottom], [$tx, $tyTop]];
                $labelX = $fx2 + 42;
                $labelY = ($fyBottom + $tyTop) / 2;
            } else {
                $middleY = $fyBottom + max(18, ($tyTop - $fyBottom) / 2);
                $points = [[$fx2, $fyBottom], [$fx2, $middleY], [$tx, $middleY], [$tx, $tyTop]];
                $labelX = ($fx2 + $tx) / 2;
                $labelY = $middleY - 7;
            }
        } else {
            $routeX = min($fx2, $tx) - ($laneWidth / 2) + 28;
            $points = [[$fx2, $fyTop], [$routeX, $fyTop - 18], [$routeX, $tyTop + 36], [$tx, $tyTop + 36]];
            $labelX = $routeX + 48;
            $labelY = $fyTop - 25;
        }
        $d->polyline($points, $label, [
            'labelW' => 100,
            'labelX' => $labelX,
            'labelY' => $labelY,
            'fontSize' => 9,
        ]);
    }
    addFooter($d);
    return $d;
}

function detailedSequenceDiagram(array $spec): Diagram
{
    $count = count($spec['participants']);
    $margin = 30;
    $participantWidth = 200;
    $gap = 75;
    $width = max(1320, ($count * $participantWidth) + (($count - 1) * $gap) + ($margin * 2));
    $messageGap = 68;
    $height = 260 + (count($spec['messages']) * $messageGap);
    $d = new Diagram(
        'sequence-diagram-rinci/' . $spec['slug'],
        'Sequence Diagram — ' . $spec['title'],
        $width,
        $height
    );
    addTitle($d, 'Urutan pesan rinci antara aktor, halaman, backend, database, dan komponen pendukung');
    $available = $width - ($margin * 2);
    $step = $count > 1 ? ($available - $participantWidth) / ($count - 1) : 0;
    $centers = [];
    foreach ($spec['participants'] as $index => $participant) {
        $x = $margin + ($index * $step);
        $fill = $index === 0 ? '#ffffff' : ($index >= $count - 2 ? '#f0fdf4' : '#eff6ff');
        $stroke = $index >= $count - 2 ? '#15803d' : '#2563eb';
        $d->rect($x, 110, $participantWidth, 64, $participant, [
            'fill' => $fill, 'stroke' => $stroke, 'bold' => true, 'fontSize' => 12, 'shadow' => true,
        ]);
        $center = $x + ($participantWidth / 2);
        $centers[$index] = $center;
        $d->edge($center, 174, $center, $height - 65, '', [
            'arrow' => false, 'dashed' => true, 'stroke' => '#94a3b8',
        ]);
    }

    foreach ($spec['messages'] as $index => $message) {
        [$from, $to, $label] = $message;
        $return = (bool) ($message[3] ?? false);
        $y = 225 + ($index * $messageGap);
        $x1 = $centers[$from];
        $x2 = $centers[$to];
        if ($from === $to) {
            $loopWidth = min(135, $step * .55);
            $points = [[$x1, $y], [$x1 + $loopWidth, $y], [$x1 + $loopWidth, $y + 32], [$x1, $y + 32]];
            $d->polyline($points, ($index + 1) . '. ' . $label, [
                'stroke' => $return ? '#64748b' : '#2563eb',
                'dashed' => $return,
                'labelW' => 240,
                'labelX' => $x1 + ($loopWidth / 2),
                'labelY' => $y - 8,
                'fontSize' => 9,
            ]);
        } else {
            $d->edge($x1, $y, $x2, $y, ($index + 1) . '. ' . $label, [
                'stroke' => $return ? '#64748b' : '#2563eb',
                'dashed' => $return,
                'labelW' => min(250, abs($x2 - $x1) - 10),
                'labelX' => ($x1 + $x2) / 2,
                'labelY' => $y - 8,
                'fontSize' => 9,
            ]);
        }
    }
    addFooter($d);
    return $d;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $root = dirname(__DIR__);
    $outputDir = $root . '/dataabitaman/diagram-bab3';
    $svgDir = sys_get_temp_dir() . '/abi-taman-bab3-svg';
    if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
        throw new RuntimeException("Tidak dapat membuat direktori output: $outputDir");
    }
    if (!is_dir($svgDir) && !mkdir($svgDir, 0775, true) && !is_dir($svgDir)) {
        throw new RuntimeException("Tidak dapat membuat direktori SVG: $svgDir");
    }
    foreach (['activity-diagram-rinci', 'sequence-diagram-rinci'] as $subdir) {
        if (!is_dir($outputDir . '/' . $subdir) && !mkdir($outputDir . '/' . $subdir, 0775, true) && !is_dir($outputDir . '/' . $subdir)) {
            throw new RuntimeException("Tidak dapat membuat direktori output rinci: {$subdir}");
        }
        if (!is_dir($svgDir . '/' . $subdir) && !mkdir($svgDir . '/' . $subdir, 0775, true) && !is_dir($svgDir . '/' . $subdir)) {
            throw new RuntimeException("Tidak dapat membuat direktori SVG rinci: {$subdir}");
        }
    }

    $diagrams = [
        proposedSystem(),
        architecture(),
        useCase(),
        activityLogin(),
        activityBooking(),
        sequenceLogin(),
        sequenceBooking(),
        classDiagram(),
        erd(),
        erdTransformation(),
        lrs(),
    ];
    foreach (detailedProcessSpecs() as $processSpec) {
        $diagrams[] = detailedActivityDiagram($processSpec);
        $diagrams[] = detailedSequenceDiagram($processSpec);
    }

    foreach ($diagrams as $diagram) {
        $diagram->write(
            $outputDir . '/' . $diagram->slug . '.drawio',
            $svgDir . '/' . $diagram->slug . '.svg'
        );
        echo $diagram->slug . "\n";
    }

    $readme = <<<'MD'
# Diagram Bab 3 — ABI Taman

Folder ini berisi diagram perancangan sistem dalam format Draw.io yang dapat
diedit dan PNG siap dimasukkan ke naskah skripsi.

## Daftar diagram

1. Analisis Sistem Usulan
2. Perancangan Arsitektur Sistem
3. Use Case Diagram
4. Activity Diagram Login
5. Activity Diagram Proses Booking Jasa Taman
6. Sequence Diagram Login
7. Sequence Diagram Proses Booking Jasa Taman
8. Class Diagram
9. Entity Relationship Diagram (ERD)
10. Transformasi ERD ke LRS
11. Logical Record Structure (LRS)

## Activity dan sequence diagram rinci

Selain dua diagram ringkasan, tersedia **19 activity diagram rinci** dan
**19 sequence diagram rinci** pada subfolder:

- `activity-diagram-rinci/`
- `sequence-diagram-rinci/`

Setiap subfolder mencakup:

1. Registrasi Customer
2. Login
3. Profil dan Ubah Password
4. Pembuatan Booking
5. Konfirmasi dan Penjadwalan Survei
6. Pelaksanaan Survei
7. Penyusunan Estimasi
8. Review dan Pengiriman Estimasi
9. Keputusan Estimasi
10. Pengerjaan dan Update Progres
11. Revisi Estimasi
12. Penyelesaian Pekerjaan dan Invoice Draft
13. Review dan Pengiriman Invoice
14. Pembayaran dan Verifikasi
15. Penyelesaian Order dan Ulasan
16. Kelola Master Data
17. Kelola User dan Pengaturan
18. Monitoring dan Laporan Boss
19. Logout

## Dasar penyusunan

- Aktor dan fitur: `README.md`, navigasi per role, halaman, dan API aplikasi.
- Alur proses: status booking dari pengajuan sampai selesai.
- Struktur data: `database/refactor_schema.sql` (23 tabel dan 35 foreign key).
- Gaya penyajian: Bab 3 pada dokumen referensi
  `dataabitaman/Skripsi Afrizal Nur Perdana FINAL SIDANG.docx`.

Catatan: isi diagram telah disesuaikan untuk ABI Taman. Istilah MOORA, Admin,
dan Owner dari dokumen contoh tidak disalin karena bukan bagian dari sistem ini.
MD;
    file_put_contents($outputDir . '/README.md', $readme . "\n");
}
