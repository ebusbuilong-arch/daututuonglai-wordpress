$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$out = Join-Path $root 'dist'
New-Item -ItemType Directory -Force $out | Out-Null
$csc = Join-Path $env:WINDIR 'Microsoft.NET\Framework64\v4.0.30319\csc.exe'
if (!(Test-Path $csc)) { $csc = Join-Path $env:WINDIR 'Microsoft.NET\Framework\v4.0.30319\csc.exe' }
& $csc /nologo /target:winexe /platform:anycpu /optimize+ "/win32manifest:$root\app.manifest" /reference:System.Windows.Forms.dll /reference:System.Drawing.dll "/out:$out\DTTL-Chia-Man-Hinh.exe" "$root\ScreenLayout.cs" "$root\DesktopDock.cs"
if ($LASTEXITCODE -ne 0) { throw 'Build failed' }
& $csc /nologo /target:exe /platform:anycpu /main:LayoutTests /reference:System.Windows.Forms.dll /reference:System.Drawing.dll "/out:$out\LayoutTests.exe" "$root\ScreenLayout.cs" "$root\DesktopDock.cs" "$root\..\..\tests\screen-layout\LayoutTests.cs" "$root\Webcam.cs" "$root\VirtualDesktop.cs" "$root\ScreenBroadcast.cs"
if ($LASTEXITCODE -ne 0) { throw 'Test build failed' }
& "$out\LayoutTests.exe"
if ($LASTEXITCODE -ne 0) { throw 'Tests failed' }
Copy-Item "$root\HUONG-DAN.txt" $out
Get-FileHash "$out\DTTL-Chia-Man-Hinh.exe" -Algorithm SHA256 | ForEach-Object { "$($_.Hash.ToLower())  DTTL-Chia-Man-Hinh.exe" } | Set-Content "$out\SHA256.txt"
Compress-Archive -Path "$out\DTTL-Chia-Man-Hinh.exe","$out\HUONG-DAN.txt","$out\SHA256.txt" -DestinationPath "$out\DTTL-Chia-Man-Hinh-0.2.3-beta.zip" -Force

& $csc /nologo /target:winexe /main:VirtualDisplayProgram /platform:anycpu /optimize+ "/win32manifest:$root\app.manifest" /reference:System.Windows.Forms.dll /reference:System.Drawing.dll "/out:$out\DTTL-Workspace.exe" "$root\ScreenLayout.cs" "$root\DesktopDock.cs" "$root\Webcam.cs" "$root\VirtualDesktop.cs" "$root\ScreenBroadcast.cs"
if ($LASTEXITCODE -ne 0) { throw 'Virtual desktop build failed' }
Copy-Item "$root\HUONG-DAN-MAN-HINH-AO.txt" $out
Get-FileHash "$out\DTTL-Workspace.exe" -Algorithm SHA256 | ForEach-Object { "$($_.Hash.ToLower())  DTTL-Workspace.exe" } | Set-Content "$out\SHA256-virtual.txt"
Compress-Archive -Path "$out\DTTL-Workspace.exe","$out\HUONG-DAN-MAN-HINH-AO.txt","$out\SHA256-virtual.txt" -DestinationPath "$out\DTTL-Workspace-0.5-thu-nghiem.zip" -Force

& $csc /nologo /target:exe /main:BroadcastTests /reference:System.Windows.Forms.dll /reference:System.Drawing.dll "/out:$out\BroadcastTests.exe" "$root\ScreenLayout.cs" "$root\ScreenBroadcast.cs" "$root\..\..\tests\screen-layout\BroadcastTests.cs"
if ($LASTEXITCODE -ne 0) { throw 'Broadcast test build failed' }
$ffmpeg = (Get-Command ffmpeg.exe -ErrorAction Stop).Source
# Chocolatey may expose a shim; find the matching actual encoder and ffprobe.
if (!(Test-Path (Join-Path (Split-Path $ffmpeg) 'ffprobe.exe'))) { throw 'ffprobe missing' }
& "$out\BroadcastTests.exe" $ffmpeg $out
if ($LASTEXITCODE -ne 0) { throw 'Broadcast tests failed' }
