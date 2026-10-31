$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$out = Join-Path $root 'dist'
New-Item -ItemType Directory -Force $out | Out-Null
$csc = Join-Path $env:WINDIR 'Microsoft.NET\Framework64\v4.0.30319\csc.exe'
if (!(Test-Path $csc)) { $csc = Join-Path $env:WINDIR 'Microsoft.NET\Framework\v4.0.30319\csc.exe' }
& $csc /nologo /target:winexe /platform:anycpu /optimize+ "/win32manifest:$root\app.manifest" /reference:System.Windows.Forms.dll /reference:System.Drawing.dll "/out:$out\DTTL-Chia-Man-Hinh.exe" "$root\ScreenLayout.cs" "$root\DesktopDock.cs"
if ($LASTEXITCODE -ne 0) { throw 'Build failed' }
& $csc /nologo /target:exe /platform:anycpu /main:LayoutTests /reference:System.Windows.Forms.dll /reference:System.Drawing.dll "/out:$out\LayoutTests.exe" "$root\ScreenLayout.cs" "$root\DesktopDock.cs" "$root\..\..\tests\screen-layout\LayoutTests.cs"
if ($LASTEXITCODE -ne 0) { throw 'Test build failed' }
& "$out\LayoutTests.exe"
if ($LASTEXITCODE -ne 0) { throw 'Tests failed' }
Copy-Item "$root\HUONG-DAN.txt" $out
Get-FileHash "$out\DTTL-Chia-Man-Hinh.exe" -Algorithm SHA256 | ForEach-Object { "$($_.Hash.ToLower())  DTTL-Chia-Man-Hinh.exe" } | Set-Content "$out\SHA256.txt"
Compress-Archive -Path "$out\DTTL-Chia-Man-Hinh.exe","$out\HUONG-DAN.txt","$out\SHA256.txt" -DestinationPath "$out\DTTL-Chia-Man-Hinh-0.2.2-beta.zip" -Force
