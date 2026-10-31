$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$out = Join-Path $root 'dist'
New-Item -ItemType Directory -Force $out | Out-Null
# Pinned camera libraries; webcam works offline without an encoder installation.
Add-Type -AssemblyName System.IO.Compression.FileSystem
$packages = @{
 'aforge' = '0795462abcfff94e1347cc8500a82edfaa6e0367a8fc11d1a854949764b47861'
 'aforge.video' = 'd6adfef653e8469b0fdb4eed54501185b11788bd2e952ee3c1223b18d95a7c5e'
 'aforge.video.directshow' = '0049bcf3e448689c238ae60522fa13956a58ad67a086a18af414b2d36939acec'
}
foreach ($name in $packages.Keys) {
 $archive = Join-Path $out "$name.nupkg"
 Invoke-WebRequest "https://api.nuget.org/v3-flatcontainer/$name/2.2.5/$name.2.2.5.nupkg" -OutFile $archive -UseBasicParsing
 if ((Get-FileHash $archive -Algorithm SHA256).Hash.ToLower() -ne $packages[$name]) { throw "Camera package checksum failed: $name" }
 $zip = [IO.Compression.ZipFile]::OpenRead($archive)
 try { foreach ($entry in $zip.Entries) { if ($entry.FullName -like 'lib/*.dll') { [IO.Compression.ZipFileExtensions]::ExtractToFile($entry,(Join-Path $out $entry.Name),$true) } } } finally { $zip.Dispose() }
}
$cameraRefs = @("/reference:$out\AForge.dll", "/reference:$out\AForge.Video.dll", "/reference:$out\AForge.Video.DirectShow.dll")
$csc = Join-Path $env:WINDIR 'Microsoft.NET\Framework64\v4.0.30319\csc.exe'
if (!(Test-Path $csc)) { $csc = Join-Path $env:WINDIR 'Microsoft.NET\Framework\v4.0.30319\csc.exe' }
& $csc /nologo /target:winexe /platform:anycpu /optimize+ "/win32manifest:$root\app.manifest" /reference:System.Windows.Forms.dll /reference:System.Drawing.dll "/out:$out\DTTL-Chia-Man-Hinh.exe" "$root\ScreenLayout.cs" "$root\DesktopDock.cs"
if ($LASTEXITCODE -ne 0) { throw 'Build failed' }
& $csc @cameraRefs /nologo /target:exe /platform:anycpu /main:LayoutTests /reference:System.Windows.Forms.dll /reference:System.Drawing.dll "/out:$out\LayoutTests.exe" "$root\ScreenLayout.cs" "$root\DesktopDock.cs" "$root\..\..\tests\screen-layout\LayoutTests.cs" "$root\Webcam.cs" "$root\NativeWebcam.cs" "$root\VirtualDesktop.cs" "$root\ScreenBroadcast.cs"
if ($LASTEXITCODE -ne 0) { throw 'Test build failed' }
& "$out\LayoutTests.exe"
if ($LASTEXITCODE -ne 0) { throw 'Tests failed' }
Copy-Item "$root\HUONG-DAN.txt" $out
Get-FileHash "$out\DTTL-Chia-Man-Hinh.exe" -Algorithm SHA256 | ForEach-Object { "$($_.Hash.ToLower())  DTTL-Chia-Man-Hinh.exe" } | Set-Content "$out\SHA256.txt"
Compress-Archive -Path "$out\DTTL-Chia-Man-Hinh.exe","$out\HUONG-DAN.txt","$out\SHA256.txt" -DestinationPath "$out\DTTL-Chia-Man-Hinh-0.2.3-beta.zip" -Force

& $csc @cameraRefs /nologo /target:winexe /main:VirtualDisplayProgram /platform:anycpu /optimize+ "/win32manifest:$root\app.manifest" /reference:System.Windows.Forms.dll /reference:System.Drawing.dll "/out:$out\DTTL-Workspace.exe" "$root\ScreenLayout.cs" "$root\DesktopDock.cs" "$root\Webcam.cs" "$root\NativeWebcam.cs" "$root\VirtualDesktop.cs" "$root\ScreenBroadcast.cs"
if ($LASTEXITCODE -ne 0) { throw 'Virtual desktop build failed' }
Copy-Item "$root\HUONG-DAN-MAN-HINH-AO.txt" $out
Get-FileHash "$out\DTTL-Workspace.exe" -Algorithm SHA256 | ForEach-Object { "$($_.Hash.ToLower())  DTTL-Workspace.exe" } | Set-Content "$out\SHA256-virtual.txt"
Copy-Item "$root\licenses" $out -Recurse -Force
Copy-Item "$root\THIRD-PARTY.txt" $out
Compress-Archive -Path "$out\DTTL-Workspace.exe","$out\AForge*.dll","$out\licenses","$out\THIRD-PARTY.txt","$out\HUONG-DAN-MAN-HINH-AO.txt","$out\SHA256-virtual.txt" -DestinationPath "$out\DTTL-Workspace-0.6-thu-nghiem.zip" -Force

& $csc @cameraRefs /nologo /target:exe /main:BroadcastTests /reference:System.Windows.Forms.dll /reference:System.Drawing.dll "/out:$out\BroadcastTests.exe" "$root\ScreenLayout.cs" "$root\ScreenBroadcast.cs" "$root\Webcam.cs" "$root\NativeWebcam.cs" "$root\..\..\tests\screen-layout\BroadcastTests.cs"
if ($LASTEXITCODE -ne 0) { throw 'Broadcast test build failed' }
$ffmpeg = (Get-Command ffmpeg.exe -ErrorAction Stop).Source
# Chocolatey may expose a shim; find the matching actual encoder and ffprobe.
if (!(Test-Path (Join-Path (Split-Path $ffmpeg) 'ffprobe.exe'))) { throw 'ffprobe missing' }
& "$out\BroadcastTests.exe" $ffmpeg $out
if ($LASTEXITCODE -ne 0) { throw 'Broadcast tests failed' }
