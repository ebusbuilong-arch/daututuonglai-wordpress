# Original DTTL Workspace mark: a split monitor with a play symbol.
param([Parameter(Mandatory=$true)][string]$OutputDirectory)
Add-Type -AssemblyName System.Drawing
function RoundedPath([float]$x,[float]$y,[float]$w,[float]$h,[float]$r) {
 $p = New-Object Drawing.Drawing2D.GraphicsPath
 $d = $r * 2
 $p.AddArc($x,$y,$d,$d,180,90); $p.AddArc(($x+$w-$d),$y,$d,$d,270,90)
 $p.AddArc(($x+$w-$d),($y+$h-$d),$d,$d,0,90); $p.AddArc($x,($y+$h-$d),$d,$d,90,90)
 $p.CloseFigure(); return ,$p
}
$master = New-Object Drawing.Bitmap 256,256
$g = [Drawing.Graphics]::FromImage($master)
$g.SmoothingMode = 'AntiAlias'; $g.Clear([Drawing.Color]::Transparent)
$tile = RoundedPath 8 8 240 240 52
$gradient = New-Object Drawing.Drawing2D.LinearGradientBrush ([Drawing.Point]::new(0,0)),([Drawing.Point]::new(256,256)),([Drawing.Color]::FromArgb(25,55,72)),([Drawing.Color]::FromArgb(10,25,38))
$g.FillPath($gradient,$tile)
$frame = RoundedPath 43 57 170 125 15
$white = New-Object Drawing.SolidBrush ([Drawing.Color]::FromArgb(235,251,252))
$teal = New-Object Drawing.SolidBrush ([Drawing.Color]::FromArgb(43,213,187))
$ink = New-Object Drawing.SolidBrush ([Drawing.Color]::FromArgb(22,53,68))
$g.FillPath($white,$frame)
$g.FillRectangle($ink,54,69,98,101); $g.FillRectangle($teal,161,69,40,45); $g.FillRectangle($ink,161,123,40,47)
$points = [Drawing.PointF[]]@([Drawing.PointF]::new(86,91),[Drawing.PointF]::new(86,148),[Drawing.PointF]::new(128,120))
$g.FillPolygon($teal,$points)
$g.FillRectangle($white,117,181,22,18)
$base = RoundedPath 88 198 80 12 6; $g.FillPath($white,$base)
$g.Dispose(); $tile.Dispose(); $frame.Dispose(); $base.Dispose(); $gradient.Dispose(); $white.Dispose(); $teal.Dispose(); $ink.Dispose()
$master.Save((Join-Path $OutputDirectory 'workspace-icon.png'),[Drawing.Imaging.ImageFormat]::Png)
$sizes = @(16,24,32,48,64,128,256); $frames = @()
foreach ($size in $sizes) {
 $b = New-Object Drawing.Bitmap $size,$size
 $g = [Drawing.Graphics]::FromImage($b); $g.InterpolationMode = 'HighQualityBicubic'; $g.PixelOffsetMode = 'HighQuality'
 $g.DrawImage($master,0,0,$size,$size); $g.Dispose()
 $stream = New-Object IO.MemoryStream; $b.Save($stream,[Drawing.Imaging.ImageFormat]::Png)
 $frames += ,$stream.ToArray(); $stream.Dispose(); $b.Dispose()
}
$master.Dispose()
$file = [IO.File]::Create((Join-Path $OutputDirectory 'DTTL-Workspace.ico'))
$writer = New-Object IO.BinaryWriter $file
try {
 $writer.Write([UInt16]0); $writer.Write([UInt16]1); $writer.Write([UInt16]$sizes.Count)
 $offset = 6 + 16 * $sizes.Count
 for ($i=0; $i -lt $sizes.Count; $i++) {
  $dimension = if ($sizes[$i] -eq 256) { 0 } else { $sizes[$i] }
  $writer.Write([byte]$dimension); $writer.Write([byte]$dimension); $writer.Write([byte]0); $writer.Write([byte]0)
  $writer.Write([UInt16]1); $writer.Write([UInt16]32); $writer.Write([UInt32]$frames[$i].Length); $writer.Write([UInt32]$offset)
  $offset += $frames[$i].Length
 }
 foreach ($frame in $frames) { $writer.Write([byte[]]$frame) }
} finally { $writer.Dispose() }
Write-Host 'PASS generated original seven-size DTTL icon (16–256 px)'
