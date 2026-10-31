# DTTL Desktop Dock 0.2 beta

Vietnamese Windows Forms portable EXE: normal desktop plus a right-hand AppBar with 1–3 drag targets. Shell work-area reservation keeps ordinary maximized windows out of auxiliary space. WinEvent move start/end hooks capture placement, dock on drop, and release on drag out. Replacing a slot restores its former occupant; exit restores all remaining occupants and removes the AppBar. One process per session. No network, telemetry, startup registration, or admin requirement.

Build on Windows with `powershell -ExecutionPolicy Bypass -File apps/screen-layout/build.ps1` from repository root. The build command's execution policy does not apply to the EXE. Framework compiler emits AnyCPU; requirements Windows 10/11 Intel/AMD with .NET Framework 4.8.

CI tests geometry including negative monitor coordinates, native AppBar reservation, main-window maximization, cross-process synthetic move events through the actual WinEvent hook, 1–3 slot positioning, replacement, release, and exit restoration. Synthetic events do not replace manual drag testing. Windows 10/11, mixed DPI, Explorer restart, physical monitor removal and different real apps still require manual testing. Minimum window sizes, elevated apps and fullscreen apps have limitations described in HUONG-DAN.txt.

Workflow also packages the WordPress catalog plugin. Catalog supports multiple apps/categories/platform ZIPs and member-only downloads. App content remains draft until accepted; SEO/publication is separate. Do not commit binaries or payload.php to the public repository. Production deployment allowlist has not been changed.
