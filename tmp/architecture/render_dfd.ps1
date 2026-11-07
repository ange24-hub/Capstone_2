param([string]$InputFile = 'dfd-render.json', [string]$OutputFile = 'sb-ispgm-level-1-dfd.png')
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing
$d = Get-Content -LiteralPath (Join-Path $PSScriptRoot $InputFile) -Raw | ConvertFrom-Json
$bmp = [System.Drawing.Bitmap]::new([int]$d.width,[int]$d.height)
$g = [System.Drawing.Graphics]::FromImage($bmp)
$g.Clear([System.Drawing.Color]::White)
$g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
$g.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit
$fmt = [System.Drawing.StringFormat]::new()
$fmt.Alignment = [System.Drawing.StringAlignment]::Center
$fmt.LineAlignment = [System.Drawing.StringAlignment]::Center
function Draw-Text($x,$y,$value,$size,$bold) {
    $style = if ($bold) {[System.Drawing.FontStyle]::Bold} else {[System.Drawing.FontStyle]::Regular}
    $font = [System.Drawing.Font]::new('Arial',[single]$size,$style,[System.Drawing.GraphicsUnit]::Pixel)
    $brush = [System.Drawing.SolidBrush]::new([System.Drawing.ColorTranslator]::FromHtml('#243447'))
    $g.DrawString([string]$value,$font,$brush,[System.Drawing.PointF]::new($x,$y),$fmt)
    $font.Dispose(); $brush.Dispose()
}
foreach ($n in $d.nodes) {
    $b = [System.Drawing.SolidBrush]::new([System.Drawing.ColorTranslator]::FromHtml($n.fill))
    $p = [System.Drawing.Pen]::new([System.Drawing.ColorTranslator]::FromHtml($n.stroke),2.5)
    $r = [System.Drawing.RectangleF]::new($n.x,$n.y,$n.w,$n.h)
    if ($n.shape -eq 'boundary') {
        $p.DashStyle = [System.Drawing.Drawing2D.DashStyle]::Dash
        $g.DrawRectangle($p,$n.x,$n.y,$n.w,$n.h)
    } elseif ($n.shape -eq 'diamond' -or $n.shape -eq 'io') {
        if ($n.shape -eq 'diamond') {
            $pts = [System.Drawing.PointF[]]@([System.Drawing.PointF]::new(($n.x+$n.w/2),$n.y),[System.Drawing.PointF]::new(($n.x+$n.w),($n.y+$n.h/2)),[System.Drawing.PointF]::new(($n.x+$n.w/2),($n.y+$n.h)),[System.Drawing.PointF]::new($n.x,($n.y+$n.h/2)))
        } else {
            $pts = [System.Drawing.PointF[]]@([System.Drawing.PointF]::new(($n.x+35),$n.y),[System.Drawing.PointF]::new(($n.x+$n.w),$n.y),[System.Drawing.PointF]::new(($n.x+$n.w-35),($n.y+$n.h)),[System.Drawing.PointF]::new($n.x,($n.y+$n.h)))
        }
        $g.FillPolygon($b,$pts); $g.DrawPolygon($p,$pts)
    } elseif ($n.shape -eq 'ellipse') {
        $g.FillEllipse($b,$r); $g.DrawEllipse($p,$r)
    } elseif ($n.shape -eq 'process') {
        $path = [System.Drawing.Drawing2D.GraphicsPath]::new()
        $path.AddArc($n.x,$n.y,48,48,180,90)
        $path.AddArc(($n.x+$n.w-48),$n.y,48,48,270,90)
        $path.AddArc(($n.x+$n.w-48),($n.y+$n.h-48),48,48,0,90)
        $path.AddArc($n.x,($n.y+$n.h-48),48,48,90,90)
        $path.CloseFigure(); $g.FillPath($b,$path); $g.DrawPath($p,$path); $path.Dispose()
    } else {
        $g.FillRectangle($b,$r)
        if ($n.shape -eq 'store') {
            $g.DrawLine($p,$n.x,$n.y,($n.x+$n.w),$n.y)
            $g.DrawLine($p,$n.x,($n.y+$n.h),($n.x+$n.w),($n.y+$n.h))
        } else { $g.DrawRectangle($p,$n.x,$n.y,$n.w,$n.h) }
    }
    $size = if ($n.size) {$n.size} elseif ($n.shape -eq 'process') {28} else {25}
    Draw-Text ($n.x+$n.w/2) ($n.y+$n.h/2) $n.text $size $true
    $p.Dispose(); $b.Dispose()
}
foreach ($e in $d.edges) {
    $p = [System.Drawing.Pen]::new([System.Drawing.ColorTranslator]::FromHtml('#617184'),2)
    if (-not $e.plain) { $p.CustomEndCap = [System.Drawing.Drawing2D.AdjustableArrowCap]::new(5,6) }
    if ($e.both) { $p.CustomStartCap = [System.Drawing.Drawing2D.AdjustableArrowCap]::new(5,6) }
    if ($e.points) {
        $pts = [System.Drawing.PointF[]]@($e.points | ForEach-Object { [System.Drawing.PointF]::new($_[0],$_[1]) })
        $g.DrawLines($p,$pts)
        Draw-Text $e.lx $e.ly $e.label 22 $false
    } else {
        $g.DrawLine($p,$e.x1,$e.y,$e.x2,$e.y)
        Draw-Text (($e.x1+$e.x2)/2) ($e.y-15) $e.label 22 $false
    }
    $p.Dispose()
}
foreach ($t in $d.texts) { Draw-Text $t.x $t.y $t.text $t.size $t.bold }
$dest = Join-Path $PSScriptRoot ('../../output/architecture/'+$OutputFile)
$bmp.Save($dest,[System.Drawing.Imaging.ImageFormat]::Png)
$fmt.Dispose(); $g.Dispose(); $bmp.Dispose()
Write-Output $dest
