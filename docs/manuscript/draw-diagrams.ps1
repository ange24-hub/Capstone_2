$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing
$diagramRoot = $PSScriptRoot
$figureRoot = Join-Path $diagramRoot 'figures'
New-Item -ItemType Directory -Path $figureRoot -Force | Out-Null
$diagrams = Get-Content -LiteralPath (Join-Path $diagramRoot 'diagrams.json') -Raw | ConvertFrom-Json
foreach ($diagram in $diagrams) {
    $bitmap = New-Object System.Drawing.Bitmap([int]$diagram.width, [int]($diagram.height + 140))
    $bitmap.SetResolution(220, 220)
    $graphic = [System.Drawing.Graphics]::FromImage($bitmap)
    $graphic.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $graphic.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit
    $graphic.Clear([System.Drawing.Color]::White)
    $pen = New-Object System.Drawing.Pen([System.Drawing.Color]::Black, 3)
    $font = New-Object System.Drawing.Font('Arial', 26, [System.Drawing.FontStyle]::Regular, [System.Drawing.GraphicsUnit]::Pixel)
    $smallFont = New-Object System.Drawing.Font('Arial', 23, [System.Drawing.FontStyle]::Regular, [System.Drawing.GraphicsUnit]::Pixel)
    $brush = [System.Drawing.Brushes]::Black
    $format = New-Object System.Drawing.StringFormat
    $format.Alignment = [System.Drawing.StringAlignment]::Center
    $format.LineAlignment = [System.Drawing.StringAlignment]::Center
    foreach ($edge in $diagram.lines) {
        $edgePen = New-Object System.Drawing.Pen([System.Drawing.Color]::Black, 3)
        if ($edge.dashed) { $edgePen.DashStyle = [System.Drawing.Drawing2D.DashStyle]::Dash }
        if ($edge.arrow) { $edgePen.CustomEndCap = New-Object System.Drawing.Drawing2D.AdjustableArrowCap(5, 7) }
        $points = [System.Drawing.PointF[]]@($edge.points | ForEach-Object { [System.Drawing.PointF]::new([single]$_[0], [single]$_[1]) })
        $graphic.DrawLines($edgePen, $points)
        $edgePen.Dispose()
    }
    foreach ($item in $diagram.nodes) {
        $rect = [System.Drawing.RectangleF]::new($item.x, $item.y, $item.w, $item.h)
        if ($item.shape -eq 'ellipse') {
            $graphic.FillEllipse([System.Drawing.Brushes]::White, $rect)
            $graphic.DrawEllipse($pen, $rect)
        } elseif ($item.shape -eq 'diamond') {
            $points = [System.Drawing.PointF[]]@([System.Drawing.PointF]::new($item.x + $item.w/2, $item.y), [System.Drawing.PointF]::new($item.x+$item.w, $item.y+$item.h/2), [System.Drawing.PointF]::new($item.x+$item.w/2, $item.y+$item.h), [System.Drawing.PointF]::new($item.x, $item.y+$item.h/2))
            $graphic.FillPolygon([System.Drawing.Brushes]::White, $points)
            $graphic.DrawPolygon($pen, $points)
        } elseif ($item.shape -eq 'io') {
            $points = [System.Drawing.PointF[]]@([System.Drawing.PointF]::new($item.x+35,$item.y),[System.Drawing.PointF]::new($item.x+$item.w,$item.y),[System.Drawing.PointF]::new($item.x+$item.w-35,$item.y+$item.h),[System.Drawing.PointF]::new($item.x,$item.y+$item.h))
            $graphic.FillPolygon([System.Drawing.Brushes]::White, $points)
            $graphic.DrawPolygon($pen, $points)
        } elseif ($item.shape -eq 'store') {
            $graphic.FillRectangle([System.Drawing.Brushes]::White, $rect)
            $graphic.DrawLine($pen,$item.x,$item.y,($item.x+$item.w),$item.y)
            $graphic.DrawLine($pen,$item.x,($item.y+$item.h),($item.x+$item.w),($item.y+$item.h))
        } elseif ($item.shape -eq 'boundary') {
            $graphic.DrawRectangle($pen,$item.x,$item.y,$item.w,$item.h)
            $graphic.DrawString($item.text,$smallFont,$brush,[single]($item.x+15),[single]($item.y+15))
            continue
        } elseif ($item.shape -eq 'actor') {
            $cx = [single]($item.x+150)
            $cy = [single]($item.y+20)
            $graphic.DrawEllipse($pen,($cx-15),$cy,30,30)
            $graphic.DrawLine($pen,$cx,($cy+30),$cx,($cy+75))
            $graphic.DrawLine($pen,($cx-35),($cy+45),($cx+35),($cy+45))
            $graphic.DrawLine($pen,$cx,($cy+75),($cx-30),($cy+105))
            $graphic.DrawLine($pen,$cx,($cy+75),($cx+30),($cy+105))
            $graphic.DrawString($item.text,$font,$brush,[System.Drawing.RectangleF]::new($item.x,($item.y+$item.h-15),$item.w,55),$format)
            continue
        } else {
            $graphic.FillRectangle([System.Drawing.Brushes]::White,$rect)
            $graphic.DrawRectangle($pen,$item.x,$item.y,$item.w,$item.h)
        }
        $graphic.DrawString($item.text,$font,$brush,$rect,$format)
    }
    foreach ($edge in $diagram.lines) {
        if ($edge.label) {
            $measure = $graphic.MeasureString($edge.label,$smallFont)
            $graphic.FillRectangle([System.Drawing.Brushes]::White,[single]$edge.x,[single]$edge.y,[single]$measure.Width,[single]$measure.Height)
            $graphic.DrawString($edge.label,$smallFont,$brush,[single]$edge.x,[single]$edge.y)
        }
    }
    $noteY = [single]($diagram.height + 5)
    foreach ($note in $diagram.notes) {
        $graphic.DrawString($note,$smallFont,$brush,[System.Drawing.RectangleF]::new(25,$noteY,1350,60))
        $noteY += 55
    }
    $bitmap.Save((Join-Path $figureRoot ($diagram.name+'.png')),[System.Drawing.Imaging.ImageFormat]::Png)
    $format.Dispose(); $font.Dispose(); $smallFont.Dispose(); $pen.Dispose(); $graphic.Dispose(); $bitmap.Dispose()
}
Write-Output ('Created '+$diagrams.Count+' manuscript diagrams.')
