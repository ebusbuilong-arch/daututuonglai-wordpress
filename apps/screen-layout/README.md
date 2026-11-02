# Phần mềm livestream màn hình — DTTL v1.0.0

Windows desktop application, promoted from user-validated Workspace 0.6.8. This release changes branding, assembly version, package name and documentation without changing recording, audio or docking behavior.

Run `./apps/screen-layout/build.ps1` on Windows with .NET Framework compiler and FFmpeg/ffprobe available. Pinned AForge and NAudio packages are verified before compilation. Existing LayoutTests and BroadcastTests cover geometry, window behavior, recording and local streaming; live platform accounts and physical cameras require manual validation.

Release ZIP: `Phan-mem-livestream-man-hinh-v1.0.0.zip`. Entry point remains `DTTL-Workspace.exe` for compatibility. FFmpeg is supplied separately by the user. Keep dependency DLLs, licenses and guide alongside the executable.

`HUONG-DAN-MAN-HINH-AO.txt` is the offline Vietnamese guide (legacy filename retained). `huong-dan-livestream-v1.html` is the full website guide. Publish the tested ZIP using the existing WordPress app catalogue upload form; do not reinstall the historical catalogue seed package to upgrade an app.

Known limitation: restore maximized third-party windows to normal size before docking. Docking places real windows and respects their minimum sizes. AI mode uses direct MP3 audio and excludes microphone/system audio. Saving MP4 asks for destination before recording; Stop finalizes the file. No automatic reconnect, scheduled streaming or lip sync.
